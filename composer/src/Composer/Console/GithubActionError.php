<?php declare(strict_types=1);











namespace Composer\Console;

use Composer\IO\IOInterface;
use Composer\Util\Platform;

final class GithubActionError
{



protected $io;

public function __construct(IOInterface $io)
{
$this->io = $io;
}

public function emit(string $message, ?string $file = null, ?int $line = null): void
{
if (Platform::getEnv('GITHUB_ACTIONS') && !Platform::getEnv('COMPOSER_TESTS_ARE_RUNNING')) {
$message = $this->escapeData($message);

if ($file && $line) {
$file = $this->escapeProperty($file);
$this->io->write("::error file=". $file .",line=". $line ."::". $message);
} elseif ($file) {
$file = $this->escapeProperty($file);
$this->io->write("::error file=". $file ."::". $message);
} else {
$this->io->write("::error ::". $message);
}
}
}

private function escapeData(string $data): string
{

$data = str_replace("%", '%25', $data);
$data = str_replace("\r", '%0D', $data);
$data = str_replace("\n", '%0A', $data);

return $data;
}

private function escapeProperty(string $property): string
{

$property = str_replace("%", '%25', $property);
$property = str_replace("\r", '%0D', $property);
$property = str_replace("\n", '%0A', $property);
$property = str_replace(":", '%3A', $property);
$property = str_replace(",", '%2C', $property);

return $property;
}
}
