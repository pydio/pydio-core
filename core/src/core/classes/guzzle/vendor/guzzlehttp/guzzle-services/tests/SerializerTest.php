<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Serializer;

class SerializerTest extends \PHPUnit_Framework_TestCase
{
    public function testAllowsUriTemplates()
    {
        $description = new Description([
            'baseUrl' => 'http://test.com',
            'operations' => [
                'test' => [
                    'httpMethod'         => 'GET',
                    'uri'                => '/api/{key}/foo',
                    'parameters'         => [
                        'key' => [
                            'required'  => true,
                            'type'      => 'string',
                            'location'  => 'uri'
                        ],
                    ]
                ]
            ]
        ]);

        $client = new Client();
        $guzzle = new GuzzleClient($client, $description);
        $command = new Command('test', ['key' => 'bar']);
        $trans = new CommandTransaction($guzzle, $command);
        $s = new Serializer($description);
        $request = $s($trans);
        $this->assertEquals('http://test.com/api/bar/foo', $request->getUrl());
    }
}
