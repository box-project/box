<?php

namespace KevinGH\Box\Tests;

use Herrera\PHPUnit\TestCase;
use KevinGH\Box\Helper\ConfigurationHelper;

class ConfigurationHelperTest extends TestCase
{
    /**
     * @var ConfigurationHelper
     */
    private $helper;

    private $cwd;
    private $dir;

    public function testConstant()
    {
        $this->assertInternalType('string', BOX_SCHEMA_FILE);
    }

    public function testGetName()
    {
        $this->assertEquals('config', $this->helper->getName());
    }

    public function testGetDefaultPath()
    {
        touch('box.json');

        $this->assertEquals(
            $this->dir . DIRECTORY_SEPARATOR . 'box.json',
            $this->helper->getDefaultPath()
        );
    }

    public function testGetDefaultPathDist()
    {
        touch('box.json.dist');

        $this->assertEquals(
            $this->dir . DIRECTORY_SEPARATOR . 'box.json.dist',
            $this->helper->getDefaultPath()
        );
    }

    public function testLoadFile()
    {
        file_put_contents('box.json.dist', '{}');

        $this->assertInstanceOf(
            'KevinGH\\Box\\Configuration',
            $this->helper->loadFile()
        );
    }

    public function testLoadFileImport()
    {
        file_put_contents('box.json', '{"import": "test.json"}');
        file_put_contents('test.json', '{"alias": "import.phar"}');

        $config = $this->helper->loadFile();

        $this->assertEquals(
            'import.phar',
            $config->getAlias()
        );
    }

    public function testGetDefaultPathNotExist()
    {
        $this->setExpectedException(
            'RuntimeException',
            'The configuration file could not be found.'
        );

        $this->helper->getDefaultPath();
    }

    protected function tearDown()
    {
        chdir($this->cwd);
    }

    protected function setUp()
    {
        $this->cwd = getcwd();
        $this->dir = $this->createDir();
        $this->helper = new ConfigurationHelper();

        chdir($this->dir);
    }
}
