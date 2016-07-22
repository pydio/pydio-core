<?php

namespace Tests\Guzzle\Service\Loader;

use Guzzle\Service\Loader\PhpLoader;

use Symfony\Component\Config\FileLocator;

class PhpLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected $PhpLoader;

    protected $locator;

    public function setUp()
    {
        $configDirectories = array(FIXTURES_PATH);
        $this->locator = new FileLocator($configDirectories);

        $this->PhpLoader = new PhpLoader($this->locator);
    }

    public function testLoad()
    {
        $values = $this->PhpLoader->load($this->locator->locate('description.php'));

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

        $PhpLoader = new PhpLoader($locator);
        $PhpLoader->load($locator->locate('notFound.php'));
    }
}