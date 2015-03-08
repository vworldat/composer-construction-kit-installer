<?php

namespace C33s\ConstructionKit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

class ConstructionKitInstaller extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return false;
    }
}
