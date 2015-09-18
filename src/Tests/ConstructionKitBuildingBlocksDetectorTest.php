<?php

namespace C33s\ConstructionKit\Tests;

use C33s\ConstructionKit\ConstructionKitBuildingBlocksDetector;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class ConstructionKitBuildingBlocksDetectorTest extends \PHPUnit_Framework_TestCase
{
    protected $testFile;

    protected function tearDown()
    {
        $fs = new Filesystem();
        if (null !== $this->testFile && $fs->exists($this->testFile)) {
            $fs->remove($this->testFile);
        }
    }

    /**
     * @test
     */
    public function initSetsAppDirAndDisabled()
    {
        $composer = new Composer();
        $package = new RootPackage('test-root', '1.0', '1.0');
        $package->setExtra(array(
            'symfony-app-dir' => 'testAppDir',
            'c33s-construction-kit-disabled' => 'test-disabled',
        ));
        $composer->setPackage($package);
        $event = new Event('somename', $composer, new NullIO());

        $detector = new ConstructionKitBuildingBlocksDetector($event);

        $class = new \ReflectionClass($detector);
        $appDir = $class->getProperty('appDir');
        $appDir->setAccessible(true);

        $this->assertSame('testAppDir', $appDir->getValue($detector));

        $disabled = $class->getProperty('disabled');
        $disabled->setAccessible(true);

        $this->assertSame('test-disabled', $disabled->getValue($detector));
    }

    /**
     * @test
     * @dataProvider provideExtraToNotLoadData
     */
    public function doesNotLoad($extra)
    {
        $event = $this->getScriptEvent($extra);

        $detector = $this
            ->getMockBuilder('C33s\ConstructionKit\ConstructionKitBuildingBlocksDetector')
            ->setMethods(array('getPackagesData', 'storeConfig'))
            ->setConstructorArgs(array($event))
            ->getMock()
        ;
        $detector
            ->expects($this->never())
            ->method('getPackagesData')
        ;

        $detector->updateBuildingBlocksList();
    }

    public function provideExtraToNotLoadData()
    {
        return array(
            array(
                array(
                    'c33s-construction-kit-disabled' => true,
                    'symfony-app-dir' => __DIR__.'/Fixtures/app',
                ),
            ),
            array(
                array(
                    'c33s-construction-kit-disabled' => false,
                    'symfony-app-dir' => __DIR__.'/this/directory/does-not-exist',
                ),
            ),
        );
    }

    /**
     * @test
     */
    public function getPackagesDataFindsPackagesAndRootPackage()
    {
        $rootPackageExtra = array(
            'c33s-building-blocks' => array(
                'Root\Block1',
                'Root\Block2',
            ),
            'symfony-app-dir' => __DIR__.'/Fixtures/app',
        );
        $lockerPackages = array(
            array(
                'name' => 'no-building-blocks',
            ),
            array(
                'name' => 'package-contains',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'P1\Block1',
                        'P1\Block2',
                    ),
                ),
            ),
            array(
                'name' => 'other-package',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'Block',
                    ),
                ),
            ),
        );

        $event = $this->getScriptEvent($rootPackageExtra, $lockerPackages);
        $detector = $this->getDetector($event);

        $data = $this->callProtectedMethod($detector, 'getPackagesData');

        $expectedPackages = array(
            array(
                'name' => 'package-contains',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'P1\Block1',
                        'P1\Block2',
                    ),
                ),
            ),
            array(
                'name' => 'other-package',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'Block',
                    ),
                ),
            ),
            array(
                'name' => 'test-root',
                'version' => '1.0',
                'version_normalized' => '1.0',
                'type' => 'library',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'Root\Block1',
                        'Root\Block2',
                    ),
                    'symfony-app-dir' => __DIR__.'/Fixtures/app',
                ),
                'minimum-stability' => 'stable',
            ),
        );
        $this->assertEquals($expectedPackages, $data);
    }

    /**
     * @test
     */
    public function updateBuildingBlocksListFindsBlocks()
    {
        $rootPackageExtra = array(
            'c33s-building-blocks' => array(
                'Root\Block1',
                'Root\Block2',
            ),
            'symfony-app-dir' => __DIR__.'/Fixtures/app',
        );
        $lockerPackages = array(
            array(
                'name' => 'no-building-blocks',
            ),
            array(
                'name' => 'package-contains',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'P1\Block1',
                        'P1\Block2',
                    ),
                ),
            ),
            array(
                'name' => 'other-package',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'Block',
                    ),
                ),
            ),
        );

        $event = $this->getScriptEvent($rootPackageExtra, $lockerPackages);
        $detector = $this->getDetector($event);

        $expectedBlocks = array(
            'other-package' => array(
                'Block',
            ),
            'package-contains' => array(
                'P1\Block1',
                'P1\Block2',
            ),
            'test-root' => array(
                'Root\Block1',
                'Root\Block2',
            ),
        );

        $detector
            ->expects($this->once())
            ->method('storeConfig')
            ->with($expectedBlocks)
        ;

        $detector->updateBuildingBlocksList();
    }

    /**
     * @test
     */
    public function updateBuildingBlocksOutputsChanges()
    {
        $rootPackageExtra = array(
            'symfony-app-dir' => __DIR__.'/Fixtures/app',
        );
        $lockerPackages = array(
            array(
                'name' => 'package-contains',
                'extra' => array(
                    'c33s-building-blocks' => array(
                        'New\Block',
                    ),
                ),
            ),
        );

        // this test is too specific, but I don't know what else to do about it
        $collectedOutput = '';
        $io = $this->getMock('Composer\IO\IOInterface');
        $io
            ->expects($this->any())
            ->method('write')
            ->with($this->callback(function ($content) use (&$collectedOutput) {
                if (is_array($content)) {
                    $collectedOutput .= implode("\n", $content)."\n";
                } else {
                    $collectedOutput .= $content."\n";
                }

                return true;
            }))
        ;

        $event = $this->getScriptEvent($rootPackageExtra, $lockerPackages, $io);

        $detector = $this->getDetector($event);
        $detector->updateBuildingBlocksList();

        $format = <<<EOF
%sSearching%s
%sFound new%s
  - New\Block
%sremoved%s
  - C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock
  - C33s\SimpleContentBundle\BuildingBlock\SimpleContentBuildingBlock
EOF;

        $this->assertStringMatchesFormat($format, $collectedOutput);
    }

    /**
     * @test
     */
    public function storeFileWritesFileWithContent()
    {
        $fixtureFile = __DIR__.'/Fixtures/app/config/config/c33s_construction_kit.composer.yml';
        $this->testFile = __DIR__.'/Fixtures/app/config/config/c33s_construction_kit.composer.yml.test';

        $rootPackageExtra = array(
            'symfony-app-dir' => __DIR__.'/Fixtures/app',
        );

        $fs = new Filesystem();
        if ($fs->exists($this->testFile)) {
            $fs->remove($this->testFile);
        }

        $event = $this->getScriptEvent($rootPackageExtra);
        $detector = $this
            ->getMockBuilder('C33s\ConstructionKit\ConstructionKitBuildingBlocksDetector')
            ->setMethods(array('getFilename'))
            ->setConstructorArgs(array($event))
            ->getMock()
        ;
        $detector
            ->expects($this->once())
            ->method('getFilename')
            ->willReturn($this->testFile)
        ;

        $this->callProtectedMethod($detector, 'storeConfig', array(array(
            'c33s/construction-kit-bundle' => array(
                'C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock',
            ),
            'c33s/simple-content-bundle' => array(
                'C33s\SimpleContentBundle\BuildingBlock\SimpleContentBuildingBlock',
            ),
        )));

        $this->assertFileExists($this->testFile);
        $this->assertFileEquals($fixtureFile, $this->testFile);
    }

    /**
     * @test
     */
    public function staticCallRunsUpdate()
    {
        $rootPackageExtra = array(
            'c33s-construction-kit-disabled' => true,
            'symfony-app-dir' => __DIR__.'/does/not/exist',
        );
        $lockerPackages = array();

        // this test is too specific, but I don't know what else to do about it
        $io = $this->getMock('Composer\IO\IOInterface');
        $io
            ->expects($this->once())
            ->method('write')
            ->with($this->stringContains('C33sConstructionKit is disabled'))
        ;

        $event = $this->getScriptEvent($rootPackageExtra, $lockerPackages, $io);
        ConstructionKitBuildingBlocksDetector::refreshBuildingBlocks($event);
    }

    /**
     * Call the given protected method on the given object using the given arguments.
     *
     * @param object $object
     * @param string $methodName
     * @param array  $args
     *
     * @return mixed
     */
    protected function callProtectedMethod($object, $methodName, array $args = array())
    {
        $class = new \ReflectionClass($object);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    /**
     * Get basic Event to use for testing.
     *
     * @return \Composer\Plugin\Event
     */
    protected function getScriptEvent($rootPackageExtra, array $lockerPackages = array(), IOInterface $io = null)
    {
        $composer = new Composer();
        if (null === $io) {
            $io = new NullIO();
        }

        $locker = $this
            ->getMockBuilder('Composer\Package\Locker')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $locker
            ->expects($this->any())
            ->method('getLockData')
            ->willReturn(array(
                'packages' => $lockerPackages,
            ))
        ;

        $composer->setLocker($locker);

        $package = new RootPackage('test-root', '1.0', '1.0');
        $package->setExtra($rootPackageExtra);
        $composer->setPackage($package);

        return new Event('somename', $composer, $io);
    }

    /**
     * Get mocked detector that will not write files.
     *
     * @param Event $event
     *
     * @return ConstructionKitBuildingBlocksDetector
     */
    protected function getDetector(Event $event)
    {
        $detector = $this
            ->getMockBuilder('C33s\ConstructionKit\ConstructionKitBuildingBlocksDetector')
            ->setMethods(array('storeConfig'))
            ->setConstructorArgs(array($event))
            ->getMock()
        ;

        return $detector;
    }
}
