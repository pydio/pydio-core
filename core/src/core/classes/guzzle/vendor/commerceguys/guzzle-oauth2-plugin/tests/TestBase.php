<?php

namespace CommerceGuys\Guzzle\Oauth2\Tests;

use GuzzleHttp\Client;

abstract class TestBase extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $options
     * @param array $serverOptions
     *
     * @return \GuzzleHttp\ClientInterface
     */
    protected function getClient(array $options = [], array $serverOptions = [])
    {
        $server = new MockOAuth2Server($serverOptions);
        return new Client([
                'handler' => $server->getHandler()
            ] + $options);
    }
}
