<?php

namespace C33s\ConstructionKit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\CommandEvent;
use Symfony\Component\ClassLoader\ClassCollectionLoader;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Composer\Package\Dumper\ArrayDumper;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Symfony\Component\Yaml\Yaml;
use Composer\Package\Package;

class ConstructionKitScriptHandler extends ScriptHandler
{
    /**
     * Composer variables are declared static so that an event could update
     * a composer.json and set new options, making them immediately available
     * to forthcoming listeners.
     */
    protected static $options = array(
        'symfony-app-dir' => 'app',
        'symfony-web-dir' => 'web',
        'symfony-assets-install' => 'hard',
        'symfony-cache-warmup' => false,
        'c33s-construction-kit-auto-install' => true,
        'c33s-construction-kit-disabled' => false,
    );

    /**
     * Performs building blocks scan and config update
     *
     * @param $event CommandEvent A instance
     */
    public static function refreshBuildingBlocks(CommandEvent $event)
    {
        $options = static::getOptions($event);
        $io = $event->getIO();

        if($options['c33s-construction-kit-disabled'])
        {
            $io->write('<info>[C33sConstructionKitBundle] C33sConstructionKit is disabled</info>');
            return;
        }

        $appDir = $options['symfony-app-dir'];
        if (!is_dir($appDir))
        {
            $io->write('<error>[C33sConstructionKitBundle] The symfony-app-dir ('.$appDir.') specified in composer.json was not found in '.getcwd().', can not build building blocks file.</error>'.PHP_EOL);

            return;
        }

        static::addConstructionKitBundleToKernel($event, $appDir);
        static::executeRefreshConfig($event);
        static::updateBuildingBlocksList($event, $appDir);
        static::executeUpdateBlocks($event);
    }

    protected static function updateBuildingBlocksList(CommandEvent $event, $appDir)
    {
        $event->getIO()->write('<info>[C33sConstructionKitBundle] Searching for building blocks in installed composer packages</info>');

        $packages = static::getPackagesData($event->getComposer());
        $blocks = array();
        foreach ($packages as $package)
        {
            foreach ($package['extra']['c33s-building-blocks'] as $block)
            {
                $blocks[$package['name']][] = $block;
            }
        }

        $content = "# This file is auto-generated by c33s/composer-construction-kit-installer\n# upon each composer dump-autoload event\n";
        $content .= Yaml::dump(array(
            'c33s_construction_kit' => array(
                'composer_building_blocks' => $blocks,
            ),
        ), 4);

        file_put_contents($appDir.'/config/config/c33s_construction_kit.composer.yml', $content);
    }

    /**
     * Check if the AppKernel already includes C33sConstructionKitBundle by booting it and checking for the bundle.
     * If the bundle was not registered yet, add it using the KernelManipulator.
     *
     * @param string $appDir
     */
    protected static function addConstructionKitBundleToKernel(CommandEvent $event, $appDir)
    {
        require_once($appDir.'/AppKernel.php');

        $kernel = new \AppKernel('prod', false);
        try
        {
            $manipulator = new KernelManipulator($kernel);
            $manipulator->addBundle('C33s\\ConstructionKitBundle\\C33sConstructionKitBundle');

            $event->getIO()->write("<info>Added C33sConstructionKitBundle to {$appDir}/AppKernel.php</info>");
        }
        catch (\RuntimeException $e)
        {
        }
    }

    protected static function executeRefreshConfig(CommandEvent $event)
    {
        $event->getIO()->write('<info>[C33sConstructionKitBundle] Refreshing config files, splitting into groups</info>');

        $options = static::getOptions($event);
        $consoleDir = self::getConsoleDir($event, 'refresh the construction kit config');

        if (null === $consoleDir)
        {
            return;
        }

        static::executeCommandSafely($event, $consoleDir, 'construction-kit:refresh-config --add-config c33s_construction_kit.composer', $options['process-timeout']);
    }

    protected static function executeUpdateBlocks(CommandEvent $event)
    {
        $event->getIO()->write('<info>[C33sConstructionKitBundle] Updating building blocks, enabling new blocks in Symfony project</info>');

        $options = static::getOptions($event);
        $consoleDir = self::getConsoleDir($event, 'validate the building blocks list');

        if (null === $consoleDir)
        {
            return;
        }

        $noAutoInstall = '';
        if (!$options['c33s-construction-kit-auto-install'])
        {
            $noAutoInstall = ' --no-auto-install';
        }

        static::executeCommandSafely($event, $consoleDir, 'construction-kit:update-blocks'.$noAutoInstall, $options['process-timeout']);
    }

    /**
     * Get active non-dev packages from the given Composer's locker that include c33s-building-blocks extra array.
     *
     * @param Composer $composer
     *
     * @return array
     */
    protected static function getPackagesData(Composer $composer)
    {
        $locker = $composer->getLocker();
        if (isset($locker)) {
            $lockData = $locker->getLockData();
            $allPackages = isset($lockData['packages']) ? $lockData['packages'] : array();
        }

        $packages = array();
        // Only add those packages that we can reasonably
        // assume are components into our packages list
        foreach ($allPackages as $package) {
            $extra = isset($package['extra']) ? $package['extra'] : array();
            if (isset($extra['c33s-building-blocks']) && is_array($extra['c33s-building-blocks']))
            {
                $packages[] = $package;
            }
        }

        // Add the root package to the packages list.
        $root = $composer->getPackage();
        if ($root) {
            $dumper = new ArrayDumper();
            $package = $dumper->dump($root);
            $package['is-root'] = true;

            if (isset($package['extra']['c33s-building-blocks']) && is_array($package['extra']['c33s-building-blocks']))
            {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * This method's catch block will be called if executing the given command fails. Since a stack trace to this script
     * does not help anyone, we drop it and display a meaningful message instead.
     *
     * It also adds the current verbosity level to the called command.
     *
     * @param CommandEvent $event
     * @param string $consoleDir
     * @param string $cmd
     * @param number $timeout
     */
    protected static function executeCommandSafely(CommandEvent $event, $consoleDir, $cmd, $timeout = 300)
    {
        $v = '';
        if ($event->getIO()->isVerbose())
        {
            $v = ' -v';
        }
        elseif ($event->getIO()->isVeryVerbose())
        {
            $v = ' -vv';
        }

        try
        {
            return static::executeCommand($event, $consoleDir, $cmd.$v, $timeout);
        }
        catch (\RuntimeException $e)
        {
            $event->getIO()->write("<comment>--------------------------------------------------------------------------------------------</comment>");
            $event->getIO()->write("<comment>  The command '$cmd' exited with an error.</comment>");
            $event->getIO()->write("<comment>  If you keep getting errors consider temporarily deactivating automatic package scanning</comment>");
            $event->getIO()->write("<comment>  by setting extra 'c33s-construction-kit-disabled': true in your composer.json</comment>");
            $event->getIO()->write("<comment>--------------------------------------------------------------------------------------------</comment>\n");

            die();
        }
    }
}
