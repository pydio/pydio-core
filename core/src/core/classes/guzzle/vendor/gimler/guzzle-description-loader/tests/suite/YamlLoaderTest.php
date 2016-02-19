<?php

namespace Tests\Guzzle\Service\Loader;

use Guzzle\Service\Loader\YamlLoader;

use Symfony\Component\Config\FileLocator;

class YamlLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected $YamlLoader;

    protected $locator;

    public function setUp()
    {
        $configDirectories = array(FIXTURES_PATH);
        $this->locator = new FileLocator($configDirectories);

        $this->YamlLoader = new YamlLoader($this->locator);
    }

    public function testLoad()
    {
        $values = $this->YamlLoader->load($this->locator->locate('description.yml'));

        $this->assertArrayHasKey('operations', $values);
        $this->assertArrayHasKey('models', $values);
        $this->assertArrayHasKey('certificates.add', $values['operations'], 'first level operation not found');
        $this->assertArrayHasKey('certificates.list', $values['operations'], 'import failed');
        $this->assertArrayHasKey('certificates.delete', $values['operations'], 'recursive imports failed');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFileNotFound()
    {
        $configDirectories = array(FIXTURES_PATH);
        $locator = new FileLocator($configDirectories);

        $YamlLoader = new YamlLoader($locator);
        $YamlLoader->load($locator->locate('notFound.yml'));
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testInvalid()
    {
        $configDirectories = array(FIXTURES_PATH);
        $locator = new FileLocator($configDirectories);

        $YamlLoader = new YamlLoader($locator);
        $YamlLoader->load($locator->locate('invalid.yml'));
    }
}