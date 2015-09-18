<?php

namespace C33s\ConstructionKit\Tests;

use C33s\ConstructionKit\ConstructionKitPlugin;
use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootAliasPackage;
use Composer\Package\RootPackage;

class ConstructionKitPluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function scriptIsAddedToUpdateScriptsOfRootPackage()
    {
        $composer = new Composer();
        $package = new RootPackage('test', '1.0', '1.0');
        $composer->setPackage($package);

        $plugin = new ConstructionKitPlugin();
        $plugin->activate($composer, new NullIO());

        $expectedScripts = array(
            'post-update-cmd' => array(
                'c33s-construction-kit-installer' => 'C33s\ConstructionKit\ConstructionKitBuildingBlocksDetector::refreshBuildingBlocks',
            ),
        );
        $this->assertSame($expectedScripts, $package->getScripts());
    }

    /**
     * @test
     */
    public function scriptIsAddedToUpdateScriptsOfAliasPackage()
    {
        $composer = new Composer();
        $package = new RootPackage('test', '1.0', '1.0');

        $alias = new RootAliasPackage($package, '0.9', '0.9');
        $alias2 = new RootAliasPackage($alias, '0.8', '0.8');
        $composer->setPackage($alias2);

        $plugin = new ConstructionKitPlugin();
        $plugin->activate($composer, new NullIO());

        $expectedScripts = array(
            'post-update-cmd' => array(
                'c33s-construction-kit-installer' => 'C33s\ConstructionKit\ConstructionKitBuildingBlocksDetector::refreshBuildingBlocks',
            ),
        );
        $this->assertSame($expectedScripts, $package->getScripts());
    }
}
