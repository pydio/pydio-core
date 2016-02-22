<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Event\HasEmitterInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Ring\FutureInterface;

/**
 * Web service client interface.
 *
 * Any event listener or subscriber added to the client is added to each
 * command created by the client when the command is created.
 */
interface ServiceClientInterface extends HasEmitterInterface
{
    /**
     * Creates and executes a command for an operation by name.
     *
     * @param string $name      Name of the command to execute.
     * @param array  $arguments Arguments to pass to the getCommand method.
     *
     * @throws \Exception
     * @see \GuzzleHttp\Command\ServiceClientInterface::getCommand
     */
    public function __call($name, array $arguments);

    /**
     * Create a command for an operation name.
     *
     * Special keys may be set on the command to control how it behaves.
     * Implementations SHOULD be able to utilize the following keys or throw
     * an exception if unable.
     *
     * - @future: Set to true to create a future if possible. When processed,
     *   the "@future" key value pair can be removed from the input data before
     *   serializing the command.
     *
     * @param string $name   Name of the operation to use in the command
     * @param array  $args   Arguments to pass to the command
     *
     * @return CommandInterface
     * @throws \InvalidArgumentException if no command can be found by name
     */
    public function getCommand($name, array $args = []);

    /**
     * Execute a single command.
     *
     * @param CommandInterface $command Command to execute
     *
     * @return mixed Returns the result of the executed command
     * @throws \Exception
     */
    public function execute(CommandInterface $command);

    /**
     * Executes many commands concurrently using a fixed pool size.
     *
     * Exceptions encountered while executing the commands will not be thrown.
     * Instead, callers are expected to handle errors using the event system.
     *
     *     $commands = [$client->getCommand('foo', ['baz' => 'bar'])];
     *     $client->executeAll($commands);
     *
     * @param array|\Iterator $commands Array or iterator that contains
     *     CommandInterface objects to execute.
     * @param array $options Associative array of options to apply.
     * @see GuzzleHttp\Command\ServiceClientInterface::createCommandPool for
     *      a list of options.
     */
    public function executeAll($commands, array $options = []);

    /**
     * Creates a future object that, when dereferenced, sends commands in
     * parallel using a fixed pool size.
     *
     * Exceptions encountered while executing the commands will not be thrown.
     * Instead, callers are expected to handle errors using the event system.
     *
     * @param array|\Iterator $commands Array or iterator that contains
     *     CommandInterface objects to execute with the client.
     * @param array $options Associative array of options to apply.
     *     - pool_size: (int) Max number of commands to send concurrently.
     *       When this number of concurrent requests are created, the sendAll
     *       function blocks until all of the futures have completed.
     *     - init: (callable) Receives an InitEvent from each command.
     *     - prepare: (callable) Receives a PrepareEvent from each command.
     *     - process: (callable) Receives a ProcessEvent from each command.
     *
     * @return FutureInterface
     */
    public function createPool($commands, array $options = []);

    /**
     * Get the HTTP client used to send requests for the web service client
     *
     * @return ClientInterface
     */
    public function getHttpClient();

    /**
     * Get a client configuration value.
     *
     * @param string|int|null $keyOrPath The Path to a particular configuration
     *     value. The syntax uses a path notation that allows you to retrieve
     *     nested array values without throwing warnings.
     *
     * @return mixed
     */
    public function getConfig($keyOrPath = null);

    /**
     * Set a client configuration value at the specified configuration path.
     *
     * @param string|int $keyOrPath Path at which to change a configuration
     *     value. This path syntax follows the same path syntax specified in
     *     {@see getConfig}.
     *
     * @param mixed $value Value to set
     */
    public function setConfig($keyOrPath, $value);

    /**
     * Create an exception for a command based on a previous exception.
     *
     * This method is invoked when an exception occurs while executing a
     * command. You may choose to use a custom exception class for exceptions
     * or return the provided exception as-is.
     *
     * @param CommandTransaction $transaction Command transaction context
     *
     * @return \Exception
     */
    public function createCommandException(CommandTransaction $transaction);
}
