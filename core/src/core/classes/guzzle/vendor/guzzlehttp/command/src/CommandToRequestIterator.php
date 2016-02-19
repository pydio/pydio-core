<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Event\ListenerAttacherTrait;
use GuzzleHttp\Ring\Core;

/**
 * Iterator used for easily creating request objects from an iterator or array
 * that contains commands.
 *
 * This iterator is useful when implementing the
 * {@see ServiceClientInterface::executeAll()} method.
 */
class CommandToRequestIterator implements \Iterator
{
    use ListenerAttacherTrait;

    /** @var \Iterator */
    private $commands;

    /** @var callable request builder function */
    private $requestBuilder;

    /** @var RequestInterface|null Current request */
    private $currentRequest;

    /** @var array Listeners to attach to each command */
    private $eventListeners = [];

    /**
     * @param callable $requestBuilder A function that accepts a command and
     *       returns a hash containing a request key mapping to a request that
     *       has emitted it's prepare event, and a result key mapping to the
     *       result if one was injected in the prepare event.
     * @param array|\Iterator        $commands Collection of command objects
     * @param array                  $options  Hash of options:
     *     - prepare: Callable to invoke for the "prepare" event. This event is
     *       called only once per execution.
     *     - before: Callable to invoke when the "process" event is fired. This
     *       event is fired one or more times.
     *     - process: Callable to invoke when the "process" event is fired. This
     *       event can be fired one or more times.
     *     - error: Callable to invoke when the "error" event of a command is
     *       emitted. This event can be fired one or more times.
     *     - end: Callable to invoke when the terminal "end" event of a command
     *       is emitted. This event is fired once per command execution.
     *
     * @throws \InvalidArgumentException If the source is invalid
     */
    public function __construct(
        callable $requestBuilder,
        $commands,
        array $options = []
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->eventListeners = $this->prepareListeners(
            $options,
            ['init', 'prepared', 'process']
        );

        if ($commands instanceof \Iterator) {
            $this->commands = $commands;
        } elseif (is_array($commands)) {
            $this->commands = new \ArrayIterator($commands);
        } else {
            throw new \InvalidArgumentException('Command iterators must be '
                . 'created using an \\Iterator or array or commands');
        }
    }

    public function current()
    {
        return $this->currentRequest;
    }

    public function next()
    {
        $this->currentRequest = null;
        $this->commands->next();
    }

    public function key()
    {
        return $this->commands->key();
    }

    public function valid()
    {
        get_next:

        // Return true if this function has already been called for iteration.
        if ($this->currentRequest) {
            return true;
        }

        // Return false if we are at the end of the provided commands iterator.
        if (!$this->commands->valid()) {
            return false;
        }

        $command = $this->commands->current();

        if (!($command instanceof CommandInterface)) {
            throw new \RuntimeException('All commands provided to the ' . __CLASS__
                . ' must implement GuzzleHttp\\Command\\CommandInterface.'
                . ' Encountered a ' . Core::describeType($command) . ' value.');
        }

        $command->setFuture('lazy');
        $this->attachListeners($command, $this->eventListeners);

        // Prevent transfer exceptions from throwing.
        $command->getEmitter()->on(
            'process',
            function (ProcessEvent $e) {
                if ($e->getException()) {
                    $e->setResult(null);
                }
            },
            RequestEvents::LATE
        );

        $builder = $this->requestBuilder;
        $result = $builder($command);

        // Skip commands that were intercepted with a result.
        if (isset($result['result'])) {
            $this->commands->next();
            goto get_next;
        }

        $this->currentRequest = $result['request'];

        return true;
    }

    public function rewind()
    {
        $this->currentRequest = null;

        if (!($this->commands instanceof \Generator)) {
            $this->commands->rewind();
        }
    }
}
