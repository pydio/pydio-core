<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Command\Event\InitEvent;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Pool;
use GuzzleHttp\Ring\Future\FutureInterface;
use GuzzleHttp\Ring\Future\FutureValue;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Message\RequestInterface;

/**
 * Abstract client implementation that provides a basic implementation of
 * several methods. Concrete implementations may choose to extend this class
 * or to completely implement all of the methods of ServiceClientInterface.
 */
abstract class AbstractClient implements ServiceClientInterface
{
    use HasEmitterTrait;

    /** @var ClientInterface HTTP client used to send requests */
    private $client;

    /** @var Collection Service client configuration data */
    private $config;

    /**
     * The default client constructor is responsible for setting private
     * properties on the client and accepts an associative array of
     * configuration parameters:
     *
     * - defaults: Associative array of default command parameters to add to
     *   each command created by the client.
     * - emitter: (internal only) A custom event emitter to use with the client.
     *
     * Concrete implementations may choose to support additional configuration
     * settings as needed.
     *
     * @param ClientInterface $client Client used to send HTTP requests
     * @param array           $config Client configuration options
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        ClientInterface $client,
        array $config = []
    ) {
        $this->client = $client;

        // Ensure the defaults key is an array so we can easily merge later.
        if (!isset($config['defaults'])) {
            $config['defaults'] = [];
        }

        if (isset($config['emitter'])) {
            $this->emitter = $config['emitter'];
            unset($config['emitter']);
        }

        $this->config = new Collection($config);
    }

    public function __call($name, array $arguments)
    {
        return $this->execute(
            $this->getCommand(
                $name,
                isset($arguments[0]) ? $arguments[0] : []
            )
        );
    }

    public function execute(CommandInterface $command)
    {
        $trans = $this->initTransaction($command);

        if ($trans->result !== null) {
            return $trans->result;
        }

        try {
            $trans->response = $this->client->send($trans->request);
            return $trans->response instanceof FutureInterface
                ? $this->createFutureResult($trans)
                : $trans->response;
        } catch (CommandException $e) {
            // Command exceptions are thrown in the command layer, so throw 'em.
            throw $e;
        } catch (\Exception $e) {
            // Handle when a command result is set after a terminal request
            // error was encountered.
            if ($trans->result !== null) {
                return $trans->result;
            }
            $trans->exception = $e;
            throw $this->createCommandException($trans);
        }
    }

    public function executeAll($commands, array $options = [])
    {
        $this->createPool($commands, $options)->wait();
    }

    public function createPool($commands, array $options = [])
    {
        return new Pool(
            $this->client,
            new CommandToRequestIterator(
                function (CommandInterface $command) {
                    $trans = $this->initTransaction($command);
                    return [
                        'request' => $trans->request,
                        'result'  => $trans->result
                    ];
                },
                $commands,
                $options
            ),
            isset($options['pool_size']) ? ['pool_size' => $options['pool_size']] : []
        );
    }

    public function getHttpClient()
    {
        return $this->client;
    }

    public function getConfig($keyOrPath = null)
    {
        if ($keyOrPath === null) {
            return $this->config->toArray();
        }

        if (strpos($keyOrPath, '/') === false) {
            return $this->config[$keyOrPath];
        }

        return $this->config->getPath($keyOrPath);
    }

    public function setConfig($keyOrPath, $value)
    {
        $this->config->setPath($keyOrPath, $value);
    }

    public function createCommandException(CommandTransaction $transaction)
    {
        $cn = 'GuzzleHttp\\Command\\Exception\\CommandException';

        // Don't continuously wrap the same exceptions.
        if ($transaction->exception instanceof CommandException) {
            return $transaction->exception;
        }

        if ($transaction->response) {
            $statusCode = (string) $transaction->response->getStatusCode();
            if ($statusCode[0] == '4') {
                $cn = 'GuzzleHttp\\Command\\Exception\\CommandClientException';
            } elseif ($statusCode[0] == '5') {
                $cn = 'GuzzleHttp\\Command\\Exception\\CommandServerException';
            }
        }

        return new $cn(
            "Error executing command: " . $transaction->exception->getMessage(),
            $transaction,
            $transaction->exception
        );
    }

    /**
     * Prepares a request for the command.
     *
     * @param CommandTransaction $trans Command and context to serialize.
     *
     * @return RequestInterface
     */
    abstract protected function serializeRequest(CommandTransaction $trans);

    /**
     * Creates a future result for a given command transaction.
     *
     * This method really should beoverridden in subclasses to implement custom
     * future response results.
     *
     * @param CommandTransaction $transaction
     *
     * @return FutureInterface
     */
    protected function createFutureResult(CommandTransaction $transaction)
    {
        return new FutureValue(
            $transaction->response->then(function () use ($transaction) {
                return $transaction->result;
            }),
            // Wait function derefs the response which populates the result.
            [$transaction->response, 'wait'],
            [$transaction->response, 'cancel']
        );
    }

    /**
     * Initialize a transaction for a command and send the prepare event.
     *
     * @param CommandInterface $command Command to associate with the trans.
     *
     * @return CommandTransaction
     */
    protected function initTransaction(CommandInterface $command)
    {
        $trans = new CommandTransaction($this, $command);
        // Throwing in the init event WILL NOT emit an error event.
        $command->getEmitter()->emit('init', new InitEvent($trans));
        $trans->request = $this->serializeRequest($trans);

        if ($future = $command->getFuture()) {
            $trans->request->getConfig()->set('future', $future);
        }

        $trans->state = 'prepared';
        $prep = new PreparedEvent($trans);

        try {
            $command->getEmitter()->emit('prepared', $prep);
        } catch (\Exception $e) {
            $trans->exception = $e;
            $trans->exception = $this->createCommandException($trans);
        }

        // If the command failed in the prepare event or was intercepted, then
        // emit the process event now and skip hooking up the request.
        if ($trans->exception || $prep->isPropagationStopped()) {
            $this->emitProcess($trans);
            return $trans;
        }

        $trans->state = 'executing';

        // When a request completes, process the request at the command
        // layer.
        $trans->request->getEmitter()->on(
            'end',
            function (EndEvent $e) use ($trans) {
                $trans->response = $e->getResponse();
                if ($trans->exception = $e->getException()) {
                    $trans->exception = $this->createCommandException($trans);
                }
                $this->emitProcess($trans);
            }, RequestEvents::LATE
        );

        return $trans;
    }

    /**
     * Finishes the process event for the command.
     */
    private function emitProcess(CommandTransaction $trans)
    {
        $trans->state = 'process';

        try {
            // Emit the final "process" event for the command.
            $trans->command->getEmitter()->emit('process', new ProcessEvent($trans));
        } catch (\Exception $ex) {
            // Override any previous exception with the most recent exception.
            $trans->exception = $ex;
            $trans->exception = $this->createCommandException($trans);
        }

        $trans->state = 'end';

        // If the transaction still has the exception, then throw it.
        if ($trans->exception) {
            throw $trans->exception;
        }
    }
}
