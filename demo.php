<?php

declare(strict_types=1);

$p = new Phar('demo.phar');
$p['myfile.txt'] = 'hi';
$p->compressFiles(Phar::GZ);

unset($p);

$p = new Phar('demo.phar');

var_dump($p);

foreach ($p as $file) {
    var_dump($file->getFileName());
    var_dump($file->isCompressed());
    var_dump($file->isCompressed(Phar::BZ2));
    var_dump($file->isCompressed(Phar::GZ));
    var_dump($file->getPathname());
    var_dump(file_get_contents($file->getPathname()));
}
$p->decompressFiles();
foreach ($p as $file) {
    var_dump($file->getFileName());
    var_dump($file->isCompressed());
    var_dump($file->isCompressed(Phar::BZ2));
    var_dump($file->isCompressed(Phar::GZ));
    var_dump($file->getPathname());
    var_dump(file_get_contents($file->getPathname()));
}