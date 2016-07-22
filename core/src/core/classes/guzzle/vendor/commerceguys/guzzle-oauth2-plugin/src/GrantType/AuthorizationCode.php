<?php

namespace CommerceGuys\Guzzle\Oauth2\GrantType;

/**
 * Authorization code grant type.
 *
 * @link http://tools.ietf.org/html/rfc6749#section-4.1
 */
class AuthorizationCode extends GrantTypeBase
{
    protected $grantType = 'authorization_code';

    /**
     * @inheritdoc
     */
    protected function getDefaults()
    {
        return parent::getDefaults() + ['redirect_uri' => ''];
    }

    /**
     * @inheritdoc
     */
    protected function getRequired()
    {
        return array_merge(parent::getRequired(), ['code']);
    }
}
