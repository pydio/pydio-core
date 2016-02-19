<?php
namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;

/**
 * Event emitted when the command has finished executing the underlying
 * request. This event is emitted to process the the result of a command.
 *
 * This event is emitted when there is an exception or when the underlying
 * request succeeded. You'll need to account for both cases when listening
 * to the process event.
 */
class ProcessEvent extends CommandEvent
{
    /**
     * Returns an exception if one was encountered.
     *
     * @return \Exception|null
     */
    public function getException()
    {
        return $this->trans->exception;
    }

    /**
     * Gets the HTTP request that was sent.
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->trans->request;
    }

    /**
     * Get the response that was received for the request if one was received.
     *
     * It is important to remember that a response will not be available if the
     * HTTP request failed with a networking error.
     *
     * @return ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->trans->response;
    }

    /**
     * Set the processed result on the event.
     *
     * Subsequent listeners ARE STILL emitted even when a result is set.
     * Calling this method will remove any exceptions associated with the
     * command.
     *
     * @param mixed $result Result to associate with the command
     */
    public function setResult($result)
    {
        $this->trans->exception = null;
        $this->trans->result = $result;
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
