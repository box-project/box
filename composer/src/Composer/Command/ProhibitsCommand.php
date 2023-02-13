<?php declare(strict_types=1);











namespace Composer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;




class ProhibitsCommand extends BaseDependencyCommand
{
use CompletionTrait;




protected function configure(): void
{
$this
->setName('prohibits')
->setAliases(['why-not'])
->setDescription('Shows which packages prevent the given package from being installed')
->setDefinition([
new InputArgument(self::ARGUMENT_PACKAGE, InputArgument::REQUIRED, 'Package to inspect', null, $this->suggestAvailablePackage()),
new InputArgument(self::ARGUMENT_CONSTRAINT, InputArgument::REQUIRED, 'Version constraint, which version you expected to be installed'),
new InputOption(self::OPTION_RECURSIVE, 'r', InputOption::VALUE_NONE, 'Recursively resolves up to the root package'),
new InputOption(self::OPTION_TREE, 't', InputOption::VALUE_NONE, 'Prints the results as a nested tree'),
new InputOption('locked', null, InputOption::VALUE_NONE, 'Read dependency information from composer.lock'),
])
->setHelp(
<<<EOT
Displays detailed information about why a package cannot be installed.

<info>php composer.phar prohibits composer/composer</info>

Read more at https://getcomposer.org/doc/03-cli.md#prohibits-why-not
EOT
)
;
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
return parent::doExecute($input, $output, true);
}
}
