<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Command;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\AbstractLocation
 */
class JsonLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $location = new JsonLocation('json');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
        $operation = new Operation([], new Description([]));
        $location->after($command, $request, $operation, []);
        $this->assertEquals('{"foo":"bar"}', $request->getBody());
        $this->assertEquals('application/json', $request->getHeader('Content-Type'));
    }

    public function testVisitsAdditionalProperties()
    {
        $location = new JsonLocation('json', 'foo');
        $command = new Command('foo', ['foo' => 'bar']);
        $command['baz'] = ['bam' => [1]];
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'json'
            ]
        ], new Description([]));
        $location->after($command, $request, $operation, []);
        $this->assertEquals('{"foo":"bar","baz":{"bam":[1]}}', $request->getBody());
        $this->assertEquals('foo', $request->getHeader('Content-Type'));
    }

    public function testVisitsNestedLocation()
    {
        $location = new JsonLocation('json');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter([
            'name' => 'foo',
            'type' => 'object',
            'properties' => [
                'baz' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'filters' => ['strtoupper']
                    ]
                ]
            ],
            'additionalProperties' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'filters' => ['strtolower']
                ]
            ]
        ]);
        $command['foo'] = [
            'baz' => ['a', 'b'],
            'bam' => ['A', 'B'],
        ];
        $location->visit($command, $request, $param, []);
        $operation = new Operation([], new Description([]));
        $location->after($command, $request, $operation, []);
        $this->assertEquals('{"foo":{"baz":["A","B"],"bam":["a","b"]}}', (string) $request->getBody());
        $this->assertEquals('application/json', $request->getHeader('Content-Type'));
    }
}
