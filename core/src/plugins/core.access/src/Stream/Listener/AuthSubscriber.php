<?php
/**
 * Created by PhpStorm.
 * User: ghecquet
 * Date: 22/06/16
 * Time: 18:10
 */

namespace Pydio\Access\Core\Stream\Listener;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Custom authentication listener that handles the "foo" auth type.
 *
 * Listens to the "before" event of a request and only modifies the request
 * when the "auth" config setting of the request is "foo".
 */
class AuthSubscriber implements SubscriberInterface
{
    private $digest;

    /**
     * AuthSubscriber constructor.
     * @param $digest
     */
    public function __construct($digest)
    {
        $this->digest = $digest;
    }

    /**
     * @return array
     */
    public function getEvents()
    {
        return ['before' => ['sign', RequestEvents::SIGN_REQUEST]];
    }

    /**
     * @param BeforeEvent $e
     */
    public function sign(BeforeEvent $e)
    {
        //$e->getClient()->setDefaultOptions
    }
}