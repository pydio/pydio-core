<?php

namespace CommerceGuys\Guzzle\Oauth2\Tests\GrantType;

use CommerceGuys\Guzzle\Oauth2\GrantType\ClientCredentials;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use CommerceGuys\Guzzle\Oauth2\Tests\TestBase;

class OAuth2SubscriberTest extends TestBase
{
    public function testSubscriberRetriesRequestOn401()
    {
        $subscriber = new Oauth2Subscriber(new ClientCredentials($this->getClient(), [
            'client_id' => 'test',
            'client_secret' => 'testSecret',
        ]));
        $client = $this->getClient([
            'defaults' => [
                'subscribers' => [$subscriber],
                'auth' => 'oauth2',
            ],
        ]);
        $response = $client->get('api/collection');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSubscriberUsesRefreshToken()
    {
        $credentials = [
            'client_id' => 'test',
            'client_secret' => 'testSecret',
        ];

        $accessTokenGrantType = new ClientCredentials(
            $this->getClient([], ['tokenExpires' => 0]),
            $credentials
        );

        $subscriber = new Oauth2Subscriber(
            $accessTokenGrantType,
            new RefreshToken($this->getClient(), $credentials)
        );
        $subscriber->setRefreshToken('testRefreshToken');
        $client = $this->getClient([
            'defaults' => [
                'subscribers' => [$subscriber],
                'auth' => 'oauth2',
            ],
        ]);

        // Initially, the access token should be expired. After the first API
        // call, the subscriber will use the refresh token to get a new access
        // token.
        $this->assertTrue($accessTokenGrantType->getToken()->isExpired());

        $response = $client->get('api/collection');

        // Now, the access token should be valid.
        $this->assertFalse($subscriber->getAccessToken()->isExpired());
        $this->assertEquals(200, $response->getStatusCode());

        // Also, the refresh token should have changed.
        $newRefreshToken = $subscriber->getRefreshToken();
        $this->assertEquals('testRefreshTokenFromServer', $newRefreshToken->getToken());
    }

    public function testNewRefreshTokenStoredAfterError()
    {
        $credentials = [
            'client_id' => 'test',
            'client_secret' => 'testSecret',
        ];

        $accessTokenGrantType = new ClientCredentials($this->getClient(), $credentials);

        $subscriber = new Oauth2Subscriber(
            $accessTokenGrantType,
            new RefreshToken($this->getClient(), $credentials)
        );

        // Use a access token that isn't expired on the client side, but
        // the server thinks is expired. This should trigger the onError event
        // in the subscriber, forcing it to try the refresh token grant type.
        $subscriber->setAccessToken('testInvalidAccessToken');
        $subscriber->setRefreshToken('testRefreshToken');
        $client = $this->getClient([
            'defaults' => [
                'subscribers' => [$subscriber],
                'auth' => 'oauth2',
            ],
        ]);

        $response = $client->get('api/collection');

        // Now, the access token should be valid.
        $this->assertFalse($subscriber->getAccessToken()->isExpired());
        $this->assertEquals(200, $response->getStatusCode());

        // Also, the refresh token should have changed.
        $newRefreshToken = $subscriber->getRefreshToken();
        $this->assertEquals('testRefreshTokenFromServer', $newRefreshToken->getToken());
    }

    public function testSettingManualAccessToken()
    {
        $subscriber = new Oauth2Subscriber();
        $client = $this->getClient([
            'defaults' => [
                'subscribers' => [$subscriber],
                'auth' => 'oauth2',
                'exceptions' => false,
            ],
        ]);

        // Set a valid token.
        $subscriber->setAccessToken('testToken');
        $this->assertEquals($subscriber->getAccessToken()->getToken(), 'testToken');
        $this->assertFalse($subscriber->getAccessToken()->isExpired());
        $response = $client->get('api/collection');
        $this->assertEquals(200, $response->getStatusCode());

        // Set an invalid token.
        $subscriber->setAccessToken('testInvalidToken');
        $response = $client->get('api/collection');
        $this->assertEquals(401, $response->getStatusCode());

        // Set an expired token.
        $subscriber->setAccessToken('testToken', 'bearer', 500);
        $this->assertNull($subscriber->getAccessToken());
    }

    public function testSettingManualRefreshToken()
    {
        $subscriber = new Oauth2Subscriber();
        $subscriber->setRefreshToken('testRefreshToken');
        $this->assertEquals('refresh_token', $subscriber->getRefreshToken()->getType());
        $this->assertEquals('testRefreshToken', $subscriber->getRefreshToken()->getToken());
    }
}
