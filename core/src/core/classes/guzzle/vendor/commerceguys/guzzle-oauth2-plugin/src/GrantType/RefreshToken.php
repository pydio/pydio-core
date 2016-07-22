<?php

namespace CommerceGuys\Guzzle\Oauth2\GrantType;

/**
 * Refresh token grant type.
 *
 * @link http://tools.ietf.org/html/rfc6749#section-6
 */
class RefreshToken extends GrantTypeBase implements RefreshTokenGrantTypeInterface
{
    protected $grantType = 'refresh_token';

    /**
     * @inheritdoc
     */
    protected function getDefaults()
    {
        return parent::getDefaults() + ['refresh_token' => ''];
    }

    /**
     * @inheritdoc
     */
    public function setRefreshToken($refreshToken)
    {
        $this->config['refresh_token'] = $refreshToken;
    }

    /**
     * @inheritdoc
     */
    public function hasRefreshToken()
    {
        return !empty($this->config['refresh_token']);
    }

    /**
     * @inheritdoc
     */
    public function getToken()
    {
        if (!$this->hasRefreshToken()) {
            throw new \RuntimeException("Refresh token not available");
        }

        return parent::getToken();
    }
}
