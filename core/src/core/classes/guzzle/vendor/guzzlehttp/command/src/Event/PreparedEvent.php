<?php
namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Exception\StateException;

/**
 * Event emitted when a command is being prepared.
 *
 * Event listeners can use this event to modify the request that was created
 * by the client, and to intercept the event to prevent HTTP requests from
 * being sent over the wire.
 *
 * This event provides a good way for a listener to hook into the HTTP level
 * event system.
 */
class PreparedEvent extends CommandEvent
{
    /**
     * @param CommandTransaction $trans Command transaction
     * @throws StateException
     */
    public function __construct(CommandTransaction $trans)
    {
        if (!$trans->request) {
            throw new StateException('No request on the command transaction');
        }

        $this->trans = $trans;
    }

    /**
     * Gets the HTTP request that will be sent for the command.
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->trans->request;
    }

    /**
     * Set a result on the command transaction to prevent the command from
     * actually sending an HTTP request.
     *
     * Subsequent listeners ARE NOT emitted even when a result is set in the
     * prepare event.
     *
     * @param mixed $result Result to associate with the command
     */
    public function intercept($result)
    {
        $this->trans->exception = null;
        $this->trans->result = $result;
        $this->stopPropagation();
    }

    /**
     * Returns the result of the command (if one is available).
     *
     * @return mixed|null
     */
    public function getResult()
    {
        return $this->trans->result;
    }
}
