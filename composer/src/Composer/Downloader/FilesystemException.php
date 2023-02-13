<?php declare(strict_types=1);











namespace Composer\Downloader;






class FilesystemException extends \Exception
{
public function __construct(string $message = '', int $code = 0, ?\Exception $previous = null)
{
parent::__construct("Filesystem exception: \n".$message, $code, $previous);
}
}
