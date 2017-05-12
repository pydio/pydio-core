<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Access\Core\Stream\Exception;

use Pydio\Core\Exception\PydioException;
//use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class OAuthException - Extend exception to trigger an oauth  error
 * if workspace requires an oauth and it cannot be found.
 * @package Pydio\Access\Core\Stream\Exception;
 */
class OAuthException extends PydioException
{

    private $url;

    /**
     * OAuthException constructor.
     * @param string $workspaceId
     * @param boolean $requireLogin
     * @param string $message
     */
    public function __construct($message, $url)
    {
        $this->message = $message;
        $this->url = $url;

        /*if(empty($message)){
            $message = LocaleService::getMessages()['559'];
        }*/

        parent::__construct($message, false, null);
    }

    /**
     * @return bool
     */
    public function getURL(){
        return $this->url;
    }
}
