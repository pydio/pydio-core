<?php
namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\ReasonPhraseLocation;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\ReasonPhraseLocation
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\AbstractLocation
 */
class ReasonPhraseLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new ReasonPhraseLocation('reasonPhrase');
        $command = new Command('foo', []);
        $parameter = new Parameter([
            'name' => 'val',
            'filters' => ['strtolower']
        ]);
        $response = new Response(200);
        $result = [];
        $l->visit($command, $response, $parameter, $result);
        $this->assertEquals('ok', $result['val']);
    }
}
