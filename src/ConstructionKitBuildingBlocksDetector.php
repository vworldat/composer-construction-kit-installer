<?php

namespace C33s\ConstructionKit;

use Composer\Package\Dumper\ArrayDumper;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * This class is loosely based on Sensio\Bundle\DistributionBundle\Composer\ScriptHandler.
 */
class ConstructionKitBuildingBlocksDetector
{
    /**
     * @var Event
     */
    protected $event;

    /**
     * @var string
     */
    protected $appDir;

    /**
     * @var bool
     */
    protected $disabled = false;

    /**
     * Performs building blocks scan and config update.
     *
     * @param $this->event Event A instance
     */
    public static function refreshBuildingBlocks(Event $event)
    {
        $detector = new static($event);

        $detector->updateBuildingBlocksList();
    }

    /**
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
        $this->init();
    }

    protected function init()
    {
        $options = array(
            'symfony-app-dir' => 'app',
            'c33s-construction-kit-disabled' => false,
        );
        $options = array_merge($options, $this->event->getComposer()->getPackage()->getExtra());

        $this->appDir = $options['symfony-app-dir'];
        $this->disabled = $options['c33s-construction-kit-disabled'];
    }

    /**
     * Check various settings before performing any updates.
     *
     * @return bool
     */
    protected function checkSettings()
    {
        $io = $this->event->getIO();

        if ($this->disabled) {
            $io->write('<info>[C33sConstructionKitBundle] C33sConstructionKit is disabled</info>');

            return false;
        }

        if (!is_dir($this->appDir)) {
            $currentDir = getcwd();
            $io->write("<warning>[C33sConstructionKitBundle] The symfony-app-dir {$this->appDir} specified in composer.json was not found in {$currentDir}. Cannot generate building blocks file.</warning>");

            return false;
        }

        return true;
    }

    /**
     * Update the list of building blocks provided by any installed composer packages.
     * The list will be stored in YAML format inside {$appDir}/config/config/c33s_construction_kit.composer.yml
     * and automatically imported by C33sConstructionKitBundle once the bundle commands are used.
     */
    public function updateBuildingBlocksList()
    {
        if (!$this->checkSettings()) {
            return;
        }

        $this->event->getIO()->write('<info>[C33sConstructionKitBundle] Searching for building blocks in installed composer packages</info>');

        $packages = $this->getPackagesData();

        $blocks = array();
        $blocksByPackage = array();
        foreach ($packages as $package) {
            foreach ($package['extra']['c33s-building-blocks'] as $block) {
                $blocksByPackage[$package['name']][] = $block;
                $blocks[] = $block;
            }

            sort($blocksByPackage[$package['name']]);
        }
        ksort($blocksByPackage);
        sort($blocks);

        $fs = new Filesystem();
        $filename = $this->getFilename();

        $existingConfig = array();
        if ($fs->exists($filename)) {
            $existingConfig = Yaml::parse(file_get_contents($filename));
        }

        $this->listChanges($existingConfig, $blocks);
        $this->storeConfig($blocksByPackage);
    }

    /**
     * Display information regarding changed building blocks.
     *
     * @param mixed $existingConfig
     * @param mixed $blocks
     */
    protected function listChanges($existingConfig, array $blocks)
    {
        $existingBlocks = array();
        if (isset($existingConfig['c33s_construction_kit']['composer_building_blocks']) && is_array($existingConfig['c33s_construction_kit']['composer_building_blocks'])) {
            foreach ($existingConfig['c33s_construction_kit']['composer_building_blocks'] as $packageBlocks) {
                if (is_array($packageBlocks)) {
                    $existingBlocks = array_merge($existingBlocks, $packageBlocks);
                }
            }
        }

        $added = array_diff($blocks, $existingBlocks);
        $removed = array_diff($existingBlocks, $blocks);

        if (count($added)) {
            $this->event->getIO()->write('<info>[C33sConstructionKitBundle] Found new building blocks:</info>');
            $added = array_map(function ($block) {
                return '  - '.$block;
            }, $added);
            $this->event->getIO()->write($added);
        }

        if (count($removed)) {
            $this->event->getIO()->write('<info>[C33sConstructionKitBundle] The following building blocks have been removed:</info>');
            $removed = array_map(function ($block) {
                return '  - '.$block;
            }, $removed);
            $this->event->getIO()->write($removed);
        }
    }

    /**
     * Get active non-dev packages from composer's locker that include an c33s-building-blocks extra array.
     *
     * @return array
     */
    protected function getPackagesData()
    {
        $composer = $this->event->getComposer();
        $locker = $composer->getLocker();
        if (isset($locker)) {
            $lockData = $locker->getLockData();
            $allPackages = isset($lockData['packages']) ? $lockData['packages'] : array();
        }

        $packages = array();
        // Only add those packages that we can reasonably
        // assume are components into our packages list
        foreach ($allPackages as $package) {
            if (isset($package['extra']['c33s-building-blocks']) && is_array($package['extra']['c33s-building-blocks'])) {
                $packages[] = $package;
            }
        }

        // Add the root package to the packages list.
        $root = $composer->getPackage();
        if ($root) {
            $dumper = new ArrayDumper();
            $package = $dumper->dump($root);

            if (isset($package['extra']['c33s-building-blocks']) && is_array($package['extra']['c33s-building-blocks'])) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * Get the filename to use for storing the blocks information.
     *
     * @return string
     */
    protected function getFilename()
    {
        return $this->appDir.'/config/config/c33s_construction_kit.composer.yml';
    }

    /**
     * Save new blocks config to file.
     *
     * @param array $blocks
     */
    protected function storeConfig(array $blocksByPackage)
    {
        $content = "# This file is auto-generated by c33s/composer-construction-kit-installer\n# upon each composer dump-autoload event\n";
        $content .= Yaml::dump(array(
            'c33s_construction_kit' => array(
                'composer_building_blocks' => $blocksByPackage,
            ),
        ), 4);

        $fs = new Filesystem();
        $fs->dumpFile($this->getFilename(), $content);
    }
}
