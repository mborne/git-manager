<?php
$buildRoot = __DIR__;

$phar = new Phar($buildRoot . '/dist/git-manager.phar', 0, 'git-manager.phar');

$include = '/^(?=(.*src|.*app|.*web|.*bin|.*vendor))(.*)$/i';

$phar->buildFromDirectory($buildRoot, $include);
$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub("bin/console"));

