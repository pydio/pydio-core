<?php
namespace GuzzleHttp\Tests\Command\Guzzle\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;

/**
 * @covers GuzzleHttp\Command\Guzzle\Subscriber\ProcessResponse
 */
class ProcessSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage 404
     */
    public function testDoesNotAddResultWhenExceptionIsPresent()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org/{foo}',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => [
                        'bar' => [
                            'type'     => 'string',
                            'required' => true,
                            'location' => 'uri'
                        ]
                    ]
                ]
            ]
        ]);

        $client = new GuzzleClient(new Client(), $description);
        $client->getHttpClient()->getEmitter()->attach(new Mock([
            new Response(404)
        ]));

        $client->foo(['bar' => 'baz']);
    }
}
