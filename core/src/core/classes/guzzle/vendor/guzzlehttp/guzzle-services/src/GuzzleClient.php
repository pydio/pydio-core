<?php
namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\AbstractClient;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Guzzle\Subscriber\ProcessResponse;
use GuzzleHttp\Command\Guzzle\Subscriber\ValidateInput;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Ring\Future\FutureArray;

/**
 * Default Guzzle web service client implementation.
 */
class GuzzleClient extends AbstractClient
{
    /** @var Description Guzzle service description */
    private $description;

    /** @var callable Factory used for creating commands */
    private $commandFactory;

    /** @var callable Serializer */
    private $serializer;

    /**
     * The client constructor accepts an associative array of configuration
     * options:
     *
     * - defaults: Associative array of default command parameters to add to
     *   each command created by the client.
     * - validate: Specify if command input is validated (defaults to true).
     *   Changing this setting after the client has been created will have no
     *   effect.
     * - process: Specify if HTTP responses are parsed (defaults to true).
     *   Changing this setting after the client has been created will have no
     *   effect.
     * - response_locations: Associative array of location types mapping to
     *   ResponseLocationInterface objects.
     * - serializer: Optional callable that accepts a CommandTransactions and
     *   returns a serialized request object.
     *
     * @param ClientInterface      $client      HTTP client to use.
     * @param DescriptionInterface $description Guzzle service description
     * @param array                $config      Configuration options
     */
    public function __construct(
        ClientInterface $client,
        DescriptionInterface $description,
        array $config = []
    ) {
        parent::__construct($client, $config);
        $this->description = $description;
        $this->processConfig($config);
    }

    public function getCommand($name, array $args = [])
    {
        $factory = $this->commandFactory;

        // Determine if a future array should be returned.
        if (!empty($args['@future'])) {
            $future = !empty($args['@future']);
            unset($args['@future']);
        } else {
            $future = false;
        }

        // Merge in default command options
        $args += $this->getConfig('defaults');

        if ($command = $factory($name, $args, $this)) {
            $command->setFuture($future);
            return $command;
        }

        throw new \InvalidArgumentException("No operation found named $name");
    }

    public function getDescription()
    {
        return $this->description;
    }

    protected function createFutureResult(CommandTransaction $transaction)
    {
        return new FutureArray(
            $transaction->response->then(function () use ($transaction) {
                return $transaction->result;
            }),
            [$transaction->response, 'wait'],
            [$transaction->response, 'cancel']
        );
    }

    protected function serializeRequest(CommandTransaction $trans)
    {
        $fn = $this->serializer;
        return $fn($trans);
    }

    /**
     * Creates a callable function used to create command objects from a
     * service description.
     *
     * @param DescriptionInterface $description Service description
     *
     * @return callable Returns a command factory
     */
    public static function defaultCommandFactory(DescriptionInterface $description)
    {
        return function (
            $name,
            array $args = [],
            ServiceClientInterface $client
        ) use ($description) {
            $operation = null;

            if ($description->hasOperation($name)) {
                $operation = $description->getOperation($name);
            } else {
                $name = ucfirst($name);
                if ($description->hasOperation($name)) {
                    $operation = $description->getOperation($name);
                }
            }

            if (!$operation) {
                return null;
            }

            return new Command($name, $args, ['emitter' => clone $client->getEmitter()]);
        };
    }

    /**
     * Prepares the client based on the configuration settings of the client.
     *
     * @param array $config Constructor config as an array
     */
    protected function processConfig(array $config)
    {
        // Use the passed in command factory or a custom factory if provided
        $this->commandFactory = isset($config['command_factory'])
            ? $config['command_factory']
            : self::defaultCommandFactory($this->description);

        // Add event listeners based on the configuration option
        $emitter = $this->getEmitter();

        if (!isset($config['validate']) ||
            $config['validate'] === true
        ) {
            $emitter->attach(new ValidateInput($this->description));
        }

        $this->serializer = isset($config['serializer'])
            ? $config['serializer']
            : new Serializer($this->description);

        if (!isset($config['process']) ||
            $config['process'] === true
        ) {
            $emitter->attach(
                new ProcessResponse(
                    $this->description,
                    isset($config['response_locations'])
                        ? $config['response_locations']
                        : []
                )
            );
        }
    }
}
