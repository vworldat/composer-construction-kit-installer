<?php

namespace C33s\ConstructionKit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Package\AliasPackage;

class ConstructionKitPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $rootPackage = $composer->getPackage();
        if (isset($rootPackage))
        {
            // Ensure we get the root package rather than its alias.
            while ($rootPackage instanceof AliasPackage)
            {
                $rootPackage = $rootPackage->getAliasOf();
            }

            // Make sure the root package can override the available scripts.
            if (method_exists($rootPackage, 'setScripts'))
            {
                $scripts = $rootPackage->getScripts();
                // Act on the "post-autoload-dump" command so that we can act on all
                // the installed packages.
                $scripts['post-autoload-dump']['c33s-construction-kit-installer'] = 'C33s\\ConstructionKit\\ConstructionKitScriptHandler::postAutoloadDump';
                $rootPackage->setScripts($scripts);
            }
        }
    }
}
