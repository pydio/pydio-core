<?php

require_once("../websocket.client.php");
require_once("../websocket.admin.php");

/**
 * These tests need the 'demo.php' server to be running
 *
 * @author Chris
 *
 */
class TestServer extends PHPUnit_Framework_TestCase {

    function testMasking() {
        $input = "Hello World!";
        $frame = WebSocketFrame::create(WebSocketOpcode::TextFrame, $input);
        $frame->setMasked(true);



        $client = new WebSocket("ws://127.0.0.1:12345/echo/");
        $client->open();
        $client->sendFrame($frame);

        $msg = $client->readMessage();

        $client->close();
        $this->assertEquals($input, $frame->getData());
        $frames = $msg->getFrames();
        $this->assertEquals(false, $frames[0]->isMasked());
    }

    function test_echoResourceHandlerResponse() {
        $input = "Hello World!";
        $msg = WebSocketMessage::create($input);

        $client = new WebSocket("ws://127.0.0.1:12345/echo/");
        $client->open();
        $client->sendMessage($msg);

        $msg = $client->readMessage();

        $client->close();
        $this->assertEquals($input, $msg->getData());
        $frames = $msg->getFrames();
        $this->assertEquals(false, $frames[0]->isMasked());
    }

    function test_DoubleEchoResourceHandlerResponse() {
        $input = str_repeat("a", 1024);
        $input2 = str_repeat("b", 1024);
        $msg = WebSocketMessage::create($input);

        $client = new WebSocket("ws://127.0.0.1:12345/echo/");
        $client->setTimeOut(1000);
        $client->open();
        $client->sendMessage($msg);
        $client->sendMessage(WebSocketMessage::create($input2));

        $msg = $client->readMessage();
        $msg2 = $client->readMessage();

        $client->close();
        $this->assertEquals($input, $msg->getData());

        $this->assertEquals($input2, $msg2->getData());
    }

}