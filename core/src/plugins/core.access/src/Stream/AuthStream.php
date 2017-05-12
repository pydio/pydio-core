<?php
/*
 * Copyright 2007-2017 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

use GuzzleHttp\Stream\StreamDecoratorTrait;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\ContextInterface;

/**
 * Class AuthStream
 * @package Pydio\Access\Core\Stream
 */
class AuthStream implements StreamInterface {
    use StreamDecoratorTrait;

    /** @var ContextInterface Context */
    private $context;

    /**
     * AuthStream constructor.
     * @param StreamInterface $stream
     * @param AJXP_Node $node
     */
    public function __construct(
        StreamInterface $stream,
        AJXP_Node $node
    ) {
        $this->context = $node->getContext();

        $credentials = MemorySafe::tryLoadingCredentialsFromSources($this->context);
        $user = $credentials["user"];
        $password = $credentials["password"];

        if ($user == "") {
            throw new \Exception("Cannot find user/pass for Remote Access!");
        }

        $authScheme = Stream::getContextOption($this->context, "authScheme", "basic");

        $auth = [$user, $password, $authScheme];

        Stream::addContextOption($this->context, [
            "auth" => $auth
        ]);

        $this->stream = $stream;
    }

    /**
     * @return string
     */
    public function getContents() {
        return $this->stream->getContents();
    }
}