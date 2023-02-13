<?php declare(strict_types=1);











namespace Composer\IO;

use Composer\Config;
use Psr\Log\LoggerInterface;






interface IOInterface extends LoggerInterface
{
public const QUIET = 1;
public const NORMAL = 2;
public const VERBOSE = 4;
public const VERY_VERBOSE = 8;
public const DEBUG = 16;






public function isInteractive();






public function isVerbose();






public function isVeryVerbose();






public function isDebug();






public function isDecorated();










public function write($messages, bool $newline = true, int $verbosity = self::NORMAL);










public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL);










public function writeRaw($messages, bool $newline = true, int $verbosity = self::NORMAL);










public function writeErrorRaw($messages, bool $newline = true, int $verbosity = self::NORMAL);











public function overwrite($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL);











public function overwriteError($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL);










public function ask(string $question, $default = null);











public function askConfirmation(string $question, bool $default = true);
















public function askAndValidate(string $question, callable $validator, ?int $attempts = null, $default = null);








public function askAndHideAnswer(string $question);
















public function select(string $question, array $choices, $default, $attempts = false, string $errorMessage = 'Value "%s" is invalid', bool $multiselect = false);






public function getAuthentications();








public function hasAuthentication(string $repositoryName);








public function getAuthentication(string $repositoryName);










public function setAuthentication(string $repositoryName, string $username, ?string $password = null);






public function loadConfiguration(Config $config);
}
