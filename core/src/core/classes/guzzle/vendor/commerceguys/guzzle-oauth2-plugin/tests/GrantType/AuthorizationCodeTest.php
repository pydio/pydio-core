<?php

namespace CommerceGuys\Guzzle\Oauth2\Tests\GrantType;

use CommerceGuys\Guzzle\Oauth2\GrantType\AuthorizationCode;
use CommerceGuys\Guzzle\Oauth2\Tests\TestBase;

class AuthorizationCodeTest extends TestBase
{
    public function testMissingConfigException()
    {
        $this->setExpectedException('\\InvalidArgumentException', 'Config is missing the following keys: client_id, code');
        new AuthorizationCode($this->getClient());
    }
}
