<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation
 */
class BodyLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $location = new BodyLocation('body');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
        $this->assertEquals('bar', $request->getBody());
    }
}
