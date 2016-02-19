<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Event\BeforeEvent;

/**
 * @covers \GuzzleHttp\Command\Guzzle\GuzzleClient
 */
class GuzzleClientTest extends \PHPUnit_Framework_TestCase
{
    public function testHasConfig()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'foo' => 'bar',
            'baz' => ['bam' => 'boo']
        ]);
        $this->assertSame($client, $guzzle->getHttpClient());
        $this->assertSame($description, $guzzle->getDescription());
        $this->assertEquals('bar', $guzzle->getConfig('foo'));
        $this->assertEquals('boo', $guzzle->getConfig('baz/bam'));
        $this->assertEquals([], $guzzle->getConfig('defaults'));
        $guzzle->setConfig('abc/123', 'listen');
        $this->assertEquals('listen', $guzzle->getConfig('abc/123'));
        $this->assertCount(1, $guzzle->getEmitter()->listeners('process'));
    }

    public function testAddsSubscribersWhenTrue()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'validate' => true,
            'process' => true
        ]);
        $this->assertCount(1, $guzzle->getEmitter()->listeners('process'));
    }

    public function testDisablesSubscribersWhenFalse()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'validate' => false,
            'process' => false
        ]);
        $this->assertCount(0, $guzzle->getEmitter()->listeners('process'));
    }

    public function testCanUseCustomConfigFactory()
    {
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\Command')
            ->disableOriginalConstructor()
            ->getMock();
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'command_factory' => function () use ($mock) {
                $this->assertCount(3, func_get_args());
                return $mock;
            }
        ]);
        $this->assertSame($mock, $guzzle->getCommand('foo'));
    }

    public function testMagicMethodExecutesCommands()
    {
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\Command')
            ->setConstructorArgs(['foo'])
            ->getMock();
        $client = new Client();
        $description = new Description([]);
        $guzzle = $this->getMockBuilder('GuzzleHttp\\Command\\Guzzle\\GuzzleClient')
            ->setConstructorArgs([
                $client, $description, [
                    'command_factory' => function ($name) use ($mock) {
                        $this->assertEquals('foo', $name);
                        $this->assertCount(3, func_get_args());
                        return $mock;
                    }
                ]
            ])
            ->setMethods(['execute'])
            ->getMock();
        $guzzle->expects($this->once())
            ->method('execute')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $guzzle->foo([]));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage No operation found named foo
     */
    public function testThrowsWhenFactoryReturnsNull()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description);
        $guzzle->getCommand('foo');
    }

    public function testDefaultFactoryChecksWithUppercaseToo()
    {
        $description = new Description([
            'operations' => ['Foo' => [], 'bar' => []]
        ]);
        $c = new GuzzleClient(new Client(), $description);
        $f = GuzzleClient::defaultCommandFactory($description);
        $command1 = $f('foo', [], $c);
        $this->assertInstanceOf('GuzzleHttp\\Command\\Command', $command1);
        $this->assertEquals('Foo', $command1->getName());
        $command2 = $f('Foo', [], $c);
        $this->assertInstanceOf('GuzzleHttp\\Command\\Command', $command2);
        $this->assertEquals('Foo', $command2->getName());
    }

    public function testReturnsProcessedResponse()
    {
        $client = new Client();
        $client->getEmitter()->on('before', function (BeforeEvent $event) {
            $event->intercept(new Response(201));
        });
        $description = new Description([
            'operations' => [
                'Foo' => ['responseModel' => 'Bar']
            ],
            'models' => [
                'Bar' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['location' => 'statusCode']
                    ]
                ]
            ]
        ]);
        $guzzle = new GuzzleClient($client, $description);
        $command = $guzzle->getCommand('foo');
        $result = $guzzle->execute($command);
        $this->assertInternalType('array', $result);
        $this->assertEquals(201, $result['code']);
    }
}
