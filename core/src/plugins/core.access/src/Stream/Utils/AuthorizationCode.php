<?php

namespace Pydio\Access\Core\Stream\Utils;

use CommerceGuys\Guzzle\Oauth2\GrantType\GrantTypeBase;

/**
 * Created by PhpStorm.
 * User: ghecquet
 * Date: 24/06/16
 * Time: 17:50
 */
class AuthorizationCode extends GrantTypeBase
{
    protected $grantType = 'authorization_code';

    /**
     * @inheritdoc
     */
    protected function getDefaults()
    {
        $defaults = parent::getDefaults() + ['redirect_uri' => ''];
        unset($defaults['scope']);

        return $defaults;
    }

    /**
     * @inheritdoc
     */
    protected function getRequired()
    {
        return array_merge(parent::getRequired(), ['code']);
    }
}