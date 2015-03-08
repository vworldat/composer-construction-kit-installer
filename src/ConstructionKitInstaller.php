<?php

namespace C33s\ConstructionKit;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;

class ConstructionKitInstaller extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     *
     * Construction blocks are supported by all packages. This checks wheteher or not the
     * entire package is a "component", as well as injects the script to act
     * on components embedded in packages that are not just "component" types.
     */
    public function supports($packageType)
    {
        // Components are supported by all package types. We will just act on
        // the root package's scripts if available.
        $rootPackage = $this->composer->getPackage();
        if (isset($rootPackage)) {
            // Ensure we get the root package rather than its alias.
            while ($rootPackage instanceof AliasPackage) {
                $rootPackage = $rootPackage->getAliasOf();
            }

            // Make sure the root package can override the available scripts.
            if (method_exists($rootPackage, 'setScripts')) {
                $scripts = $rootPackage->getScripts();
                // Act on the "post-autoload-dump" command so that we can act on all
                // the installed packages.
                $scripts['post-autoload-dump']['c33s-construction-kit-installer'] = 'C33s\\ConstructionKit\\ConstructionKitInstaller::postAutoloadDump';
                $rootPackage->setScripts($scripts);
            }
        }

        // Explicitly state support of "symfony-bundle" packages.
        return $packageType === 'c33s-construction-block';
    }

    /**
     * Script callback; Acted on after the autoloader is dumped.
     */
    public static function postAutoloadDump(Event $event)
    {
        // Retrieve basic information about the environment and present a
        // message to the user.
        $composer = $event->getComposer();
        $io = $event->getIO();
        $io->write('<info>Retrieving construction blocks</info>');

        return;
        // Set up all the processes.
        $processes = array(
            // Copy the assets to the Components directory.
            "ComponentInstaller\\Process\\CopyProcess",
            // Build the require.js file.
            "ComponentInstaller\\Process\\RequireJsProcess",
            // Build the require.css file.
            "ComponentInstaller\\Process\\RequireCssProcess",
            // Compile the require-built.js file.
            "ComponentInstaller\\Process\\BuildJsProcess",
        );

        // Initialize and execute each process in sequence.
        foreach ($processes as $class) {
            $process = new $class($composer, $io);
            // When an error occurs during initialization, end the process.
            if (!$process->init()) {
                $io->write('<error>An error occurred while initializing the process.</info>');
                break;
            }
            $process->process();
        }
    }
}
