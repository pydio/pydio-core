<?php
namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation
 */
class XmlLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new XmlLocation('xml');
        $command = new Command('foo', []);
        $parameter = new Parameter([
            'name'    => 'val',
            'sentAs'  => 'vim',
            'filters' => ['strtoupper']
        ]);
        $model = new Parameter();
        $response = new Response(200, [], Stream::factory('<w><vim>bar</vim></w>'));
        $result = [];
        $l->before($command, $response, $model, $result);
        $l->visit($command, $response, $parameter, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals('BAR', $result['val']);
    }

    public function testVisitsAdditionalProperties()
    {
        $l = new XmlLocation('xml');
        $command = new Command('foo', []);
        $parameter = new Parameter();
        $model = new Parameter(['additionalProperties' => ['location' => 'xml']]);
        $response = new Response(200, [], Stream::factory('<w><vim>bar</vim></w>'));
        $result = [];
        $l->before($command, $response, $parameter, $result);
        $l->visit($command, $response, $parameter, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals('bar', $result['vim']);
    }

    public function testEnsuresFlatArraysAreFlat()
    {
        $param = new Parameter(array(
            'location' => 'xml',
            'name'     => 'foo',
            'type'     => 'array',
            'items'    => array('type' => 'string')
        ));

        $xml = '<xml><foo>bar</foo><foo>baz</foo></xml>';
        $this->xmlTest($param, $xml, array('foo' => array('bar', 'baz')));
        $this->xmlTest($param, '<xml><foo>bar</foo></xml>', array('foo' => array('bar')));
    }

    public function xmlDataProvider()
    {
        $param = new Parameter(array(
            'location' => 'xml',
            'name'     => 'Items',
            'type'     => 'array',
            'items'    => array(
                'type'       => 'object',
                'name'       => 'Item',
                'properties' => array(
                    'Bar' => array('type' => 'string'),
                    'Baz' => array('type' => 'string')
                )
            )
        ));

        return array(
            array($param, '<Test><Items><Item><Bar>1</Bar></Item><Item><Bar>2</Bar></Item></Items></Test>', array(
                'Items' => array(
                    array('Bar' => 1),
                    array('Bar' => 2)
                )
            )),
            array($param, '<Test><Items><Item><Bar>1</Bar></Item></Items></Test>', array(
                'Items' => array(
                    array('Bar' => 1)
                )
            )),
            array($param, '<Test><Items /></Test>', array(
                'Items' => array()
            ))
        );
    }

    /**
     * @dataProvider xmlDataProvider
     */
    public function testEnsuresWrappedArraysAreInCorrectLocations($param, $xml, $expected)
    {
        $l = new XmlLocation('xml');
        $command = new Command('foo', []);
        $model = new Parameter();
        $response = new Response(200, [], Stream::factory($xml));
        $result = [];
        $l->before($command, $response, $param, $result);
        $l->visit($command, $response, $param, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals($result, $expected);
    }

    public function testCanRenameValues()
    {
        $param = new Parameter(array(
            'name'     => 'TerminatingInstances',
            'type'     => 'array',
            'location' => 'xml',
            'sentAs'   => 'instancesSet',
            'items'    => array(
                'name'       => 'item',
                'type'       => 'object',
                'sentAs'     => 'item',
                'properties' => array(
                    'InstanceId'    => array(
                        'type'   => 'string',
                        'sentAs' => 'instanceId',
                    ),
                    'CurrentState'  => array(
                        'type'       => 'object',
                        'sentAs'     => 'currentState',
                        'properties' => array(
                            'Code' => array(
                                'type'   => 'numeric',
                                'sentAs' => 'code',
                            ),
                            'Name' => array(
                                'type'   => 'string',
                                'sentAs' => 'name',
                            ),
                        ),
                    ),
                    'PreviousState' => array(
                        'type'       => 'object',
                        'sentAs'     => 'previousState',
                        'properties' => array(
                            'Code' => array(
                                'type'   => 'numeric',
                                'sentAs' => 'code',
                            ),
                            'Name' => array(
                                'type'   => 'string',
                                'sentAs' => 'name',
                            ),
                        ),
                    ),
                ),
            )
        ));

        $xml = '
            <xml>
                <instancesSet>
                    <item>
                        <instanceId>i-3ea74257</instanceId>
                        <currentState>
                            <code>32</code>
                            <name>shutting-down</name>
                        </currentState>
                        <previousState>
                            <code>16</code>
                            <name>running</name>
                        </previousState>
                    </item>
                </instancesSet>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'TerminatingInstances' => array(
                array(
                    'InstanceId'    => 'i-3ea74257',
                    'CurrentState'  => array(
                        'Code' => '32',
                        'Name' => 'shutting-down',
                    ),
                    'PreviousState' => array(
                        'Code' => '16',
                        'Name' => 'running',
                    )
                )
            )
        ));
    }

    public function testCanRenameAttributes()
    {
        $param = new Parameter(array(
            'name'     => 'RunningQueues',
            'type'     => 'array',
            'location' => 'xml',
            'items'    => array(
                'type'       => 'object',
                'sentAs'     => 'item',
                'properties' => array(
                    'QueueId'       => array(
                        'type'   => 'string',
                        'sentAs' => 'queue_id',
                        'data'   => array(
                            'xmlAttribute' => true,
                        ),
                    ),
                    'CurrentState'  => array(
                        'type'       => 'object',
                        'properties' => array(
                            'Code' => array(
                                'type'   => 'numeric',
                                'sentAs' => 'code',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                            'Name' => array(
                                'sentAs' => 'name',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                        ),
                    ),
                    'PreviousState' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'Code' => array(
                                'type'   => 'numeric',
                                'sentAs' => 'code',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                            'Name' => array(
                                'sentAs' => 'name',
                                'data'   => array(
                                    'xmlAttribute' => true,
                                ),
                            ),
                        ),
                    ),
                ),
            )
        ));

        $xml = '
            <wrap>
                <RunningQueues>
                    <item queue_id="q-3ea74257">
                        <CurrentState code="32" name="processing" />
                        <PreviousState code="16" name="wait" />
                    </item>
                </RunningQueues>
            </wrap>';

        $this->xmlTest($param, $xml, array(
            'RunningQueues' => array(
                array(
                    'QueueId'       => 'q-3ea74257',
                    'CurrentState'  => array(
                        'Code' => '32',
                        'Name' => 'processing',
                    ),
                    'PreviousState' => array(
                        'Code' => '16',
                        'Name' => 'wait',
                    ),
                ),
            )
        ));
    }

    public function testAddsEmptyArraysWhenValueIsMissing()
    {
        $param = new Parameter(array(
            'name'     => 'Foo',
            'type'     => 'array',
            'location' => 'xml',
            'items'    => array(
                'type'       => 'object',
                'properties' => array(
                    'Baz' => array('type' => 'array'),
                    'Bar' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'Baz' => array('type' => 'array'),
                        )
                    )
                )
            )
        ));

        $xml = '<xml><Foo><Bar></Bar></Foo></xml>';

        $this->xmlTest($param, $xml, array(
            'Foo' => array(
                array(
                    'Bar' => array()
                )
            )
        ));
    }

    /**
     * @group issue-399
     * @link  https://github.com/guzzle/guzzle/issues/399
     */
    public function testDiscardingUnknownProperties()
    {
        $param = new Parameter(array(
            'name'                 => 'foo',
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'bar' => array(
                    'type' => 'string',
                    'name' => 'bar',
                ),
            ),
        ));

        $xml = '
            <xml>
                <foo>
                    <bar>15</bar>
                    <unknown>discard me</unknown>
                </foo>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'foo' => array(
                'bar' => 15
            )
        ));
    }

    /**
     * @group issue-399
     * @link  https://github.com/guzzle/guzzle/issues/399
     */
    public function testDiscardingUnknownPropertiesWithAliasing()
    {
        $param = new Parameter(array(
            'name'                 => 'foo',
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'bar' => array(
                    'name'   => 'bar',
                    'sentAs' => 'baz',
                ),
            ),
        ));

        $xml = '
            <xml>
                <foo>
                    <baz>15</baz>
                    <unknown>discard me</unknown>
                </foo>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'foo' => array(
                'bar' => 15
            )
        ));
    }

    public function testProcessingOfNestedAdditionalProperties()
    {
        $param = new Parameter(array(
            'name'                 => 'foo',
            'type'                 => 'object',
            'additionalProperties' => true,
            'properties'           => array(
                'bar'                        => array(
                    'name'   => 'bar',
                    'sentAs' => 'baz',
                ),
                'nestedNoAdditional'         => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'id' => array(
                            'type' => 'integer'
                        )
                    )
                ),
                'nestedWithAdditional'       => array(
                    'type'                 => 'object',
                    'additionalProperties' => true,
                ),
                'nestedWithAdditionalSchema' => array(
                    'type'                 => 'object',
                    'additionalProperties' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type' => 'string'
                        )
                    ),
                ),
            ),
        ));

        $xml = '
            <xml>
                <foo>
                    <baz>15</baz>
                    <additional>include me</additional>
                    <nestedNoAdditional>
                        <id>15</id>
                        <unknown>discard me</unknown>
                    </nestedNoAdditional>
                    <nestedWithAdditional>
                        <id>15</id>
                        <additional>include me</additional>
                    </nestedWithAdditional>
                    <nestedWithAdditionalSchema>
                        <arrayA>
                            <item>1</item>
                            <item>2</item>
                            <item>3</item>
                        </arrayA>
                        <arrayB>
                            <item>A</item>
                            <item>B</item>
                            <item>C</item>
                        </arrayB>
                    </nestedWithAdditionalSchema>
                </foo>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'foo' => array(
                'bar'                        => '15',
                'additional'                 => 'include me',
                'nestedNoAdditional'         => array(
                    'id' => '15'
                ),
                'nestedWithAdditional'       => array(
                    'id'         => '15',
                    'additional' => 'include me'
                ),
                'nestedWithAdditionalSchema' => array(
                    'arrayA' => array('1', '2', '3'),
                    'arrayB' => array('A', 'B', 'C'),
                )

            )
        ));
    }

    public function testConvertsMultipleAssociativeElementsToArray()
    {
        $param = new Parameter(array(
            'name'                 => 'foo',
            'type'                 => 'object',
            'additionalProperties' => true
        ));

        $xml = '
            <xml>
                <foo>
                    <baz>15</baz>
                    <baz>25</baz>
                    <bar>hi</bar>
                    <bam>test</bam>
                    <bam attr="hi" />
                </foo>
            </xml>
        ';

        $this->xmlTest($param, $xml, [
            'foo' => [
                'baz' => ['15', '25'],
                'bar' => 'hi',
                'bam' => [
                    'test',
                    ['@attributes' => ['attr' => 'hi']]
                ]
            ]
        ]);
    }

    public function testUnderstandsNamespaces()
    {
        $param = new Parameter(array(
            'name'     => 'nstest',
            'type'     => 'array',
            'location' => 'xml',
            'items'    => array(
                'name'       => 'item',
                'type'       => 'object',
                'sentAs'     => 'item',
                'properties' => array(
                    'id'           => array(
                        'type' => 'string',
                    ),
                    'isbn:number'  => array(
                        'type' => 'string',
                    ),
                    'meta'         => array(
                        'type'       => 'object',
                        'sentAs'     => 'abstract:meta',
                        'properties' => array(
                            'foo' => array(
                                'type' => 'numeric',
                            ),
                            'bar' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'attribute' => array(
                                        'type' => 'string',
                                        'data' => array(
                                            'xmlAttribute' => true,
                                            'xmlNs'        => 'abstract'
                                        ),
                                    )
                                )
                            ),
                        ),
                    ),
                    'gamma'        => array(
                        'type'                 => 'object',
                        'data'                 => array(
                            'xmlNs' => 'abstract'
                        ),
                        'additionalProperties' => true
                    ),
                    'nonExistent'  => array(
                        'type'                 => 'object',
                        'data'                 => array(
                            'xmlNs' => 'abstract'
                        ),
                        'additionalProperties' => true
                    ),
                    'nonExistent2' => array(
                        'type'                 => 'object',
                        'additionalProperties' => true
                    ),
                ),
            )
        ));

        $xml = '
            <xml>
                <nstest xmlns:isbn="urn:ISBN:0-395-36341-6" xmlns:abstract="urn:my.org:abstract">
                    <item>
                        <id>101</id>
                        <isbn:number>1568491379</isbn:number>
                        <abstract:meta>
                            <foo>10</foo>
                            <bar abstract:attribute="foo"></bar>
                        </abstract:meta>
                        <abstract:gamma>
                            <foo>bar</foo>
                        </abstract:gamma>
                    </item>
                    <item>
                        <id>102</id>
                        <isbn:number>1568491999</isbn:number>
                        <abstract:meta>
                            <foo>20</foo>
                            <bar abstract:attribute="bar"></bar>
                        </abstract:meta>
                        <abstract:gamma>
                            <foo>baz</foo>
                        </abstract:gamma>
                    </item>
                </nstest>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'nstest' => array(
                array(
                    'id'          => '101',
                    'isbn:number' => 1568491379,
                    'meta'        => array(
                        'foo' => 10,
                        'bar' => array(
                            'attribute' => 'foo'
                        ),
                    ),
                    'gamma'       => array(
                        'foo' => 'bar'
                    )
                ),
                array(
                    'id'          => '102',
                    'isbn:number' => 1568491999,
                    'meta'        => array(
                        'foo' => 20,
                        'bar' => array(
                            'attribute' => 'bar'
                        ),
                    ),
                    'gamma'       => array(
                        'foo' => 'baz'
                    )
                ),
            )
        ));
    }

    public function testCanWalkUndefinedPropertiesWithNamespace()
    {
        $param = new Parameter(array(
            'name'     => 'nstest',
            'type'     => 'array',
            'location' => 'xml',
            'items'    => array(
                'name'                 => 'item',
                'type'                 => 'object',
                'sentAs'               => 'item',
                'additionalProperties' => array(
                    'type' => 'object',
                    'data' => array(
                        'xmlNs' => 'abstract'
                    ),
                ),
                'properties'           => array(
                    'id'          => array(
                        'type' => 'string',
                    ),
                    'isbn:number' => array(
                        'type' => 'string',
                    )
                )
            )
        ));

        $xml = '
            <xml>
                <nstest xmlns:isbn="urn:ISBN:0-395-36341-6" xmlns:abstract="urn:my.org:abstract">
                    <item>
                        <id>101</id>
                        <isbn:number>1568491379</isbn:number>
                        <abstract:meta>
                            <foo>10</foo>
                            <bar>baz</bar>
                        </abstract:meta>
                    </item>
                    <item>
                        <id>102</id>
                        <isbn:number>1568491999</isbn:number>
                        <abstract:meta>
                            <foo>20</foo>
                            <bar>foo</bar>
                        </abstract:meta>
                    </item>
                </nstest>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'nstest' => array(
                array(
                    'id'          => '101',
                    'isbn:number' => 1568491379,
                    'meta'        => array(
                        'foo' => 10,
                        'bar' => 'baz'
                    )
                ),
                array(
                    'id'          => '102',
                    'isbn:number' => 1568491999,
                    'meta'        => array(
                        'foo' => 20,
                        'bar' => 'foo'
                    )
                ),
            )
        ));
    }

    public function testCanWalkSimpleArrayWithNamespace()
    {
        $param = new Parameter(array(
            'name'     => 'nstest',
            'type'     => 'array',
            'location' => 'xml',
            'items'    => array(
                'type'   => 'string',
                'sentAs' => 'number',
                'data'   => array(
                    'xmlNs' => 'isbn'
                )
            )
        ));

        $xml = '
            <xml>
                <nstest xmlns:isbn="urn:ISBN:0-395-36341-6">
                    <isbn:number>1568491379</isbn:number>
                    <isbn:number>1568491999</isbn:number>
                    <isbn:number>1568492999</isbn:number>
                </nstest>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'nstest' => array(
                1568491379,
                1568491999,
                1568492999,
            )
        ));
    }

    public function testCanWalkSimpleArrayWithNamespace2()
    {
        $param = new Parameter(array(
            'name'     => 'nstest',
            'type'     => 'array',
            'location' => 'xml',
            'items'    => array(
                'type'   => 'string',
                'sentAs' => 'isbn:number',
            )
        ));

        $xml = '
            <xml>
                <nstest xmlns:isbn="urn:ISBN:0-395-36341-6">
                    <isbn:number>1568491379</isbn:number>
                    <isbn:number>1568491999</isbn:number>
                    <isbn:number>1568492999</isbn:number>
                </nstest>
            </xml>
        ';

        $this->xmlTest($param, $xml, array(
            'nstest' => array(
                1568491379,
                1568491999,
                1568492999,
            )
        ));
    }

    private function xmlTest(Parameter $param, $xml, $expected)
    {
        $l = new XmlLocation('xml');
        $command = new Command('foo', []);
        $model = new Parameter();
        $response = new Response(200, [], Stream::factory($xml));
        $result = [];
        $l->before($command, $response, $param, $result);
        $l->visit($command, $response, $param, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals($expected, $result);
    }
}
