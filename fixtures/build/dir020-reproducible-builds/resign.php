<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Seld\PharUtils\Timestamps;

$file = getcwd() . '/' . ($argv[1] ?? '');
if (!is_file($file)) {
    echo "File does not exist.\n";
    exit(1);
}

$util = new Timestamps($file);
$util->updateTimestamps(new DateTimeImmutable('2017-10-11 08:58:00'));
$util->save($file, Phar::SHA512);