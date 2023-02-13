<?php










namespace Symfony\Component\String\Slugger;

use Symfony\Component\String\AbstractUnicodeString;






interface SluggerInterface
{



public function slug(string $string, string $separator = '-', string $locale = null): AbstractUnicodeString;
}
