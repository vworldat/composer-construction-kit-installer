<?php

namespace C33s\ConstructionKit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class ConstructionKitPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new ConstructionKitInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
