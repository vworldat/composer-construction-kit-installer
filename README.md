Composer plugin for C33sConstructionKitBundle
=============================================

[![Build Status](https://travis-ci.org/vworldat/composer-construction-kit-installer.svg)](https://travis-ci.org/vworldat/composer-construction-kit-installer)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/511321d7-fbbe-4108-8e35-895099539198/mini.png)](https://insight.sensiolabs.com/projects/511321d7-fbbe-4108-8e35-895099539198)
[![codecov.io](http://codecov.io/github/vworldat/composer-construction-kit-installer/coverage.svg?branch=master)](http://codecov.io/github/vworldat/composer-construction-kit-installer?branch=master)

This composer plugin detects installed packages that contain a `c33s-building-blocks` extra and lists those blocks
inside `{$appDir}/config/config/c33s_construction_kit.composer.yml`.

This is used by [c33s/construction-kit-bundle](https://packagist.org/packages/c33s/construction-kit-bundle).

Updating manually
-----------------

To manually trigger the update run `composer run-script post-update-cmd`.

Disable the plugin
------------------

To completely disable the automatic detection of changes, add the `extra` value `c33s-construction-kit-disabled: true` to your `composer.json`.
