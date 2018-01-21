<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\Command;

use KevinGH\Box\Box;
use KevinGH\Box\Compactor;
use KevinGH\Box\Configuration;
use KevinGH\Box\Logger\BuildLogger;
use KevinGH\Box\RetrieveRelativeBasePath;
use KevinGH\Box\StubGenerator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use function KevinGH\Box\formatted_filesize;
use function KevinGH\Box\get_phar_compression_algorithms;

final class Build extends Configurable
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('build');
        $this->setDescription('Builds a new PHAR');
        $this->setHelp(
            <<<HELP
The <info>%command.name%</info> command will build a new Phar based on a variety of settings.
<comment>
  This command relies on a configuration file for loading
  Phar packaging settings. If a configuration file is not
  specified through the <info>--configuration|-c</info> option, one of
  the following files will be used (in order): <info>box.json,
  box.json.dist</info>
</comment>
The configuration file is actually a JSON object saved to a file.
Note that all settings are optional.
<comment>
  {
    "algorithm": ?,
    "alias": ?,
    "banner": ?,
    "banner-file": ?,
    "base-path": ?,
    "blacklist": ?,
    "bootstrap": ?,
    "chmod": ?,
    "compactors": ?,
    "compression": ?,
    "datetime": ?,
    "datetime_format": ?,
    "directories": ?,
    "directories-bin": ?,
    "extract": ?,
    "files": ?,
    "files-bin": ?,
    "finder": ?,
    "finder-bin": ?,
    "git-version": ?,
    "intercept": ?,
    "key": ?,
    "key-pass": ?,
    "main": ?,
    "map": ?,
    "metadata": ?,
    "mimetypes": ?,
    "mung": ?,
    "not-found": ?,
    "output": ?,
    "replacements": ?,
    "shebang": ?,
    "stub": ?,
    "web": ?
  }
</comment>



The <info>algorithm</info> <comment>(string, integer)</comment> setting is the signing algorithm to
use when the Phar is built <comment>(Phar::setSignatureAlgorithm())</comment>. It can an
integer value (the value of the constant), or the name of the Phar
constant. The following is a list of the signature algorithms listed
on the help page:
<comment>
  - MD5 (Phar::MD5)
  - SHA1 (Phar::SHA1)
  - SHA256 (Phar::SHA256)
  - SHA512 (Phar::SHA512)
  - OPENSSL (Phar::OPENSSL)
</comment>
The <info>alias</info> <comment>(string)</comment> setting is used when generating a new stub to call
the <comment>Phar::mapPhar()</comment> method. This makes it easier to refer to files in
the Phar.

The <info>annotations</info> <comment>(boolean, object)</comment> setting is used to enable compacting
annotations in PHP source code. By setting it to <info>true</info>, all Doctrine-style
annotations are compacted in PHP files. You may also specify a list of
annotations to ignore, which will be stripped while protecting the
remaining annotations:
<comment>
  {
      "annotations": {
          "ignore": [
              "author",
              "package",
              "version",
              "see"
          ]
      }
  }
</comment>
You may want to see this website for a list of annotations which are
commonly ignored:
<comment>
  https://github.com/herrera-io/php-annotations
</comment>
The <info>banner</info> <comment>(string)</comment> setting is the banner comment that will be used when
a new stub is generated. The value of this setting must not already be
enclosed within a comment block, as it will be automatically done for
you.

The <info>banner-file</info> <comment>(string)</comment> setting is like <info>stub-banner</info>, except it is a
path to the file that will contain the comment. Like <info>stub-banner</info>, the
comment must not already be enclosed in a comment block.

The <info>base-path</info> <comment>(string)</comment> setting is used to specify where all of the
relative file paths should resolve to. This does not, however, alter
where the built Phar will be stored <comment>(see: <info>output</info>)</comment>. By default, the
base path is the directory containing the configuration file.

The <info>blacklist</info> <comment>(string, array)</comment> setting is a list of files that must
not be added. The files blacklisted are the ones found using the other
available configuration settings: <info>directories, directories-bin, files,
files-bin, finder, finder-bin</info>. Note that directory separators are
automatically corrected to the platform specific version.

Assuming that the base directory path is <comment>/home/user/project</comment>:
<comment>
  {
      "blacklist": [
          "path/to/file/1"
          "path/to/file/2"
      ],
      "directories": ["src"]
  }
</comment>
The following files will be blacklisted:
<comment>
  - /home/user/project/src/path/to/file/1
  - /home/user/project/src/path/to/file/2
</comment>
But not these files:
<comment>
  - /home/user/project/src/another/path/to/file/1
  - /home/user/project/src/another/path/to/file/2
</comment>
The <info>bootstrap</info> <comment>(string)</comment> setting allows you to specify a PHP file that
will be loaded before the <info>build</info> or <info>add</info> commands are used. This is
useful for loading third-party file contents compacting classes that
were configured using the <info>compactors</info> setting.

The <info>chmod</info> <comment>(string)</comment> setting is used to change the file permissions of
the newly built Phar. The string contains an octal value: <comment>0755</comment>. You
must prefix the mode with zero if you specify the mode in decimal.

The <info>compactors</info> <comment>(string, array)</comment> setting is a list of file contents
compacting classes that must be registered. A file compacting class
is used to reduce the size of a specific file type. The following is
a simple example:
<comment>
  use Herrera\\Box\\Compactor\\CompactorInterface;

  class MyCompactor implements CompactorInterface
  {
      public function compact(\$contents)
      {
          return trim(\$contents);
      }

      public function supports(\$file)
      {
          return (bool) preg_match('/\.txt/', \$file);
      }
  }
</comment>
The following compactors are included with Box:
<comment>
  - Herrera\\Box\\Compactor\\Json
  - Herrera\\Box\\Compactor\\Php
</comment>
The <info>compression</info> <comment>(string, integer)</comment> setting is the compression algorithm
to use when the Phar is built. The compression affects the individual
files within the Phar, and not the Phar as a whole <comment>(Phar::compressFiles())</comment>.
The following is a list of the signature algorithms listed on the help
page:
<comment>
  - BZ2 (Phar::BZ2)
  - GZ (Phar::GZ)
  - NONE (Phar::NONE)
</comment>
The <info>directories</info> <comment>(string, array)</comment> setting is a list of directory paths
relative to <info>base-path</info>. All files ending in <comment>.php</comment> will be automatically
compacted, have their placeholder values replaced, and added to the
Phar. Files listed in the <info>blacklist</info> setting will not be added.

The <info>directories-bin</info> <comment>(string, array)</comment> setting is similar to <info>directories</info>,
except all file types are added to the Phar unmodified. This is suitable
for directories containing images or other binary data.

The <info>extract</info> <comment>(boolean)</comment> setting determines whether or not the generated
stub should include a class to extract the phar. This class would be
used if the phar is not available. (Increases stub file size.)

The <info>files</info> <comment>(string, array)</comment> setting is a list of files paths relative to
<info>base-path</info>. Each file will be compacted, have their placeholder files
replaced, and added to the Phar. This setting is not affected by the
<info>blacklist</info> setting.

The <info>files-bin</info> <comment>(string, array)</comment> setting is similar to <info>files</info>, except that
all files are added to the Phar unmodified. This is suitable for files
such as images or those that contain binary data.

The <info>finder</info> <comment>(array)</comment> setting is a list of JSON objects. Each object key
is a name, and each value an argument for the methods in the
<comment>Symfony\\Component\\Finder\\Finder</comment> class. If an array of values is provided
for a single key, the method will be called once per value in the array.
Note that the paths specified for the "in" method are relative to
<info>base-path</info>.

The <info>finder-bin</info> <comment>(array)</comment> setting performs the same function, except all
files found by the finder will be treated as binary files, leaving them
unmodified.
<comment>
It may be useful to know that Box imports files in the following order:

 - finder
 - finder-bin
 - directories
 - directories-bin
 - files
 - files-bin
</comment>
The <info>datetime</info> <comment>(string)</comment> setting is the name of a placeholder value that
will be replaced in all non-binary files by the current datetime.

Example: <comment>2015-01-28 14:55:23</comment>

The <info>datetime_format</info> <comment>(string)</comment> setting accepts a valid PHP date format. It can be used to change the format for the <info>datetime</info> setting.

Example: <comment>Y-m-d H:i:s</comment>

The <info>git-commit</info> <comment>(string)</comment> setting is the name of a placeholder value that
will be replaced in all non-binary files by the current Git commit hash
of the repository.

Example: <comment>e558e335f1d165bc24d43fdf903cdadd3c3cbd03</comment>

The <info>git-commit-short</info> <comment>(string)</comment> setting is the name of a placeholder value
that will be replaced in all non-binary files by the current Git short
commit hash of the repository.

Example: <comment>e558e33</comment>

The <info>git-tag</info> <comment>(string)</comment> setting is the name of a placeholder value that will
be replaced in all non-binary files by the current Git tag of the
repository.

Examples:
<comment>
 - 2.0.0
 - 2.0.0-2-ge558e33
</comment>
The <info>git-version</info> <comment>(string)</comment> setting is the name of a placeholder value that
will be replaced in all non-binary files by the one of the following (in
order):

  - The git repository's most recent tag.
  - The git repository's current short commit hash.

The short commit hash will only be used if no tag is available.

The <info>intercept</info> <comment>(boolean)</comment> setting is used when generating a new stub. If
setting is set to <comment>true</comment>, the <comment>Phar::interceptFileFuncs();</comment> method will be
called in the stub.

The <info>key</info> <comment>(string)</comment> setting is used to specify the path to the private key
file. The private key file will be used to sign the Phar using the
<comment>OPENSSL</comment> signature algorithm. If an absolute path is not provided, the
path will be relative to the current working directory.

The <info>key-pass</info> <comment>(string, boolean)</comment> setting is used to specify the passphrase
for the private <info>key</info>. If a <comment>string</comment> is provided, it will be used as is as
the passphrase. If <comment>true</comment> is provided, you will be prompted for the
passphrase.

The <info>main</info> <comment>(string)</comment> setting is used to specify the file (relative to
<info>base-path</info>) that will be run when the Phar is executed from the command
line. If the file was not added by any of the other file adding settings,
it will be automatically added after it has been compacted and had its
placeholder values replaced. Also, the #! line will be automatically
removed if present.

The <info>map</info> <comment>(array)</comment> setting is used to change where some (or all) files are
stored inside the phar. The key is a beginning of the relative path that
will be matched against the file being added to the phar. If the key is
a match, the matched segment will be replaced with the value. If the key
is empty, the value will be prefixed to all paths (except for those
already matched by an earlier key).

<comment>
  {
    "map": [
      { "my/test/path": "src/Test" },
      { "": "src/Another" }
    ]
  }
</comment>

(with the files)

<comment>
  1. my/test/path/file.php
  2. my/test/path/some/other.php
  3. my/test/another.php
</comment>

(will be stored as)

<comment>
  1. src/Test/file.php
  2. src/Test/some/other.php
  3. src/Another/my/test/another.php
</comment>

The <info>metadata</info> <comment>(any)</comment> setting can be any value. This value will be stored as
metadata that can be retrieved from the built Phar <comment>(Phar::getMetadata())</comment>.

The <info>mimetypes</info> <comment>(object)</comment> setting is used when generating a new stub. It is
a map of file extensions and their mimetypes. To see a list of the default
mapping, please visit:

  <comment>http://www.php.net/manual/en/phar.webphar.php</comment>

The <info>mung</info> <comment>(array)</comment> setting is used when generating a new stub. It is a list
of server variables to modify for the Phar. This setting is only useful
when the <info>web</info> setting is enabled.

The <info>not-found</info> <comment>(string)</comment> setting is used when generating a new stub. It
specifies the file that will be used when a file is not found inside the
Phar. This setting is only useful when <info>web</info> setting is enabled.

The <info>output</info> <comment>(string)</comment> setting specifies the file name and path of the newly
built Phar. If the value of the setting is not an absolute path, the path
will be relative to the current working directory.

The <info>replacements</info> <comment>(object)</comment> setting is a map of placeholders and their
values. The placeholders are replaced in all non-binary files with the
specified values.

The <info>shebang</info> <comment>(string)</comment> setting is used to specify the shebang line used
when generating a new stub. By default, this line is used:

  <comment>#!/usr/bin/env php</comment>

The shebang line can be removed altogether if <comment>false</comment> or an empty string
is provided.

The <info>stub</info> <comment>(string, boolean)</comment> setting is used to specify the location of a
stub file, or if one should be generated. If a path is provided, the stub
file will be used as is inside the Phar. If <comment>true</comment> is provided, a new stub
will be generated. If <comment>false (or nothing)</comment> is provided, the default stub
used by the Phar class will be used.

The <info>web</info> <comment>(boolean)</comment> setting is used when generating a new stub. If <comment>true</comment> is
provided, <comment>Phar::webPhar()</comment> will be called in the stub.
HELP
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln($this->getApplication()->getHelp());
        $io->writeln('');

        $config = $this->getConfig($input);
        $path = $config->getOutputPath();

        $logger = new BuildLogger($io);

        $startTime = microtime(true);

        $this->loadBootstrapFile($config, $logger);
        $this->removeExistingPhar($config, $logger);

        $logger->logStartBuilding($path);

        $this->createPhar($path, $config, $input, $output, $logger);

        $this->correctPermissions($path, $config, $logger);

        $logger->log(
            BuildLogger::STAR_PREFIX,
            'Done.'
        );

        if ($io->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $io->comment(
                sprintf(
                    "<info>Size: %s\nMemory usage: %.2fMB (peak: %.2fMB), time: %.2fs<info>",
                    formatted_filesize($path),
                    round(memory_get_usage() / 1024 / 1024, 2),
                    round(memory_get_peak_usage() / 1024 / 1024, 2),
                    round(microtime(true) - $startTime, 2)
                )
            );
        }

        if (false === file_exists($path)) {
            //TODO: check that one
            $io->warning('The archive was not generated because it did not have any contents');
        }
    }

    private function createPhar(
        string $path,
        Configuration $config,
        InputInterface $input,
        OutputInterface $output,
        BuildLogger $logger
    ): void {
        $box = Box::create($path);

        $box->getPhar()->startBuffering();

        $this->setReplacementValues($config, $box, $logger);
        $this->registerCompactors($config, $box, $logger);
        $this->alertAboutMappedPaths($config, $logger);

        $this->addFiles($config, $box, $logger);

        $main = $this->registerMainScript($config, $box, $logger);

        $this->registerStub($config, $box, $main, $logger);
        $this->configureMetadata($config, $box, $logger);
        $this->configureCompressionAlgorithm($config, $box, $logger);

        $box->getPhar()->stopBuffering();

        $this->signPhar($config, $box, $path, $input, $output, $logger);
    }

    private function loadBootstrapFile(Configuration $config, BuildLogger $logger): void
    {
        $file = $config->getBootstrapFile();

        if (null === $file) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Loading the bootstrap file "%s"',
                $file
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );

        $config->loadBootstrap();
    }

    private function removeExistingPhar(Configuration $config, BuildLogger $logger): void
    {
        $path = $config->getOutputPath();

        if (false === file_exists($path)) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Removing the existing PHAR "%s"',
                $path
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );

        (new Filesystem())->remove($path);
    }

    private function setReplacementValues(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $values = $config->getProcessedReplacements();

        if ([] === $values) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Setting replacement values',
            OutputInterface::VERBOSITY_VERBOSE
        );

        foreach ($values as $key => $value) {
            $logger->log(
                BuildLogger::PLUS_PREFIX,
                sprintf(
                    '%s: %s',
                    $key,
                    $value
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }

        $box->registerPlaceholders($values);
    }

    private function registerCompactors(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $compactors = $config->getCompactors();

        if ([] === $compactors) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'No compactor to register',
                OutputInterface::VERBOSITY_VERBOSE
            );

            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Registering compactors',
            OutputInterface::VERBOSITY_VERBOSE
        );

        $logCompactors = function (Compactor $compactor) use ($logger): void {
            $logger->log(
                BuildLogger::PLUS_PREFIX,
                get_class($compactor),
                OutputInterface::VERBOSITY_VERBOSE
            );
        };

        array_map($logCompactors, $compactors);

        $box->registerCompactors($compactors);
    }

    private function alertAboutMappedPaths(Configuration $config, BuildLogger $logger): void
    {
        $map = $config->getMap();

        if ([] === $map) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Mapping paths',
            OutputInterface::VERBOSITY_VERBOSE
        );

        foreach ($map as $item) {
            foreach ($item as $match => $replace) {
                if (empty($match)) {
                    $match = '(all)';
                }

                $logger->log(
                    BuildLogger::MINUS_PREFIX,
                    sprintf(
                        '%s <info>></info> %s',
                        $match,
                        $replace
                    ),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }
    }

    private function addFiles(Configuration $config, Box $box, BuildLogger $logger): void
    {
        if ([] !== ($iterators = $config->getFilesIterators())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Adding finder files',
                OutputInterface::VERBOSITY_VERBOSE
            );

            foreach ($iterators as $iterator) {
                $this->addFilesToBox($config, $box, $iterator, null, false, $config->getBasePathRetriever(), $logger);
            }
        }

        if ([] !== ($iterators = $config->getBinaryIterators())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Adding binary finder files',
                OutputInterface::VERBOSITY_VERBOSE
            );

            foreach ($iterators as $iterator) {
                $this->addFilesToBox($config, $box, $iterator, null, true, $config->getBasePathRetriever(), $logger);
            }
        }

        $this->addFilesToBox(
            $config,
            $box,
            $config->getDirectoriesIterator(),
            'Adding directories',
            false,
            $config->getBasePathRetriever(),
            $logger
        );

        $this->addFilesToBox(
            $config,
            $box,
            $config->getBinaryDirectoriesIterator(),
            'Adding binary directories',
            true,
            $config->getBasePathRetriever(),
            $logger
        );

        $this->addFilesToBox(
            $config,
            $box,
            $config->getFilesIterator(),
            'Adding files',
            false,
            $config->getBasePathRetriever(),
            $logger
        );

        $this->addFilesToBox(
            $config,
            $box,
            $config->getBinaryFilesIterator(),
            'Adding binary files',
            true,
            $config->getBasePathRetriever(),
            $logger
        );
    }

    private function registerMainScript(Configuration $config, Box $box, BuildLogger $logger): ?string
    {
        $main = $config->getMainScriptPath();

        if (null === $main) {
            return null;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Adding main file: %s',
                $config->getBasePath().DIRECTORY_SEPARATOR.$main
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );

        $mapFile = $config->getFileMapper();
        $pharPath = $mapFile($main);

        if (null !== $pharPath) {
            $logger->log(
                BuildLogger::CHEVRON_PREFIX,
                $pharPath,
                OutputInterface::VERBOSITY_VERBOSE
            );

            $main = $pharPath;
        }

        $box->addFromString(
            $main,
            $config->getMainScriptContent()
        );

        return $main;
    }

    private function registerStub(Configuration $config, Box $box, ?string $main, BuildLogger $logger): void
    {
        if (true === $config->isStubGenerated()) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Generating new stub',
                OutputInterface::VERBOSITY_VERBOSE
            );

            $stub = $this->createStub($config, $main, $logger);

            $box->getPhar()->setStub($stub->generate());
        } elseif (null !== ($stub = $config->getStubPath())) {
            $stub = $config->getBasePath().DIRECTORY_SEPARATOR.$stub;

            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Using stub file: %s',
                    $stub
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );

            $box->registerStub($stub);
        } else {
            if (null !== $main) {
                $box->getPhar()->setDefaultStub($main, $main);
            }

            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Using default stub',
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
    }

    private function configureMetadata(Configuration $config, Box $box, BuildLogger $logger): void
    {
        if (null !== ($metadata = $config->getMetadata())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Setting metadata',
                OutputInterface::VERBOSITY_VERBOSE
            );

            $logger->log(
                BuildLogger::MINUS_PREFIX,
                is_string($metadata) ? $metadata : var_export($metadata, true),
                OutputInterface::VERBOSITY_VERBOSE
            );

            $box->getPhar()->setMetadata($metadata);
        }
    }

    private function configureCompressionAlgorithm(Configuration $config, Box $box, BuildLogger $logger): void
    {
        if (null !== ($algorithm = $config->getCompressionAlgorithm())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Compressing with the algorithm "<comment>%s</comment>"',
                    array_search($algorithm, get_phar_compression_algorithms(), true)
                ),
                OutputInterface::VERBOSITY_VERBOSE
            );

            $box->getPhar()->compressFiles($algorithm);
        } else {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                '<error>No compression</error>',
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
    }

    private function signPhar(
        Configuration $config,
        Box $box,
        string $path,
        InputInterface $input,
        OutputInterface $output,
        BuildLogger $logger
    ): void {
        // sign using private key, if applicable
        //TODO: check that out
        if (file_exists($path.'.pubkey')) {
            unlink($path.'.pubkey');
        }

        $key = $config->getPrivateKeyPath();

        if (null === $key) {
            if (null !== ($algorithm = $config->getSigningAlgorithm())) {
                $box->getPhar()->setSignatureAlgorithm($algorithm);
            }

            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Signing using a private key',
            OutputInterface::VERBOSITY_VERBOSE
        );

        $passphrase = $config->getPrivateKeyPassphrase();

        if ($config->isPrivateKeyPrompt()) {
            if (false === $input->isInteractive()) {
                throw new RuntimeException(
                    sprintf(
                        'Accessing to the private key "%s" requires a passphrase but none provided. Either '
                        .'provide one or run this command in interactive mode.',
                        $key
                    )
                );
            }

            /** @var $dialog QuestionHelper */
            $dialog = $this->getHelper('question');

            $question = new Question('Private key passphrase:');
            $question->setHidden(false);
            $question->setHiddenFallback(false);

            $passphrase = $dialog->ask($input, $output, $question);

            $output->writeln('');
        }

        $box->signUsingFile($key, $passphrase);
    }

    private function correctPermissions(string $path, Configuration $config, BuildLogger $logger): void
    {
        if (null !== ($chmod = $config->getFileMode())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                "Setting file permissions to <comment>$chmod</comment>",
                OutputInterface::VERBOSITY_VERBOSE
            );

            chmod($path, $chmod);
        }
    }

    /**
     * Adds files using an iterator.
     *
     * @param Configuration $config
     * @param Box $box
     * @param iterable|SplFileInfo[] $iterator the iterator
     * @param string $message the message to announce
     * @param bool $binary Should the adding be binary-safe?
     * @param RetrieveRelativeBasePath $retrieveRelativeBasePath
     * @param BuildLogger $logger
     */
    private function addFilesToBox(
        Configuration $config,
        Box $box,
        ?iterable $iterator,
        ?string $message,
        bool $binary,
        RetrieveRelativeBasePath $retrieveRelativeBasePath,
        BuildLogger $logger
    ): void {
        static $count = 0;

        if (null === $iterator) {
            return;
        }

        if (null !== $message) {
            $logger->log(BuildLogger::QUESTION_MARK_PREFIX, $message, OutputInterface::VERBOSITY_VERBOSE);
        }

        $box = $binary ? $box->getPhar() : $box;
        $mapFile = $config->getFileMapper();

        foreach ($iterator as $file) {
            // @var $file SplFileInfo

            // Forces garbadge collection from time to time
            if (0 === (++$count % 100)) {
                gc_collect_cycles();
            }

            $relativePath = $retrieveRelativeBasePath($file->getPathname());

            $mapped = $mapFile($relativePath);

            if (null !== $mapped) {
                $relativePath = $mapped;
            }

            if (null !== $mapped) {
                $logger->log(
                    BuildLogger::CHEVRON_PREFIX,
                    $relativePath,
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
            } else {
                $logger->log(
                    BuildLogger::PLUS_PREFIX,
                    (string) $file,
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
            }

            $box->addFile((string) $file, $relativePath);
        }
    }

    private function createStub(Configuration $config, ?string $main, BuildLogger $logger): StubGenerator
    {
        $stub = StubGenerator::create()
            ->alias($config->getAlias())
            ->extract($config->isExtractable())
            ->index($main)
            ->intercept($config->isInterceptFileFuncs())
            ->mimetypes($config->getMimetypeMapping())
            ->mung($config->getMungVariables())
            ->notFound($config->getNotFoundScriptPath())
            ->web($config->isWebPhar());

        if (null !== ($shebang = $config->getShebang())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom shebang line: %s',
                    $shebang
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $stub->shebang($shebang);
        }

        if (null !== ($banner = $config->getStubBanner())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom banner: %s',
                    $banner
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $stub->banner($banner);
        } elseif (null !== ($banner = $config->getStubBannerFromFile())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom banner from file: %s',
                    $config->getBasePath().DIRECTORY_SEPARATOR.$config->getStubBannerPath()
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $stub->banner($banner);
        }

        return $stub;
    }
}
