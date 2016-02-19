<?php
namespace Guzzle\Tests\Service\Description;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\Description;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Parameter
 */
class ParameterTest extends \PHPUnit_Framework_TestCase
{
    protected $data = array(
        'name'            => 'foo',
        'type'            => 'bar',
        'required'        => true,
        'default'         => '123',
        'description'     => '456',
        'minLength'       => 2,
        'maxLength'       => 5,
        'location'        => 'body',
        'static'          => 'static!',
        'filters'         => array('trim', 'json_encode')
    );

    public function testCreatesParamFromArray()
    {
        $p = new Parameter($this->data);
        $this->assertEquals('foo', $p->getName());
        $this->assertEquals('bar', $p->getType());
        $this->assertEquals(true, $p->getRequired());
        $this->assertEquals('123', $p->getDefault());
        $this->assertEquals('456', $p->getDescription());
        $this->assertEquals(2, $p->getMinLength());
        $this->assertEquals(5, $p->getMaxLength());
        $this->assertEquals('body', $p->getLocation());
        $this->assertEquals('static!', $p->getStatic());
        $this->assertEquals(array('trim', 'json_encode'), $p->getFilters());
        $p->setName('abc');
        $this->assertEquals('abc', $p->getName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesDescription()
    {
        new Parameter($this->data, ['description' => 'foo']);
    }

    public function testCanConvertToArray()
    {
        $p = new Parameter($this->data);
        $this->assertEquals($this->data, $p->toArray());
    }

    public function testUsesStatic()
    {
        $d = $this->data;
        $d['default'] = 'booboo';
        $d['static'] = true;
        $p = new Parameter($d);
        $this->assertEquals('booboo', $p->getValue('bar'));
    }

    public function testUsesDefault()
    {
        $d = $this->data;
        $d['default'] = 'foo';
        $d['static'] = null;
        $p = new Parameter($d);
        $this->assertEquals('foo', $p->getValue(null));
    }

    public function testReturnsYourValue()
    {
        $d = $this->data;
        $d['static'] = null;
        $p = new Parameter($d);
        $this->assertEquals('foo', $p->getValue('foo'));
    }

    public function testZeroValueDoesNotCauseDefaultToBeReturned()
    {
        $d = $this->data;
        $d['default'] = '1';
        $d['static'] = null;
        $p = new Parameter($d);
        $this->assertEquals('0', $p->getValue('0'));
    }

    public function testFiltersValues()
    {
        $d = $this->data;
        $d['static'] = null;
        $d['filters'] = 'strtoupper';
        $p = new Parameter($d);
        $this->assertEquals('FOO', $p->filter('foo'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No service description
     */
    public function testRequiresServiceDescriptionForFormatting()
    {
        $d = $this->data;
        $d['format'] = 'foo';
        $p = new Parameter($d);
        $p->filter('bar');
    }

    public function testConvertsBooleans()
    {
        $p = new Parameter(array('type' => 'boolean'));
        $this->assertEquals(true, $p->filter('true'));
        $this->assertEquals(false, $p->filter('false'));
    }

    public function testUsesArrayByDefaultForFilters()
    {
        $d = $this->data;
        $d['filters'] = null;
        $p = new Parameter($d);
        $this->assertEquals(array(), $p->getFilters());
    }

    public function testAllowsSimpleLocationValue()
    {
        $p = new Parameter(array('name' => 'myname', 'location' => 'foo', 'sentAs' => 'Hello'));
        $this->assertEquals('foo', $p->getLocation());
        $this->assertEquals('Hello', $p->getSentAs());
    }

    public function testParsesTypeValues()
    {
        $p = new Parameter(array('type' => 'foo'));
        $this->assertEquals('foo', $p->getType());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage A [method] value must be specified for each complex filter
     */
    public function testValidatesComplexFilters()
    {
        $p = new Parameter(array('filters' => array(array('args' => 'foo'))));
    }

    public function testAllowsComplexFilters()
    {
        $that = $this;
        $param = new Parameter([
            'filters' => [
                [
                    'method' => function ($a, $b, $c, $d) use ($that, &$param) {
                        $that->assertEquals('test', $a);
                        $that->assertEquals('my_value!', $b);
                        $that->assertEquals('bar', $c);
                        $that->assertSame($param, $d);
                        return 'abc' . $b;
                    },
                    'args' => ['test', '@value', 'bar', '@api']
                ]
            ]
        ]);

        $this->assertEquals('abcmy_value!', $param->filter('my_value!'));
    }

    public function testAddsAdditionalProperties()
    {
        $p = new Parameter(array(
            'type' => 'object',
            'additionalProperties' => array('type' => 'string')
        ));
        $this->assertInstanceOf('GuzzleHttp\Command\Guzzle\Parameter', $p->getAdditionalProperties());
        $this->assertNull($p->getAdditionalProperties()->getAdditionalProperties());
        $p = new Parameter(array('type' => 'object'));
        $this->assertTrue($p->getAdditionalProperties());
    }

    public function testAddsItems()
    {
        $p = new Parameter(array(
            'type'  => 'array',
            'items' => array('type' => 'string')
        ));
        $this->assertInstanceOf('GuzzleHttp\Command\Guzzle\Parameter', $p->getItems());
        $out = $p->toArray();
        $this->assertEquals('array', $out['type']);
        $this->assertInternalType('array', $out['items']);
    }

    public function testCanRetrieveKnownPropertiesUsingDataMethod()
    {
        $p = new Parameter(['data' => ['name' => 'test'], 'extra' => 'hi!']);
        $this->assertEquals('test', $p->getData('name'));
        $this->assertEquals(['name' => 'test'], $p->getData());
        $this->assertNull($p->getData('fjnweefe'));
        $this->assertEquals('hi!', $p->getData('extra'));
    }

    public function testHasPattern()
    {
        $p = new Parameter(['pattern' => '/[0-9]+/']);
        $this->assertEquals('/[0-9]+/', $p->getPattern());
    }

    public function testHasEnum()
    {
        $p = new Parameter(['enum' => ['foo', 'bar']]);
        $this->assertEquals(array('foo', 'bar'), $p->getEnum());
    }

    public function testSerializesItems()
    {
        $p = new Parameter(array(
            'type'  => 'object',
            'additionalProperties' => array('type' => 'string')
        ));
        $this->assertEquals(array(
            'type'  => 'object',
            'additionalProperties' => array('type' => 'string')
        ), $p->toArray());
    }

    public function testResolvesRefKeysRecursively()
    {
        $description = new Description(array(
            'models' => array(
                'JarJar' => array('type' => 'string', 'default' => 'Mesa address tha senate!'),
                'Anakin' => array('type' => 'array', 'items' => array('$ref' => 'JarJar'))
            )
        ));
        $p = new Parameter(array('$ref' => 'Anakin', 'description' => 'added'), ['description' => $description]);
        $this->assertEquals(array(
            'description' => 'added',
            '$ref' => 'Anakin'
        ), $p->toArray());
    }

    public function testResolvesExtendsRecursively()
    {
        $jarJar = array('type' => 'string', 'default' => 'Mesa address tha senate!', 'description' => 'a');
        $anakin = array('type' => 'array', 'items' => array('extends' => 'JarJar', 'description' => 'b'));
        $description = new Description(array(
            'models' => array('JarJar' => $jarJar, 'Anakin' => $anakin)
        ));
        // Description attribute will be updated, and format added
        $p = new Parameter(array('extends' => 'Anakin', 'format' => 'date'), ['description' => $description]);
        $this->assertEquals(array(
            'format' => 'date',
            'extends' => 'Anakin'
        ), $p->toArray());
    }

    public function testHasKeyMethod()
    {
        $p = new Parameter(['name' => 'foo', 'sentAs' => 'bar']);
        $this->assertEquals('bar', $p->getWireName());
    }

    public function testIncludesNameInToArrayWhenItemsAttributeHasName()
    {
        $p = new Parameter(array(
            'type' => 'array',
            'name' => 'Abc',
            'items' => array(
                'name' => 'Foo',
                'type' => 'object'
            )
        ));
        $result = $p->toArray();
        $this->assertEquals(array(
            'type' => 'array',
            'name' => 'Abc',
            'items' => array(
                'name' => 'Foo',
                'type' => 'object'
            )
        ), $result);
    }

    public function dateTimeProvider()
    {
        $d = 'October 13, 2012 16:15:46 UTC';

        return array(
            array($d, 'date-time', '2012-10-13T16:15:46Z'),
            array($d, 'date', '2012-10-13'),
            array($d, 'timestamp', strtotime($d)),
            array(new \DateTime($d), 'timestamp', strtotime($d))
        );
    }

    /**
     * @dataProvider dateTimeProvider
     */
    public function testAppliesFormat($d, $format, $result)
    {
        $p = new Parameter(['format' => $format], ['description' => new Description([])]);
        $this->assertEquals($format, $p->getFormat());
        $this->assertEquals($result, $p->filter($d));
    }

    public function testHasMinAndMax()
    {
        $p = new Parameter([
            'minimum' => 2,
            'maximum' => 3,
            'minItems' => 4,
            'maxItems' => 5,
        ]);
        $this->assertEquals(2, $p->getMinimum());
        $this->assertEquals(3, $p->getMaximum());
        $this->assertEquals(4, $p->getMinItems());
        $this->assertEquals(5, $p->getMaxItems());
    }

    public function testHasProperties()
    {
        $data = [
            'type' => 'object',
            'properties' => [
                'foo' => ['type' => 'string'],
                'bar' => ['type' => 'string'],
            ]
        ];
        $p = new Parameter($data);
        $this->assertInstanceOf('GuzzleHttp\\Command\\Guzzle\\Parameter', $p->getProperty('foo'));
        $this->assertSame($p->getProperty('foo'), $p->getProperty('foo'));
        $this->assertNull($p->getProperty('wefwe'));

        $properties = $p->getProperties();
        $this->assertInternalType('array', $properties);
        foreach ($properties as $prop) {
            $this->assertInstanceOf('GuzzleHttp\\Command\\Guzzle\\Parameter', $prop);
        }

        $this->assertEquals($data, $p->toArray());
    }
}
