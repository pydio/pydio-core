<?php
/**
 * Created by PhpStorm.
 * User: ghecquet
 * Date: 21/09/16
 * Time: 14:01
 */

namespace Pydio\Core\Exception;


class RouteNotFoundException extends PydioException
{
    public function __construct()
    {
        parent::__construct("Could not find route", null);

        $this->code = 404;
    }
}