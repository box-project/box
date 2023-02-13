<?php










namespace Symfony\Component\Console\Formatter;






interface WrappableOutputFormatterInterface extends OutputFormatterInterface
{



public function formatAndWrap(?string $message, int $width);
}
