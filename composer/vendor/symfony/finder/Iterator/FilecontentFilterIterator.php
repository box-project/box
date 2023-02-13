<?php










namespace Symfony\Component\Finder\Iterator;









class FilecontentFilterIterator extends MultiplePcreFilterIterator
{





#[\ReturnTypeWillChange]
public function accept()
{
if (!$this->matchRegexps && !$this->noMatchRegexps) {
return true;
}

$fileinfo = $this->current();

if ($fileinfo->isDir() || !$fileinfo->isReadable()) {
return false;
}

$content = $fileinfo->getContents();
if (!$content) {
return false;
}

return $this->isAccepted($content);
}








protected function toRegex(string $str)
{
return $this->isRegex($str) ? $str : '/'.preg_quote($str, '/').'/';
}
}
