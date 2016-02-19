<?php
namespace GuzzleHttp\Tests\Command\Guzzle\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Subscriber\ValidateInput;
use GuzzleHttp\Command\Event\InitEvent;

/**
 * @covers GuzzleHttp\Command\Guzzle\Subscriber\ValidateInput
 */
class ValidateInputTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage Validation errors: [bar] is a required string
     */
    public function testValidates()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => [
                        'bar' => [
                            'type'     => 'string',
                            'required' => true
                        ]
                    ]
                ]
            ]
        ]);

        $client = new GuzzleClient(new Client(), $description);
        $val = new ValidateInput($description);
        $event = new InitEvent(
            new CommandTransaction(
                $client,
                $client->getCommand('foo')
            )
        );
        $val->onInit($event);
    }

    public function testSuccessfulValidationDoesNotThrow()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => []
                ]
            ]
        ]);

        $client = new GuzzleClient(new Client(), $description);
        $val = new ValidateInput($description);
        $event = new InitEvent(
            new CommandTransaction(
                $client,
                $client->getCommand('foo')
            )
        );
        $val->onInit($event);
    }

    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage Validation errors: [bar] must be of type string
     */
    public function testValidatesAdditionalParameters()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'additionalParameters' => [
                        'type'     => 'string'
                    ]
                ]
            ]
        ]);

        $client = new GuzzleClient(new Client(), $description);
        $val = new ValidateInput($description);
        $event = new InitEvent(
            new CommandTransaction(
                $client,
                $client->getCommand('foo', ['bar' => new \stdClass()])
            )
        );
        $val->onInit($event);
    }
}
