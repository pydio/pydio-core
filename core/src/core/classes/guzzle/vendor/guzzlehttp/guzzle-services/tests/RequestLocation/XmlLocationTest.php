<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Command\Command;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation
 */
class XmlLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $location = new XmlLocation('xml');
        $command = new Command('foo', ['foo' => 'bar']);
        $command['bar'] = 'test';
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
        $param = new Parameter(['name' => 'bar']);
        $location->visit($command, $request, $param, []);
        $operation = new Operation([], new Description([]));
        $location->after($command, $request, $operation, []);
        $xml = (string) $request->getBody();
        $this->assertEquals('<?xml version="1.0"?>' . "\n"
            . '<Request><foo>bar</foo><bar>test</bar></Request>' . "\n", $xml);
        $this->assertEquals('application/xml', $request->getHeader('Content-Type'));
    }

    public function testCreatesBodyForEmptyDocument()
    {
        $location = new XmlLocation('xml');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $operation = new Operation([
            'data' => ['xmlAllowEmpty' => true]
        ], new Description([]));
        $location->after($command, $request, $operation, []);
        $xml = (string) $request->getBody();
        $this->assertEquals('<?xml version="1.0"?>' . "\n"
            . '<Request/>' . "\n", $xml);
        $this->assertEquals('application/xml', $request->getHeader('Content-Type'));
    }

    public function testAddsAdditionalParameters()
    {
        $location = new XmlLocation('xml', 'test');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $command['foo'] = 'bar';
        $location->visit($command, $request, $param, []);
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'xml'
            ]
        ], new Description([]));
        $command['bam'] = 'boo';
        $location->after($command, $request, $operation, []);
        $xml = (string) $request->getBody();
        $this->assertEquals('<?xml version="1.0"?>' . "\n"
            . '<Request><foo>bar</foo><foo>bar</foo><bam>boo</bam></Request>' . "\n", $xml);
        $this->assertEquals('test', $request->getHeader('Content-Type'));
    }

    public function testAllowsXmlEncoding()
    {
        $location = new XmlLocation('xml');
        $operation = new Operation([
            'data' => ['xmlEncoding' => 'UTF-8']
        ], new Description([]));
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $command['foo'] = 'bar';
        $location->visit($command, $request, $param, []);
        $location->after($command, $request, $operation, []);
        $xml = (string) $request->getBody();
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Request><foo>bar</foo></Request>' . "\n", $xml);
    }

    public function xmlProvider()
    {
        return array(
            array(
                array(
                    'data' => array(
                        'xmlRoot' => array(
                            'name'       => 'test',
                            'namespaces' => 'http://foo.com'
                        )
                    ),
                    'parameters' => array(
                        'Foo' => array('location' => 'xml', 'type' => 'string'),
                        'Baz' => array('location' => 'xml', 'type' => 'string')
                    )
                ),
                array('Foo' => 'test', 'Baz' => 'bar'),
                '<test xmlns="http://foo.com"><Foo>test</Foo><Baz>bar</Baz></test>'
            ),
            // Ensure that the content-type is not added
            array(array('parameters' => array('Foo' => array('location' => 'xml', 'type' => 'string'))), array(), ''),
            // Test with adding attributes and no namespace
            array(
                array(
                    'data' => array(
                        'xmlRoot' => array(
                            'name' => 'test'
                        )
                    ),
                    'parameters' => array(
                        'Foo' => array('location' => 'xml', 'type' => 'string', 'data' => array('xmlAttribute' => true))
                    )
                ),
                array('Foo' => 'test', 'Baz' => 'bar'),
                '<test Foo="test"/>'
            ),
            // Test adding with an array
            array(
                array(
                    'parameters' => array(
                        'Foo' => array('location' => 'xml', 'type' => 'string'),
                        'Baz' => array(
                            'type'     => 'array',
                            'location' => 'xml',
                            'items' => array(
                                'type'   => 'numeric',
                                'sentAs' => 'Bar'
                            )
                        )
                    )
                ),
                array('Foo' => 'test', 'Baz' => array(1, 2)),
                '<Request><Foo>test</Foo><Baz><Bar>1</Bar><Bar>2</Bar></Baz></Request>'
            ),
            // Test adding an object
            array(
                array(
                    'parameters' => array(
                        'Foo' => array('location' => 'xml', 'type' => 'string'),
                        'Baz' => array(
                            'type'     => 'object',
                            'location' => 'xml',
                            'properties' => array(
                                'Bar' => array('type' => 'string'),
                                'Bam' => array()
                            )
                        )
                    )
                ),
                array('Foo' => 'test', 'Baz' => array('Bar' => 'abc', 'Bam' => 'foo')),
                '<Request><Foo>test</Foo><Baz><Bar>abc</Bar><Bam>foo</Bam></Baz></Request>'
            ),
            // Add an array that contains an object
            array(
                array(
                    'parameters' => array(
                        'Baz' => array(
                            'type'     => 'array',
                            'location' => 'xml',
                            'items' => array(
                                'type'       => 'object',
                                'sentAs'     => 'Bar',
                                'properties' => array('A' => array(), 'B' => array())
                            )
                        )
                    )
                ),
                array('Baz' => array(
                    array('A' => '1', 'B' => '2'),
                    array('A' => '3', 'B' => '4')
                )),
                '<Request><Baz><Bar><A>1</A><B>2</B></Bar><Bar><A>3</A><B>4</B></Bar></Baz></Request>'
            ),
            // Add an object of attributes
            array(
                array(
                    'parameters' => array(
                        'Foo' => array('location' => 'xml', 'type' => 'string'),
                        'Baz' => array(
                            'type'     => 'object',
                            'location' => 'xml',
                            'properties' => array(
                                'Bar' => array('type' => 'string', 'data' => array('xmlAttribute' => true)),
                                'Bam' => array()
                            )
                        )
                    )
                ),
                array('Foo' => 'test', 'Baz' => array('Bar' => 'abc', 'Bam' => 'foo')),
                '<Request><Foo>test</Foo><Baz Bar="abc"><Bam>foo</Bam></Baz></Request>'
            ),
            // Check order doesn't matter
            array(
                array(
                    'parameters' => array(
                        'Foo' => array('location' => 'xml', 'type' => 'string'),
                        'Baz' => array(
                            'type'     => 'object',
                            'location' => 'xml',
                            'properties' => array(
                                'Bar' => array('type' => 'string', 'data' => array('xmlAttribute' => true)),
                                'Bam' => array()
                            )
                        )
                    )
                ),
                array('Foo' => 'test', 'Baz' => array('Bam' => 'foo', 'Bar' => 'abc')),
                '<Request><Foo>test</Foo><Baz Bar="abc"><Bam>foo</Bam></Baz></Request>'
            ),
            // Add values with custom namespaces
            array(
                array(
                    'parameters' => array(
                        'Foo' => array(
                            'location' => 'xml',
                            'type' => 'string',
                            'data' => array(
                                'xmlNamespace' => 'http://foo.com'
                            )
                        )
                    )
                ),
                array('Foo' => 'test'),
                '<Request><Foo xmlns="http://foo.com">test</Foo></Request>'
            ),
            // Add attributes with custom namespace prefix
            array(
                array(
                    'parameters' => array(
                        'Wrap' => array(
                            'type' => 'object',
                            'location' => 'xml',
                            'properties' => array(
                                'Foo' => array(
                                    'type' => 'string',
                                    'sentAs' => 'xsi:baz',
                                    'data' => array(
                                        'xmlNamespace' => 'http://foo.com',
                                        'xmlAttribute' => true
                                    )
                                )
                            )
                        ),
                    )
                ),
                array('Wrap' => array(
                    'Foo' => 'test'
                )),
                '<Request><Wrap xsi:baz="test" xmlns:xsi="http://foo.com"/></Request>'
            ),
            // Add nodes with custom namespace prefix
            array(
                array(
                    'parameters' => array(
                        'Wrap' => array(
                            'type' => 'object',
                            'location' => 'xml',
                            'properties' => array(
                                'Foo' => array(
                                    'type' => 'string',
                                    'sentAs' => 'xsi:Foo',
                                    'data' => array(
                                        'xmlNamespace' => 'http://foobar.com'
                                    )
                                )
                            )
                        ),
                    )
                ),
                array('Wrap' => array(
                    'Foo' => 'test'
                )),
                '<Request><Wrap><xsi:Foo xmlns:xsi="http://foobar.com">test</xsi:Foo></Wrap></Request>'
            ),
            array(
                array(
                    'parameters' => array(
                        'Foo' => array(
                            'location' => 'xml',
                            'type' => 'string',
                            'data' => array(
                                'xmlNamespace' => 'http://foo.com'
                            )
                        )
                    )
                ),
                array('Foo' => '<h1>This is a title</h1>'),
                '<Request><Foo xmlns="http://foo.com"><![CDATA[<h1>This is a title</h1>]]></Foo></Request>'
            ),
            // Flat array at top level
            array(
                array(
                    'parameters' => array(
                        'Bars' => array(
                            'type'     => 'array',
                            'data'     => array('xmlFlattened' => true),
                            'location' => 'xml',
                            'items' => array(
                                'type'       => 'object',
                                'sentAs'     => 'Bar',
                                'properties' => array(
                                    'A' => array(),
                                    'B' => array()
                                )
                            )
                        ),
                        'Boos' => array(
                            'type'     => 'array',
                            'data'     => array('xmlFlattened' => true),
                            'location' => 'xml',
                            'items'  => array(
                                'sentAs' => 'Boo',
                                'type' => 'string'
                            )
                        )
                    )
                ),
                array(
                    'Bars' => array(
                        array('A' => '1', 'B' => '2'),
                        array('A' => '3', 'B' => '4')
                    ),
                    'Boos' => array('test', '123')
                ),
                '<Request><Bar><A>1</A><B>2</B></Bar><Bar><A>3</A><B>4</B></Bar><Boo>test</Boo><Boo>123</Boo></Request>'
            ),
            // Nested flat arrays
            array(
                array(
                    'parameters' => array(
                        'Delete' => array(
                            'type'     => 'object',
                            'location' => 'xml',
                            'properties' => array(
                                'Items' => array(
                                    'type' => 'array',
                                    'data' => array('xmlFlattened' => true),
                                    'items' => array(
                                        'type'       => 'object',
                                        'sentAs'     => 'Item',
                                        'properties' => array(
                                            'A' => array(),
                                            'B' => array()
                                        )
                                    )
                                )
                            )
                        )
                    )
                ),
                array(
                    'Delete' => array(
                        'Items' => array(
                            array('A' => '1', 'B' => '2'),
                            array('A' => '3', 'B' => '4')
                        )
                    )
                ),
                '<Request><Delete><Item><A>1</A><B>2</B></Item><Item><A>3</A><B>4</B></Item></Delete></Request>'
            ),
            // Test adding root node attributes after nodes
            array(
                array(
                    'data' => array(
                        'xmlRoot' => array(
                            'name' => 'test'
                        )
                    ),
                    'parameters' => array(
                        'Foo' => array('location' => 'xml', 'type' => 'string'),
                        'Baz' => array('location' => 'xml', 'type' => 'string', 'data' => array('xmlAttribute' => true)),
                    )
                ),
                array('Foo' => 'test', 'Baz' => 'bar'),
                '<test Baz="bar"><Foo>test</Foo></test>'
            ),
        );
    }

    /**
     * @dataProvider xmlProvider
     */
    public function testSerializesXml(array $operation, array $input, $xml)
    {
        $operation['uri'] = 'http://httpbin.org';
        $client = new GuzzleClient(
            new Client(),
            new Description([
                'operations' => [
                    'foo' => $operation
                ]
            ]
        ));

        $request = null;
        $command = $client->getCommand('foo', $input);

        $command->getEmitter()->on(
            'prepared',
            function (PreparedEvent $event) use (&$request) {
                $request = $event->getRequest();
                $event->getRequest()->getEmitter()->on(
                    'before',
                    function(BeforeEvent $e) {
                        $e->intercept(new Response(200));
                    }
                );
            }
        );

        $client->execute($command);

        if (empty($input)) {
            $this->assertEquals('', (string) $request->getHeader('Content-Type'));
        } else {
            $this->assertEquals('application/xml', $request->getHeader('Content-Type'));
        }

        $body = str_replace(array("\n", "<?xml version=\"1.0\"?>"), '', (string) $request->getBody());
        $this->assertEquals($xml, $body);
    }
}
