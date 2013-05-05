<?php

class WebSocketMessageNotFinalised extends Exception {

    public function __construct(IWebSocketMessage $msg) {
        parent::__construct("WebSocketMessage is not finalised!");
    }

}

class WebSocketFrameSizeMismatch extends Exception {

    public function __construct(IWebSocketFrame $msg) {
        parent::__construct("Frame size mismatches with the expected frame size. Maybe a buggy client.");
    }

}

class WebSocketInvalidChallengeResponse extends Exception {

    public function __construct() {
        parent::__construct("Server send an incorrect response to the clients challenge!");
    }

}

class WebSocketInvalidUrlScheme extends Exception {

    public function __construct() {
        parent::__construct("Only 'ws://' urls are supported!");
    }

}

class WebSocketNotAuthorizedException extends Exception {

    protected $user;

    public function __construct(IWebSocketUser $user) {
        parent::__construct("None or invalid credentials provided!");
        $this->user = $user;
    }

}

class WebSocketInvalidKeyException extends Exception {

    public function __construct($key1, $key2, $l8b) {
        parent::__construct("Client sent an invalid opening handshake!");
        fwrite(STDERR, "Key 1: \t$key1\nKey 2: \t$key2\nL8b: \t$l8b");
    }

}