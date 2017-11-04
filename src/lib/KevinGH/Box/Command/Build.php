<?php

namespace KevinGH\Box\Command;

use Herrera\Box\Box;
use Herrera\Box\StubGenerator;
use KevinGH\Box\Configuration;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Traversable;

/**
 * Builds a new Phar.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Build extends Configurable
{
    /**
     * The Box instance.
     *
     * @var Box
     */
    private $box;

    /**
     * The configuration settings.
     *
     * @var Configuration
     */
    private $config;

    /**
     * @override
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('build');
        $this->setDescription('Builds a new Phar.');
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
     * @override
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->config = $this->getConfig($input);
        $path = $this->config->getOutputPath();

        // load bootstrap file
        if (null !== ($bootstrap = $this->config->getBootstrapFile())) {
            $this->config->loadBootstrap();
            $this->putln('?', "Loading bootstrap file: $bootstrap");

            unset($bootstrap);
        }

        // remove any previous work
        if (file_exists($path)) {
            $this->putln('?', 'Removing previously built Phar...');

            unlink($path);
        }

        // set up Box
        if ($this->isVerbose()) {
            $this->putln('*', 'Building...');
        } else {
            $output->writeln('Building...');
        }

        $this->putln('?', "Output path: $path");

        $this->box = Box::create($path);
        $this->box->getPhar()->startBuffering();

        // set replacement values, if any
        if (array() !== ($values = $this->config->getProcessedReplacements())) {
            $this->putln('?', 'Setting replacement values...');

            if ($this->isVerbose()) {
                foreach ($values as $key => $value) {
                    $this->putln('+', "$key: $value");
                }
            }

            $this->box->setValues($values);

            unset($values, $key, $value);
        }

        // register configured compactors
        if (array() !== ($compactors = $this->config->getCompactors())) {
            $this->putln('?', 'Registering compactors...');

            foreach ($compactors as $compactor) {
                $this->putln('+', get_class($compactor));

                $this->box->addCompactor($compactor);
            }
        }

        // alert about mapped paths
        if (array() !== ($map = $this->config->getMap())) {
            $this->putln('?', 'Mapping paths:');

            foreach ($map as $item) {
                foreach ($item as $match => $replace) {
                    if (empty($match)) {
                        $match = "(all)";
                    }

                    $this->putln('-', "$match <info>></info> $replace");
                }
            }
        }

        // start adding files
        if (array() !== ($iterators = $this->config->getFinders())) {
            $this->putln('?', 'Adding Finder files...');

            foreach ($iterators as $iterator) {
                $this->add($iterator, null);
            }
        }

        if (array() !== ($iterators = $this->config->getBinaryFinders())) {
            $this->putln('?', 'Adding binary Finder files...');

            foreach ($iterators as $iterator) {
                $this->add($iterator, null, true);
            }
        }

        $this->add(
            $this->config->getDirectoriesIterator(),
            'Adding directories...'
        );

        $this->add(
            $this->config->getBinaryDirectoriesIterator(),
            'Adding binary directories...',
            true
        );

        $this->add(
            $this->config->getFilesIterator(),
            'Adding files...'
        );

        $this->add(
            $this->config->getBinaryFilesIterator(),
            'Adding binary files...',
            true
        );

        if (null !== ($main = $this->config->getMainScriptPath())) {
            $this->putln(
                '?',
                'Adding main file: ' . $this->config->getBasePath() . DIRECTORY_SEPARATOR . $main
            );

            $mapper = $this->config->getMapper();
            $pharPath = $mapper($main);

            if (null !== $pharPath) {
                $this->putln('>', $pharPath);

                $main = $pharPath;
            }

            $this->box->addFromString(
                $main,
                $this->config->getMainScriptContents()
            );
        }

        // set the appropriate stub
        if (true === $this->config->isStubGenerated()) {
            $this->putln('?', 'Generating new stub...');

            $stub = StubGenerator::create()
                ->alias($this->config->getAlias())
                ->extract($this->config->isExtractable())
                ->index($main)
                ->intercept($this->config->isInterceptFileFuncs())
                ->mimetypes($this->config->getMimetypeMapping())
                ->mung($this->config->getMungVariables())
                ->notFound($this->config->getNotFoundScriptPath())
                ->web($this->config->isWebPhar());

            if (null !== ($shebang = $this->config->getShebang())) {
                $this->putln('-', 'Using custom shebang line: ' . $shebang);

                $stub->shebang($shebang);
            }

            if (null !== ($banner = $this->config->getStubBanner())) {
                $this->putln('-', 'Using custom banner.');

                $stub->banner($banner);
            } elseif (null !== ($banner = $this->config->getStubBannerFromFile())) {
                $this->putln(
                    '-',
                    'Using custom banner from file: '
                    . $this->config->getBasePath()
                    . DIRECTORY_SEPARATOR
                    . $this->config->getStubBannerPath()
                );

                $stub->banner($banner);
            }

            $this->box->getPhar()->setStub($stub->generate());
        } elseif (null !== ($stub = $this->config->getStubPath())) {
            $stub = $this->config->getBasePath() . DIRECTORY_SEPARATOR . $stub;

            $this->putln('?', "Using stub file: $stub");

            $this->box->setStubUsingFile($stub);
        } else {
            $this->putln('?', 'Using default stub.');
        }

        // set metadata, if any
        if (null !== ($metadata = $this->config->getMetadata())) {
            $this->putln('?', 'Setting metadata...');

            $this->box->getPhar()->setMetadata($metadata);
        }

        // compress, if algorithm set
        if (null !== ($algorithm = $this->config->getCompressionAlgorithm())) {
            $this->putln('?', 'Compressing...');

            $this->box->getPhar()->compressFiles($algorithm);
        }

        $this->box->getPhar()->stopBuffering();

        // sign using private key, if applicable
        if (file_exists($path . '.pubkey')) {
            unlink($path . '.pubkey');
        }

        if (null !== ($key = $this->config->getPrivateKeyPath())) {
            $this->putln('?', 'Signing using a private key...');

            $passphrase = $this->config->getPrivateKeyPassphrase();

            if ($this->config->isPrivateKeyPrompt()) {
                /** @var $dialog QuestionHelper */
                $dialog = $this->getHelper('question');
                $passphrase = $dialog->askHiddenResponse(
                    $output,
                    'Private key passphrase:'
                );
            }

            $this->box->signUsingFile($key, $passphrase);

            // set the signature algorithm if no key is used
        } elseif (null !== ($algorithm = $this->config->getSigningAlgorithm())) {
            $this->box->getPhar()->setSignatureAlgorithm($algorithm);
        }

        unset($this->box);

        // chmod, if configured
        if (null !== ($chmod = $this->config->getFileMode())) {
            $this->putln('?', 'Setting file permissions...');

            chmod($path, $chmod);
        }

        $this->putln('*', 'Done.');

        if (!file_exists($path)) {
            $output->writeln(
                '<fg=yellow>The archive was not generated because it did not have any contents.</fg=yellow>'
            );
        }
    }

    /**
     * Adds files using an iterator.
     *
     * @param Traversable $iterator The iterator.
     * @param string      $message  The message to announce.
     * @param boolean     $binary   Should the adding be binary-safe?
     *
     * @throws RuntimeException If a file is not readable.
     */
    private function add(
        Traversable $iterator = null,
        $message = null,
        $binary = false
    ) {
        static $count = 0;

        if ($iterator) {
            if ($message) {
                $this->putln('?', $message);
            }

            $box = $binary ? $this->box->getPhar() : $this->box;
            $baseRegex = $this->config->getBasePathRegex();
            $mapper = $this->config->getMapper();

            /** @var $file SplFileInfo */
            foreach ($iterator as $file) {
                if (0 === (++$count % 100)) {
                    gc_collect_cycles();
                }

                $relative = preg_replace($baseRegex, '', $file->getPathname());

                if (null !== ($mapped = $mapper($relative))) {
                    $relative = $mapped;
                }

                if ($this->isVerbose()) {
                    if (false === $file->isReadable()) {
                        throw new RuntimeException(
                            sprintf(
                                'The file "%s" is not readable.',
                                $file->getPathname()
                            )
                        );
                    }

                    $this->putln('+', $file);

                    if (null !== $mapped) {
                        $this->putln('>', $relative);
                    }
                }

                $box->addFile($file, $relative);
            }
        }
    }
}
