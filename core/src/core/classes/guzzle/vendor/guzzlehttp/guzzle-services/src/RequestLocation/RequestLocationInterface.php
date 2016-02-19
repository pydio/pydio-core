<?php

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Message\RequestInterface;

/**
 * Handles locations specified in a service description
 */
interface RequestLocationInterface
{
    /**
     * Visits a location for each top-level parameter
     *
     * @param CommandInterface $command Command being prepared
     * @param RequestInterface $request Request being modified
     * @param Parameter        $param   Parameter being visited
     * @param array            $context Associative array containing a
     *     'client' key referencing the client that created the command.
     */
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param,
        array $context
    );

    /**
     * Called when all of the parameters of a command have been visited.
     *
     * @param CommandInterface $command   Command being prepared
     * @param RequestInterface $request   Request being modified
     * @param Operation        $operation Operation being serialized
     * @param array            $context   Associative array containing a
     *     'client' key referencing the client that created the command.
     */
    public function after(
        CommandInterface $command,
        RequestInterface $request,
        Operation $operation,
        array $context
    );
}
