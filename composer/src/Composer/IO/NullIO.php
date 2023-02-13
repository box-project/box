<?php declare(strict_types=1);











namespace Composer\IO;






class NullIO extends BaseIO
{



public function isInteractive(): bool
{
return false;
}




public function isVerbose(): bool
{
return false;
}




public function isVeryVerbose(): bool
{
return false;
}




public function isDebug(): bool
{
return false;
}




public function isDecorated(): bool
{
return false;
}




public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
{
}




public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void
{
}




public function overwrite($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void
{
}




public function overwriteError($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL): void
{
}




public function ask($question, $default = null)
{
return $default;
}




public function askConfirmation($question, $default = true): bool
{
return $default;
}




public function askAndValidate($question, $validator, $attempts = null, $default = null)
{
return $default;
}




public function askAndHideAnswer($question): ?string
{
return null;
}




public function select($question, $choices, $default, $attempts = false, $errorMessage = 'Value "%s" is invalid', $multiselect = false)
{
return $default;
}
}
