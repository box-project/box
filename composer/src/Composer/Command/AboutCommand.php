<?php declare(strict_types=1);











namespace Composer\Command;

use Composer\Composer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;




class AboutCommand extends BaseCommand
{
protected function configure(): void
{
$this
->setName('about')
->setDescription('Shows a short information about Composer')
->setHelp(
<<<EOT
<info>php composer.phar about</info>
EOT
)
;
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
$composerVersion = Composer::getVersion();

$this->getIO()->write(
<<<EOT
<info>Composer - Dependency Manager for PHP - version $composerVersion</info>
<comment>Composer is a dependency manager tracking local dependencies of your projects and libraries.
See https://getcomposer.org/ for more information.</comment>
EOT
);

return 0;
}
}
