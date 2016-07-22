<?php

namespace CommerceGuys\Guzzle\Oauth2;

use CommerceGuys\Guzzle\Oauth2\GrantType\GrantTypeInterface;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshTokenGrantTypeInterface;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;

class Oauth2Subscriber implements SubscriberInterface
{

    /** @var AccessToken|null */
    protected $accessToken;
    /** @var AccessToken|null */
    protected $refreshToken;

    /** @var GrantTypeInterface */
    protected $grantType;
    /** @var RefreshTokenGrantTypeInterface */
    protected $refreshTokenGrantType;

    /**
     * Create a new Oauth2 subscriber.
     *
     * @param GrantTypeInterface             $grantType
     * @param RefreshTokenGrantTypeInterface $refreshTokenGrantType
     */
    public function __construct(GrantTypeInterface $grantType = null, RefreshTokenGrantTypeInterface $refreshTokenGrantType = null)
    {
        $this->grantType = $grantType;
        $this->refreshTokenGrantType = $refreshTokenGrantType;
    }

    /**
     * @inheritdoc
     */
    public function getEvents()
    {
        return [
            'before' => ['onBefore', RequestEvents::SIGN_REQUEST],
            'error' => ['onError', RequestEvents::EARLY],
        ];
    }

    /**
     * @inheritdoc
     */
    public function onError(ErrorEvent $event)
    {
        $response = $event->getResponse();
        if ($response && 401 == $response->getStatusCode()) {
            $request = $event->getRequest();
            if ($request->getConfig()->get('auth') == 'oauth2' && !$request->getConfig()->get('retried')) {
                if ($token = $this->acquireAccessToken()) {
                    $this->accessToken = $token;
                    $this->refreshToken = $token->getRefreshToken();
                    $request->getConfig()->set('retried', true);
                    $event->intercept($event->getClient()->send($request));
                }
            }
        }
    }

    /**
     * Get a new access token.
     *
     * @return AccessToken|null
     */
    protected function acquireAccessToken()
    {
        $accessToken = null;

        if ($this->refreshTokenGrantType) {
            // Get an access token using the stored refresh token.
            if ($this->refreshToken) {
                $this->refreshTokenGrantType->setRefreshToken($this->refreshToken->getToken());
            }
            if ($this->refreshTokenGrantType->hasRefreshToken()) {
                $accessToken = $this->refreshTokenGrantType->getToken();
            }
        }

        if (!$accessToken && $this->grantType) {
            // Get a new access token.
            $accessToken = $this->grantType->getToken();
        }

        return $accessToken ?: null;
    }

    /**
     * Add the Authorization header to requests.
     *
     * @param BeforeEvent $event Event received
     */
    public function onBefore(BeforeEvent $event)
    {
        $request = $event->getRequest();
        if ($request->getConfig()->get('auth') == 'oauth2') {
            $token = $this->getAccessToken();
            if ($token !== null) {
                $request->setHeader('Authorization', 'Bearer ' . $token->getToken());
            }
        }
    }

    /**
     * Get the access token.
     *
     * @return AccessToken|null Oauth2 access token
     */
    public function getAccessToken()
    {
        if ($this->accessToken && $this->accessToken->isExpired()) {
            // The access token has expired.
            $this->accessToken = null;
        }

        if (null === $this->accessToken) {
            // Try to acquire a new access token from the server.
            $this->accessToken = $this->acquireAccessToken();
            if ($this->accessToken) {
                $this->refreshToken = $this->accessToken->getRefreshToken();
            }
        }

        return $this->accessToken;
    }

    /**
     * Get the refresh token.
     *
     * @return AccessToken|null
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * Set the access token.
     *
     * @param AccessToken|string $accessToken
     * @param string             $type
     * @param int                $expires
     */
    public function setAccessToken($accessToken, $type = null, $expires = null)
    {
        if (is_string($accessToken)) {
            $accessToken = new AccessToken($accessToken, $type, ['expires' => $expires]);
        } elseif (!$accessToken instanceof AccessToken) {
            throw new \InvalidArgumentException('Invalid access token');
        }
        $this->accessToken = $accessToken;
        $this->refreshToken = $accessToken->getRefreshToken();
    }

    /**
     * Set the refresh token.
     *
     * @param AccessToken|string $refreshToken The refresh token
     */
    public function setRefreshToken($refreshToken)
    {
        if (is_string($refreshToken)) {
            $refreshToken = new AccessToken($refreshToken, 'refresh_token');
        } elseif (!$refreshToken instanceof AccessToken) {
            throw new \InvalidArgumentException('Invalid refresh token');
        }
        $this->refreshToken = $refreshToken;
    }
}
