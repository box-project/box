<?php declare(strict_types=1);











namespace Composer\Package\Loader;

use Composer\Package\CompletePackage;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\RootAliasPackage;
use Composer\Package\RootPackage;
use Composer\Package\BasePackage;






interface LoaderInterface
{










public function load(array $config, string $class = 'Composer\Package\CompletePackage'): BasePackage;
}
