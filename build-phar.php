<?php
$buildRoot = __DIR__;

$phar = new Phar($buildRoot . '/dist/git-manager.phar', 0, 'git-manager.phar');

$phar->startBuffering();

$include = '/^(?=(.*src|.*config|.*bin|.*vendor))(.*)$/i';
$phar->buildFromDirectory($buildRoot, $include);

/* avoid first line display */
$content = file_get_contents(__DIR__.'/bin/console');
$content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
$phar->addFromString('bin/console',$content);

$phar->setStub("#!/usr/bin/env php\n".$phar->createDefaultStub("bin/console"));
$phar->stopBuffering();
