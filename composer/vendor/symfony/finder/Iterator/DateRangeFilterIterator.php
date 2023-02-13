<?php










namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\Comparator\DateComparator;








class DateRangeFilterIterator extends \FilterIterator
{
private $comparators = [];





public function __construct(\Iterator $iterator, array $comparators)
{
$this->comparators = $comparators;

parent::__construct($iterator);
}






#[\ReturnTypeWillChange]
public function accept()
{
$fileinfo = $this->current();

if (!file_exists($fileinfo->getPathname())) {
return false;
}

$filedate = $fileinfo->getMTime();
foreach ($this->comparators as $compare) {
if (!$compare->test($filedate)) {
return false;
}
}

return true;
}
}
