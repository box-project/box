<?php declare(strict_types=1);











namespace Composer\Plugin\Capability;











interface CommandProvider extends Capability
{





public function getCommands();
}
