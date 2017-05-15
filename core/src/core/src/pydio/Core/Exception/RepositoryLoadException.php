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
namespace Pydio\Core\Exception;

use Pydio\Core\Model\RepositoryInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class RepositoryLoadException
 * @package Pydio\Core\Exception
 */
class RepositoryLoadException extends PydioException
{
    /**
     * @var RepositoryInterface
     */
    private $repository;
    /**
     * RepositoryLoadException constructor.
     * @param RepositoryInterface|String $workspace
     * @param array $errors
     */
    public function __construct($workspace, $errors)
    {
        if($workspace instanceof RepositoryInterface){
            $message = "Error while loading workspace ".$workspace->getDisplay()." : ".implode("\n-", $errors);
            $this->repository = $workspace;
        }else{
            $message = "Error while loading workspace ".$workspace." : ".implode("\n-", $errors);
        }
        parent::__construct($message, false, 5000);
    }

    /**
     * @return RepositoryInterface|String
     */
    public function getRepository(){
        return $this->repository;
    }
}