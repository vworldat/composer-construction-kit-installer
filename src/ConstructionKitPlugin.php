<?php

namespace C33s\ConstructionKit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Plugin\PluginInterface;

class ConstructionKitPlugin implements PluginInterface
{
    /**
     * Apply plugin modifications to composer.
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $rootPackage = $composer->getPackage();
        if (isset($rootPackage)) {
            // Ensure we get the root package rather than its alias.
            while ($rootPackage instanceof AliasPackage) {
                $rootPackage = $rootPackage->getAliasOf();
            }

            // Make sure the root package can override the available scripts.
            if (method_exists($rootPackage, 'setScripts')) {
                $scripts = $rootPackage->getScripts();
                $scripts['post-update-cmd']['c33s-construction-kit-installer'] = 'C33s\ConstructionKit\ConstructionKitBuildingBlocksDetector::refreshBuildingBlocks';
                $rootPackage->setScripts($scripts);
            }
        }
    }
}
