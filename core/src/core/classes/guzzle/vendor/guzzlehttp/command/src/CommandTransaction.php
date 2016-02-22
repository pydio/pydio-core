<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Collection;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\ClientInterface;

/**
 * Represents a command transaction as it is sent over the wire and inspected
 * by event listeners.
 */
class CommandTransaction
{
    /**
     * Web service client used in the transaction
     *
     * @var ServiceClientInterface
     */
    public $serviceClient;

    /**
     * The command being executed.
     *
     * @var CommandInterface
     */
    public $command;

    /**
     * The result of the command (if available)
     *
     * @var mixed|null
     */
    public $result;

    /**
     * The exception that was received while transferring (if any).
     *
     * @var CommandException
     */
    public $exception;

    /**
     * Contains contextual information about the transaction.
     *
     * The information added to this collection can be anything required to
     * implement a command abstraction.
     *
     * @var Collection
     */
    public $context;

    /**
     * HTTP client used to transfer the request.
     *
     * @var ClientInterface
     */
    public $client;

    /**
     * The request that is being sent.
     *
     * @var RequestInterface
     */
    public $request;

    /**
     * The response associated with the transaction. A response will not be
     * present when a networking error occurs or an error occurs before sending
     * the request.
     *
     * @var ResponseInterface|null
     */
    public $response;

    /**
     * The transaction's state.
     *
     * @var string
     */
    public $state;

    /**
     * @param ServiceClientInterface $client  Client that executes commands.
     * @param CommandInterface       $command Command being executed.
     * @param array                  $context Command context array of data.
     */
    public function __construct(
        ServiceClientInterface $client,
        CommandInterface $command,
        array $context = []
    ) {
        $this->serviceClient = $client;
        $this->client = $client->getHttpClient();
        $this->command = $command;
        $this->context = new Collection($context);
        $this->state = 'init';
    }
}
