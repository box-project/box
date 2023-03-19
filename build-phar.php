<?php

@unlink('demo.phar');
$phar = new Phar('demo.phar');
$phar->addFile('composer.json');
$phar->addFile('composer.lock');
$phar->compressFiles(Phar::BZ2);

$x = new PharFileInfo('phar://demo.phar/composer.lock');
$x->compress(Phar::GZ);
