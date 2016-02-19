<?php
namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Event\AbstractEvent;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Collection;

/**
 * Base command event that is emitted.
 */
class CommandEvent extends AbstractEvent
{
    /** @var CommandTransaction */
    protected $trans;

    /**
     * @param CommandTransaction $trans Command transaction
     */
    public function __construct(CommandTransaction $trans)
    {
        $this->trans = $trans;
    }

    /**
     * Get the command associated with the event
     *
     * @return CommandInterface
     */
    public function getCommand()
    {
        return $this->trans->command;
    }

    /**
     * Get the service client associated with the command transfer.
     *
     * @return ServiceClientInterface
     */
    public function getClient()
    {
        return $this->trans->serviceClient;
    }

    /**
     * Get context associated with the command transfer.
     *
     * The return value is a Guzzle collection object which can be accessed and
     * mutated like a PHP associative array. You can add arbitrary data to the
     * context for application specific purposes.
     *
     * @return Collection
     */
    public function getContext()
    {
        return $this->trans->context;
    }

    /**
     * Gets the transaction associated with the event
     *
     * @return CommandTransaction
     */
    public function getTransaction()
    {
        return $this->trans;
    }
}
