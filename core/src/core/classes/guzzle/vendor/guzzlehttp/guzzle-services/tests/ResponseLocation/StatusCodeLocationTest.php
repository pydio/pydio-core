<?php
namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\StatusCodeLocation;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\StatusCodeLocation
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\AbstractLocation
 */
class StatusCodeLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new StatusCodeLocation('statusCode');
        $command = new Command('foo', []);
        $parameter = new Parameter(['name' => 'val']);
        $response = new Response(200);
        $result = [];
        $l->visit($command, $response, $parameter, $result);
        $this->assertEquals(200, $result['val']);
    }
}
