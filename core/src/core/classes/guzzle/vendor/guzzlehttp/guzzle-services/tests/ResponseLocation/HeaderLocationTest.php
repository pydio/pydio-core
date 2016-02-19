<?php
namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\HeaderLocation;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\HeaderLocation
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\AbstractLocation
 */
class HeaderLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new HeaderLocation('header');
        $command = new Command('foo', []);
        $parameter = new Parameter([
            'name'    => 'val',
            'sentAs'  => 'X-Foo',
            'filters' => ['strtoupper']
        ]);
        $response = new Response(200, ['X-Foo' => 'bar']);
        $result = [];
        $l->visit($command, $response, $parameter, $result);
        $this->assertEquals('BAR', $result['val']);
    }
}
