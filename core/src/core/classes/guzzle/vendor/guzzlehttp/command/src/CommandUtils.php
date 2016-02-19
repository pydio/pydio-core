<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\BatchResults;

/**
 * Provides useful functions for interacting with web service clients.
 */
class CommandUtils
{
    /**
     * Sends multiple commands concurrently and returns a hash map of commands
     * mapped to their corresponding result or exception.
     *
     * Note: This method keeps every command and command and result in memory,
     * and as such is NOT recommended when sending a large number or an
     * indeterminable number of commands concurrently. Instead, you should use
     * executeAll() and utilize the event system to work with results.
     *
     * @param ServiceClientInterface $client
     * @param array|\Iterator        $commands Commands to send.
     * @param array                  $options  Passes through the options available
     *                                         in {@see ServiceClientInterface::createPool()}
     *
     * @return BatchResults
     * @throws \InvalidArgumentException if the event format is incorrect.
     */
    public static function batch(
        ServiceClientInterface $client,
        $commands,
        array $options = []
    ) {
        $hash = new \SplObjectStorage();
        foreach ($commands as $command) {
            $hash->attach($command);
        }

        $client->executeAll($commands, RequestEvents::convertEventArray(
            $options,
            ['process'],
            [
                'priority' => RequestEvents::LATE,
                'fn'       => function (ProcessEvent $e) use ($hash) {
                    if ($e->getException()) {
                        $hash[$e->getCommand()] = $e->getException();
                    } else {
                        $hash[$e->getCommand()] = $e->getResult();
                    }
                }
            ]
        ));

        return new BatchResults($hash);
    }
}
