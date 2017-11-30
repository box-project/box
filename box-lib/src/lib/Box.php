<?php

namespace Herrera\Box;

use FilesystemIterator;
use Herrera\Box\Compactor\CompactorInterface;
use Herrera\Box\Exception\FileException;
use Herrera\Box\Exception\InvalidArgumentException;
use Herrera\Box\Exception\OpenSslException;
use Herrera\Box\Exception\UnexpectedValueException;
use Phar;
use Phine\Path\Path;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;
use SplObjectStorage;
use Traversable;

/**
 * Provides additional, complimentary functionality to the Phar class.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Box
{
    /**
     * The source code compactors.
     *
     * @var SplObjectStorage
     */
    private $compactors;

    /**
     * The path to the Phar file.
     *
     * @var string
     */
    private $file;

    /**
     * The Phar instance.
     *
     * @var Phar
     */
    private $phar;

    /**
     * The placeholder values.
     *
     * @var array
     */
    private $values = array();

    /**
     * Sets the Phar instance.
     *
     * @param Phar   $phar The instance.
     * @param string $file The path to the Phar file.
     */
    public function __construct(Phar $phar, $file)
    {
        $this->compactors = new SplObjectStorage();
        $this->file = $file;
        $this->phar = $phar;
    }

    /**
     * Adds a file contents compactor.
     *
     * @param CompactorInterface $compactor The compactor.
     */
    public function addCompactor(CompactorInterface $compactor)
    {
        $this->compactors->attach($compactor);
    }

    /**
     * Adds a file to the Phar, after compacting it and replacing its
     * placeholders.
     *
     * @param string $file  The file name.
     * @param string $local The local file name.
     *
     * @throws Exception\Exception
     * @throws FileException If the file could not be used.
     */
    public function addFile($file, $local = null)
    {
        if (null === $local) {
            $local = $file;
        }

        if (false === is_file($file)) {
            throw FileException::create(
                'The file "%s" does not exist or is not a file.',
                $file
            );
        }

        if (false === ($contents = @file_get_contents($file))) {
            throw FileException::lastError();
        }

        $this->addFromString($local, $contents);
    }

    /**
     * Adds the contents from a file to the Phar, after compacting it and
     * replacing its placeholders.
     *
     * @param string $local    The local name.
     * @param string $contents The contents.
     */
    public function addFromString($local, $contents)
    {
        $this->phar->addFromString(
            $local,
            $this->replaceValues($this->compactContents($local, $contents))
        );
    }

    /**
     * Similar to Phar::buildFromDirectory(), except the files will be
     * compacted and their placeholders replaced.
     *
     * @param string $dir   The directory.
     * @param string $regex The regular expression filter.
     */
    public function buildFromDirectory($dir, $regex = null)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_FILEINFO
                | FilesystemIterator::SKIP_DOTS
            )
        );

        if ($regex) {
            $iterator = new RegexIterator($iterator, $regex);
        }

        $this->buildFromIterator($iterator, $dir);
    }

    /**
     * Similar to Phar::buildFromIterator(), except the files will be compacted
     * and their placeholders replaced.
     *
     * @param Traversable $iterator The iterator.
     * @param string      $base     The base directory path.
     *
     * @throws Exception\Exception
     * @throws UnexpectedValueException If the iterator value is unexpected.
     */
    public function buildFromIterator(Traversable $iterator, $base = null)
    {
        if ($base) {
            $base = Path::canonical($base . DIRECTORY_SEPARATOR);
        }

        foreach ($iterator as $key => $value) {
            if (is_string($value)) {
                if (false === is_string($key)) {
                    throw UnexpectedValueException::create(
                        'The key returned by the iterator (%s) is not a string.',
                        gettype($key)
                    );
                }

                $key = Path::canonical($key);
                $value = Path::canonical($value);

                if (is_dir($value)) {
                    $this->phar->addEmptyDir($key);
                } else {
                    $this->addFile($value, $key);
                }
            } elseif ($value instanceof SplFileInfo) {
                if (null === $base) {
                    throw InvalidArgumentException::create(
                        'The $base argument is required for SplFileInfo values.'
                    );
                }

                /** @var $value SplFileInfo */
                $real = $value->getRealPath();

                if (0 !== strpos($real, $base)) {
                    throw UnexpectedValueException::create(
                        'The file "%s" is not in the base directory.',
                        $real
                    );
                }

                $local = str_replace($base, '', $real);

                if ($value->isDir()) {
                    $this->phar->addEmptyDir($local);
                } else {
                    $this->addFile($real, $local);
                }
            } else {
                throw UnexpectedValueException::create(
                    'The iterator value "%s" was not expected.',
                    gettype($value)
                );
            }
        }
    }

    /**
     * Compacts the file contents using the supported compactors.
     *
     * @param string $file     The file name.
     * @param string $contents The file contents.
     *
     * @return string The compacted contents.
     */
    public function compactContents($file, $contents)
    {
        foreach ($this->compactors as $compactor) {
            /** @var $compactor CompactorInterface */
            if ($compactor->supports($file)) {
                $contents = $compactor->compact($contents);
            }
        }

        return $contents;
    }

    /**
     * Creates a new Phar and Box instance.
     *
     * @param string  $file  The file name.
     * @param integer $flags The RecursiveDirectoryIterator flags.
     * @param string  $alias The Phar alias.
     *
     * @return Box The Box instance.
     */
    public static function create($file, $flags = null, $alias = null)
    {
        return new Box(new Phar($file, $flags, $alias), $file);
    }

    /**
     * Returns the Phar instance.
     *
     * @return Phar The instance.
     */
    public function getPhar()
    {
        return $this->phar;
    }

    /**
     * Returns the signature of the phar.
     *
     * This method does not use the extension to extract the phar's signature.
     *
     * @param string  $path The phar file path.
     *
     * @return array The signature.
     */
    public static function getSignature($path)
    {
        return Signature::create($path)->get();
    }

    /**
     * Replaces the placeholders with their values.
     *
     * @param string $contents The contents.
     *
     * @return string The replaced contents.
     */
    public function replaceValues($contents)
    {
        return str_replace(
            array_keys($this->values),
            array_values($this->values),
            $contents
        );
    }

    /**
     * Sets the bootstrap loader stub using a file.
     *
     * @param string  $file    The file path.
     * @param boolean $replace Replace placeholders?
     *
     * @throws Exception\Exception
     * @throws FileException If the stub file could not be used.
     */
    public function setStubUsingFile($file, $replace = false)
    {
        if (false === is_file($file)) {
            throw FileException::create(
                'The file "%s" does not exist or is not a file.',
                $file
            );
        }

        if (false === ($contents = @file_get_contents($file))) {
            throw FileException::lastError();
        }

        if ($replace) {
            $contents = $this->replaceValues($contents);
        }

        $this->phar->setStub($contents);
    }

    /**
     * Sets the placeholder values.
     *
     * @param array $values The values.
     *
     * @throws Exception\Exception
     * @throws InvalidArgumentException If a non-scalar value is used.
     */
    public function setValues(array $values)
    {
        foreach ($values as $value) {
            if (false === is_scalar($value)) {
                throw InvalidArgumentException::create(
                    'Non-scalar values (such as %s) are not supported.',
                    gettype($value)
                );
            }
        }

        $this->values = $values;
    }

    /**
     * Signs the Phar using a private key.
     *
     * @param string $key      The private key.
     * @param string $password The private key password.
     *
     * @throws Exception\Exception
     * @throws OpenSslException If the "openssl" extension could not be used
     *                          or has generated an error.
     */
    public function sign($key, $password = null)
    {
        OpenSslException::reset();

        if (false === extension_loaded('openssl')) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::create(
                'The "openssl" extension is not available.'
            );
            // @codeCoverageIgnoreEnd
        }

        if (false === ($resource = openssl_pkey_get_private($key, $password))) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::lastError();
            // @codeCoverageIgnoreEnd
        }

        if (false === openssl_pkey_export($resource, $private)) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::lastError();
            // @codeCoverageIgnoreEnd
        }

        if (false === ($details = openssl_pkey_get_details($resource))) {
            // @codeCoverageIgnoreStart
            throw OpenSslException::lastError();
            // @codeCoverageIgnoreEnd
        }

        $this->phar->setSignatureAlgorithm(Phar::OPENSSL, $private);

        if (false === @file_put_contents($this->file . '.pubkey', $details['key'])) {
            throw FileException::lastError();
        }
    }

    /**
     * Signs the Phar using a private key file.
     *
     * @param string $file     The private key file name.
     * @param string $password The private key password.
     *
     * @throws Exception\Exception
     * @throws FileException If the private key file could not be read.
     */
    public function signUsingFile($file, $password = null)
    {
        if (false === is_file($file)) {
            throw FileException::create(
                'The file "%s" does not exist or is not a file.',
                $file
            );
        }

        if (false === ($key = @file_get_contents($file))) {
            throw FileException::lastError();
        }

        $this->sign($key, $password);
    }
}
