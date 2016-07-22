<?php

namespace CommerceGuys\Guzzle\Oauth2\Tests;

use CommerceGuys\Guzzle\Oauth2\AccessToken;

class AccessTokenTest extends \PHPUnit_Framework_TestCase
{
    public function testAccessTokenGetters()
    {
        $data = [
            'access_token' => 'testToken',
            'token_type' => 'bearer',
            'expires_in' => 300,
            'scope' => 'profile administration',
            'refresh_token' => 'testRefreshToken',
        ];
        $token = new AccessToken($data['access_token'], $data['token_type'], $data);
        $this->assertEquals($data['access_token'], $token->getToken());
        $this->assertEquals($data['token_type'], $token->getType());
        $this->assertEquals($data['scope'], $token->getScope());
        $this->assertGreaterThan(time(), $token->getExpires()->getTimestamp());
        $this->assertFalse($token->isExpired());
        $this->assertEquals($data, $token->getData());
        $this->assertEquals('refresh_token', $token->getRefreshToken()->getType());
        $this->assertEquals($data['refresh_token'], $token->getRefreshToken()->getToken());
    }

    public function testAccessTokenSetExpiresDirect()
    {
        $token = new AccessToken('testToken', 'bearer', ['expires' => 500]);
        $this->assertTrue($token->isExpired());

        $token = new AccessToken('testToken', 'bearer', ['expires' => time() + 500]);
        $this->assertFalse($token->isExpired());
    }
}
