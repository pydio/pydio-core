<?php
namespace GuzzleHttp\Command\Subscriber;

use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Queues mock results and/or exceptions and delivers them in a FIFO order.
 */
class ResultMock implements SubscriberInterface, \Countable
{
    /** @var array Array of mock results and exceptions */
    private $queue = [];

    /**
     * @param array $results Array of results and exceptions to queue
     */
    public function __construct(array $results = [])
    {
        $this->addMultiple($results);
    }

    public function getEvents()
    {
        return ['prepare' => ['onPrepared', 'last']];
    }

    /**
     * @throws \Exception if one has been queued.
     * @throws \OutOfBoundsException if the queue is empty.
     */
    public function onPrepared(PreparedEvent $event)
    {
        if (!$result = array_shift($this->queue)) {
            throw new \OutOfBoundsException('Result mock queue is empty');
        } elseif ($result instanceof \Exception) {
            throw $result;
        } else {
            $event->intercept($result);
        }
    }

    public function count()
    {
        return count($this->queue);
    }

    /**
     * Add a result to the end of the queue.
     *
     * @param mixed $result The result of the command.
     *
     * @return self
     */
    public function addResult($result)
    {
        $this->queue[] = $result;

        return $this;
    }

    /**
     * Add an exception to the end of the queue.
     *
     * @param \Exception $exception Thrown when executing.
     *
     * @return self
     */
    public function addException(\Exception $exception)
    {
        $this->queue[] = $exception;

        return $this;
    }

    /**
     * Add multiple results/exceptions to the queue
     *
     * @param array $results Results to add
     *
     * @return self
     */
    public function addMultiple(array $results)
    {
        foreach ($results as $result) {
            if ($result instanceof \Exception) {
                $this->addException($result);
            } else {
                $this->addResult($result);
            }
        }

        return $this;
    }

    /**
     * Clear the queue.
     *
     * @return self
     */
    public function clearQueue()
    {
        $this->queue = [];

        return $this;
    }
}
