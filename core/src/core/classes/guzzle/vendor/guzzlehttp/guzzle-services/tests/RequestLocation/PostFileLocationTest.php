<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFileLocation;
use GuzzleHttp\Command\Command;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\PostFileLocation
 */
class PostFileLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $location = new PostFileLocation('postFile');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org', [], new PostBody());
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
        $this->assertEquals(
            'bar',
            $request->getBody()->getFile('foo')->getContent()
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testValidatesBodyIsPost()
    {
        $location = new PostFileLocation('postFile');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
    }
}
