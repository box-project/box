<?php declare(strict_types=1);











namespace Composer\Installer;

use Composer\Package\PackageInterface;






interface BinaryPresenceInterface
{







public function ensureBinariesPresence(PackageInterface $package);
}
