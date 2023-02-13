<?php










namespace Seld\PharUtils;

class Timestamps
{
private $contents;




public function __construct($file)
{
$this->contents = file_get_contents($file);
}








public function updateTimestamps($timestamp = null)
{
if ($timestamp instanceof \DateTime || $timestamp instanceof \DateTimeInterface) {
$timestamp = $timestamp->getTimestamp();
} elseif (is_string($timestamp)) {
$timestamp = strtotime($timestamp);
} elseif (!is_int($timestamp)) {
$timestamp = strtotime('1984-12-24T00:00:00Z');
}


if (!preg_match('{__HALT_COMPILER\(\);(?: +\?>)?\r?\n}', $this->contents, $match, PREG_OFFSET_CAPTURE)) {
throw new \RuntimeException('Could not detect the stub\'s end in the phar');
}


$pos = $match[0][1] + strlen($match[0][0]);
$stubEnd = $pos + $this->readUint($pos, 4);
$pos += 4;

$numFiles = $this->readUint($pos, 4);
$pos += 4;


$pos += 2;


$pos += 4;

$aliasLength = $this->readUint($pos, 4);
$pos += 4 + $aliasLength;

$metadataLength = $this->readUint($pos, 4);
$pos += 4 + $metadataLength;

while ($pos < $stubEnd) {
$filenameLength = $this->readUint($pos, 4);
$pos += 4 + $filenameLength;


$pos += 4;


$timeStampBytes = pack('L', $timestamp);
$this->contents[$pos + 0] = $timeStampBytes[0];
$this->contents[$pos + 1] = $timeStampBytes[1];
$this->contents[$pos + 2] = $timeStampBytes[2];
$this->contents[$pos + 3] = $timeStampBytes[3];


$pos += 4*4;

$metadataLength = $this->readUint($pos, 4);
$pos += 4 + $metadataLength;

$numFiles--;
}

if ($numFiles !== 0) {
throw new \LogicException('All files were not processed, something must have gone wrong');
}
}








public function save($path, $signatureAlgo)
{
$pos = $this->determineSignatureBegin();

$algos = array(
\Phar::MD5 => 'md5',
\Phar::SHA1 => 'sha1',
\Phar::SHA256 => 'sha256',
\Phar::SHA512 => 'sha512',
);

if (!isset($algos[$signatureAlgo])) {
throw new \UnexpectedValueException('Invalid hash algorithm given: '.$signatureAlgo.' expected one of Phar::MD5, Phar::SHA1, Phar::SHA256 or Phar::SHA512');
}
$algo = $algos[$signatureAlgo];



$signature = hash($algo, substr($this->contents, 0, $pos), true)

. pack('L', $signatureAlgo)

. 'GBMB';

$this->contents = substr($this->contents, 0, $pos) . $signature;

return file_put_contents($path, $this->contents);
}

private function readUint($pos, $bytes)
{
$res = unpack('V', substr($this->contents, $pos, $bytes));

return $res[1];
}






private function determineSignatureBegin()
{

if (!preg_match('{__HALT_COMPILER\(\);(?: +\?>)?\r?\n}', $this->contents, $match, PREG_OFFSET_CAPTURE)) {
throw new \RuntimeException('Could not detect the stub\'s end in the phar');
}


$pos = $match[0][1] + strlen($match[0][0]);
$manifestEnd = $pos + 4 + $this->readUint($pos, 4);

$pos += 4;
$numFiles = $this->readUint($pos, 4);

$pos += 4;


$pos += 2;


$pos += 4;

$aliasLength = $this->readUint($pos, 4);
$pos += 4 + $aliasLength;

$metadataLength = $this->readUint($pos, 4);
$pos += 4 + $metadataLength;

$compressedSizes = 0;
while (($numFiles > 0) && ($pos < $manifestEnd - 24)) {
$filenameLength = $this->readUint($pos, 4);
$pos += 4 + $filenameLength;


$pos += 2*4;

$compressedSizes += $this->readUint($pos, 4);

$pos += 3*4;

$metadataLength = $this->readUint($pos, 4);
$pos += 4 + $metadataLength;

$numFiles--;
}

if ($numFiles !== 0) {
throw new \LogicException('All files were not processed, something must have gone wrong');
}

return $manifestEnd + $compressedSizes;
}
}
