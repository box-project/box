<?php declare(strict_types=1);











namespace Composer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;




class DependsCommand extends BaseDependencyCommand
{
use CompletionTrait;




protected function configure(): void
{
$this
->setName('depends')
->setAliases(['why'])
->setDescription('Shows which packages cause the given package to be installed')
->setDefinition([
new InputArgument(self::ARGUMENT_PACKAGE, InputArgument::REQUIRED, 'Package to inspect', null, $this->suggestInstalledPackage(true, true)),
new InputOption(self::OPTION_RECURSIVE, 'r', InputOption::VALUE_NONE, 'Recursively resolves up to the root package'),
new InputOption(self::OPTION_TREE, 't', InputOption::VALUE_NONE, 'Prints the results as a nested tree'),
new InputOption('locked', null, InputOption::VALUE_NONE, 'Read dependency information from composer.lock'),
])
->setHelp(
<<<EOT
Displays detailed information about where a package is referenced.

<info>php composer.phar depends composer/composer</info>

Read more at https://getcomposer.org/doc/03-cli.md#depends-why
EOT
)
;
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
return parent::doExecute($input, $output);
}
}
