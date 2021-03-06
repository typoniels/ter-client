<?php
namespace Helhum\TerClient\Tests\Unit;

use Helhum\TerClient\ExtensionUploadPacker;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;

class ExtensionUploadPackerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected static $fixture = array(
        'title' => 'Dummy title', 'description' => 'Dummy description',
        'category' => 'misc', 'shy' => 0, 'version' => '1.2.3-invalid', 'dependencies' => 'cms,extbase,fluid',
        'conflicts' => '', 'priority' => '', 'loadOrder' => '', 'module' => '', 'state' => 'beta',
        'uploadfolder' => 0, 'createDirs' => '', 'modify_tables' => '', 'clearcacheonload' => 1,
        'lockType' => '', 'author' => 'Author Name', 'author_email' => 'author@domain.com',
        'author_company' => '', 'CGLcompliance' => '', 'CGLcompliance_note' => '',
        'constraints' => array('depends' => array('typo3' => '6.1.0-6.2.99', 'cms' => ''), 'conflicts' => array(), 'suggests' => array('news' => '')),
        '_md5_values_when_last_written' => '',
    );

    /**
     * @var string
     */
    protected static $fixtureString = null;

    /**
     * @var int
     */
    protected static $mtime = null;

    public static function setUpBeforeClass()
    {
        self::$mtime = time();
        self::$fixtureString = '<' . '?php
            $EM_CONF[$_EXTKEY] = ' . var_export(self::$fixture, true) . ';
        ';
        $emConf = new vfsStreamFile('ext_emconf.php');
        $emConf->setContent(self::$fixtureString);
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('temp', 0777));
        vfsStreamWrapper::getRoot()->addChild($emConf);
    }

    /**
     * @dataProvider getValidateVersionInvalidTestValues
     * @param mixed $version
     */
    public function testThrowsRuntimeExceptionOnInvalidVersionNumberInConfiguration($version)
    {
        $plugin = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($plugin, 'validateVersionNumber');
        $method->setAccessible(true);
        $this->setExpectedException('RuntimeException');
        $method->invokeArgs($plugin, array($version));
    }

    /**
     * @return array
     */
    public function getValidateVersionInvalidTestValues()
    {
        return array(
            array('foobar'),
            array('f.o.b'),
            array('-1.0.0'),
            array('1.0.0-dev'),
            array('test-tag'),
            array('accidental.dotcount.match'),
        );
    }

    /**
     * @dataProvider getValidateVersionValidTestValues
     * @param mixed $version
     */
    public function testValidateVersionNumber($version)
    {
        $plugin = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($plugin, 'validateVersionNumber');
        $method->setAccessible(true);
        $result = $method->invokeArgs($plugin, array($version));
        $this->assertNull($result);
    }

    /**
     * @return array
     */
    public function getValidateVersionValidTestValues()
    {
        return array(
            array('0.0.1'),
            array('1.2.3'),
            array('3.2.1'),
        );
    }

    public function testCreateSoapDataCreatesExpectedOutput()
    {
        $directory = vfsStream::url('temp');
        /** @var \Helhum\TerClient\ExtensionUploadPacker|\PHPUnit_Framework_MockObject_MockObject $packer */
        $packer = $this->getMock(
            'Helhum\\TerClient\\ExtensionUploadPacker',
            array('validateVersionNumber')
        );
        $packer->expects($this->once())->method('validateVersionNumber');
        $result = $packer->pack(basename($directory), $directory,'comment');
        $expected = array(
            'extensionData' => array(
                'extensionKey' => 'temp',
                'version' => '1.2.3-invalid',
                'metaData' => array(
                    'title' => 'Dummy title',
                    'description' => 'Dummy description',
                    'category' => 'misc',
                    'state' => 'beta',
                    'authorName' => 'Author Name',
                    'authorEmail' => 'author@domain.com',
                    'authorCompany' => '',
                ),
                'technicalData' => array(
                    'dependencies' => array(
                         array(
                            'kind' => 'depends',
                            'extensionKey' => 'typo3',
                            'versionRange' => '6.1.0-6.2.99',
                        ),
                         array(
                            'kind' => 'depends',
                            'extensionKey' => 'cms',
                            'versionRange' => '',
                        ),
                         array(
                            'kind' => 'suggests',
                            'extensionKey' => 'news',
                            'versionRange' => '',
                        ),
                    ),
                    'loadOrder' => '',
                    'uploadFolder' => false,
                    'createDirs' => '',
                    'shy' => 0,
                    'modules' => '',
                    'modifyTables' => '',
                    'priority' => '',
                    'clearCacheOnLoad' => false,
                    'lockType' => '',
                    'doNotLoadInFEe' => null,
                    'docPath' => null,
                ),
                'infoData' => array(
                    'codeLines' => 41,
                    'codeBytes' => 868,
                    'codingGuidelinesCompliance' => '',
                    'codingGuidelinesComplianceNotes' => '',
                    'uploadComment' => 'comment',
                    'techInfo' => 'All good, baby',
                ),
            ),
            'filesData' => array(
                 array(
                    'name' => 'ext_emconf.php',
                    'size' => 868,
                    'modificationTime' => self::$mtime,
                    'isExecutable' => 0,
                    'content' => self::$fixtureString,
                    'contentMD5' => '1e9681d65a3de27d2b3ee11b70b052a0',
                ),
            ),
        );
        $this->assertEquals($expected, $result);
    }

    public function testReadExtensionConfigurationFileThrowsExceptionIfFileDoesNotExist()
    {
        $directory = vfsStream::url('doesnotexist');
        $extensionKey = 'temp';
        $packer = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($packer, 'readExtensionConfigurationFile');
        $method->setAccessible(true);
        $this->setExpectedException('RuntimeException');
        $method->invoke($packer, $directory, $extensionKey);
    }

    public function testReadExtensionConfigurationFileThrowsExceptionIfVersionInFileIsInvalid()
    {
        $directory = vfsStream::url('ext_emconf.php');
        $extensionKey = 'temp';
        $packer = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($packer, 'readExtensionConfigurationFile');
        $method->setAccessible(true);
        $this->setExpectedException('RuntimeException');
        $method->invoke($packer, $directory, $extensionKey);
    }

    public function testPack()
    {
        $directory = vfsStream::url('temp');
        $extensionKey = 'temp';
        /** @var \Helhum\TerClient\ExtensionUploadPacker|\PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->getMock(
            'Helhum\\TerClient\\ExtensionUploadPacker',
            array('createFileDataArray', 'createSoapData', 'validateVersionNumber')
        );
        $method = new \ReflectionMethod($mock, 'readExtensionConfigurationFile');
        $method->setAccessible(true);
        $configuration = $method->invoke($mock, $directory, $extensionKey);
        $mock->expects($this->once())->method('createFileDataArray')
            ->with($directory)->will($this->returnValue(array('foo' => 'bar')));
        $mock->expects($this->once())->method('createSoapData')
            ->with($extensionKey, array('foo' => 'bar', 'EM_CONF' => $configuration), 'commentfoo')
            ->will($this->returnValue('test'));
        $result = $mock->pack($extensionKey, $directory, 'commentfoo');
        $this->assertEquals('test', $result);
    }

    public function testCreateFileDataArray()
    {
        $directory = vfsStream::url('temp/');
        $packer = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($packer, 'createFileDataArray');
        $method->setAccessible(true);
        $result = $method->invoke($packer, $directory);
        $this->assertEquals(array('extKey' => 'temp', 'misc' => array('codelines' => 41, 'codebytes' => 868),
            'techInfo' => 'All good, baby', 'FILES' => array('ext_emconf.php' => array('name' => 'ext_emconf.php',
            'size' => 868, 'mtime' => self::$mtime, 'is_executable' => false, 'content' => self::$fixtureString,
            'content_md5' => '1e9681d65a3de27d2b3ee11b70b052a0', 'codelines' => 41, ),
        ), ), $result);
    }

    /**
     * @dataProvider getSettingsAndValues
     * @param array $settings
     * @param string $settingName
     * @param mixed $defaultValue
     * @param mixed $expectedValue
     */
    public function testGetValueOrDefault($settings, $settingName, $defaultValue, $expectedValue)
    {
        $packer = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($packer, 'valueOrDefault');
        $method->setAccessible(true);
        $result = $method->invoke($packer, array('EM_CONF' => $settings), $settingName, $defaultValue);
        $this->assertEquals($expectedValue, $result);
    }

    /**
     * @return array
     */
    public function getSettingsAndValues()
    {
        return array(
            array(array('foo' => 'bar'), 'foo', 'baz', 'bar'),
            array(array('foo' => 'bar'), 'foo2', 'baz', 'baz'),
        );
    }

    /**
     * @dataProvider getExtensionDataAndExpectedDependencyOutput
     * @param string $kindOfDependency
     * @param array $extensionData
     * @param array $expectedOutout
     * @param string $expectedException
     */
    public function testCreateDependenciesArray($kindOfDependency, $extensionData, $expectedOutout, $expectedException)
    {
        $uploader = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($uploader, 'createDependenciesArray');
        $method->setAccessible(true);
        if (null !== $expectedException) {
            $this->setExpectedException($expectedException);
        }
        $output = $method->invoke($uploader, $extensionData, $kindOfDependency);
        $this->assertEquals($expectedOutout, $output);
    }

    /**
     * @return array
     */
    public function getExtensionDataAndExpectedDependencyOutput()
    {
        return array(
            // correct usage and input:
            array(
                ExtensionUploadPacker::KIND_DEPENDENCY,
                array(
                    'EM_CONF' => array(
                        'constraints' => array(
                            ExtensionUploadPacker::KIND_DEPENDENCY => array(
                                'foobar' => '0.0.0-1.0.0',
                                'foobar2' => '1.0.0-2.0.0',
                            ),
                        ),
                    ),
                ),
                array(
                    array('kind' => 'depends', 'extensionKey' => 'foobar', 'versionRange' => '0.0.0-1.0.0'),
                    array('kind' => 'depends', 'extensionKey' => 'foobar2', 'versionRange' => '1.0.0-2.0.0'),
                ),
                null,
            ),
            // no deps: empty output, no error
            array(
                ExtensionUploadPacker::KIND_DEPENDENCY,
                array('EM_CONF' => array()),
                array(),
                null,
            ),
            // deps setting not an array, empty output, no error
            array(
                ExtensionUploadPacker::KIND_DEPENDENCY,
                array('EM_CONF' => array('constraints' => array(ExtensionUploadPacker::KIND_DEPENDENCY => 'iamastring'))),
                array(),
                null,
            ),
            // deps numerically indexed - error!
            array(
                ExtensionUploadPacker::KIND_DEPENDENCY,
                array(
                    'EM_CONF' => array(
                        'constraints' => array(
                            ExtensionUploadPacker::KIND_DEPENDENCY => array(0 => array('0.0.0-1.0.0')),
                        ),
                    ),
                ),
                array(),
                'RuntimeException',
            ),
        );
    }

    /**
     * @dataProvider getIsFilePermittedTestValues
     * @param \SplFileInfo $file
     * @param string $inPath
     * @param bool $expectedPermitted
     */
    public function testIsFilePermitted(\SplFileInfo $file, $inPath, $expectedPermitted)
    {
        $instance = new ExtensionUploadPacker();
        $method = new \ReflectionMethod($instance, 'isFilePermitted');
        $method->setAccessible(true);
        $result = $method->invokeArgs($instance, array($file, $inPath));
        $this->assertEquals($expectedPermitted, $result);
    }

    /**
     * @return array
     */
    public function getIsFilePermittedTestValues()
    {
        return array(
            array(new \SplFileInfo('/path/file'), '/path', true),
            array(new \SplFileInfo('/path/.file'), '/path', false),
            array(new \SplFileInfo('/path/.htaccess'), '/path', true),
            array(new \SplFileInfo('/path/.htpasswd'), '/path', true),
            array(new \SplFileInfo('/.git/file'), '/.git', true),
            array(new \SplFileInfo('/.git/.dotfile'), '/.git', false),
            array(new \SplFileInfo('/.git/.htaccess'), '/.git', true),
            array(new \SplFileInfo('/.git/.htpasswd'), '/.git', true),
            array(new \SplFileInfo('/path/.git/file'), '/path', false),
        );
    }
}
