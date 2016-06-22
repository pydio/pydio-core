<?php
/*
 * Copyright 2007-2016 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

use GuzzleHttp\Stream\StreamDecoratorTrait;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Auth\Core\AJXP_Safe;
use Pydio\Core\Model\ContextInterface;

class AuthStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var ContextInterface Context */
    private $context;

    public function __construct(
        StreamInterface $stream,
        AJXP_Node $node
    ) {
        $this->context = $node->getContext();

        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources($this->context);
        $user = $credentials["user"];
        $password = $credentials["password"];

        if ($user == "") {
            throw new \Exception("Cannot find user/pass for Remote Access!");
        }

        $auth = ["admin", "security", 'digest'];

        Stream::addContextOption($this->context, [
            "auth" => $auth
        ]);

        $resource = PydioStreamWrapper::getResource($stream);
        $this->stream = new Stream($resource, $node);
    }

    public function getContents() {
        return $this->stream->getContents();
    }
}