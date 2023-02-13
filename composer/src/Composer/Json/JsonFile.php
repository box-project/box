<?php declare(strict_types=1);











namespace Composer\Json;

use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use JsonSchema\Validator;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Composer\Util\HttpDownloader;
use Composer\IO\IOInterface;
use Composer\Downloader\TransportException;







class JsonFile
{
public const LAX_SCHEMA = 1;
public const STRICT_SCHEMA = 2;
public const AUTH_SCHEMA = 3;


public const JSON_UNESCAPED_SLASHES = 64;

public const JSON_PRETTY_PRINT = 128;

public const JSON_UNESCAPED_UNICODE = 256;

public const COMPOSER_SCHEMA_PATH = __DIR__ . '/../../../res/composer-schema.json';


private $path;

private $httpDownloader;

private $io;









public function __construct(string $path, ?HttpDownloader $httpDownloader = null, ?IOInterface $io = null)
{
$this->path = $path;

if (null === $httpDownloader && Preg::isMatch('{^https?://}i', $path)) {
throw new \InvalidArgumentException('http urls require a HttpDownloader instance to be passed');
}
$this->httpDownloader = $httpDownloader;
$this->io = $io;
}

public function getPath(): string
{
return $this->path;
}




public function exists(): bool
{
return is_file($this->path);
}








public function read()
{
try {
if ($this->httpDownloader) {
$json = $this->httpDownloader->get($this->path)->getBody();
} else {
if (!Filesystem::isReadable($this->path)) {
throw new \RuntimeException('The file "'.$this->path.'" is not readable.');
}
if ($this->io && $this->io->isDebug()) {
$realpathInfo = '';
$realpath = realpath($this->path);
if (false !== $realpath && $realpath !== $this->path) {
$realpathInfo = ' (' . $realpath . ')';
}
$this->io->writeError('Reading ' . $this->path . $realpathInfo);
}
$json = file_get_contents($this->path);
}
} catch (TransportException $e) {
throw new \RuntimeException($e->getMessage(), 0, $e);
} catch (\Exception $e) {
throw new \RuntimeException('Could not read '.$this->path."\n\n".$e->getMessage());
}

if ($json === false) {
throw new \RuntimeException('Could not read '.$this->path);
}

return static::parseJson($json, $this->path);
}









public function write(array $hash, int $options = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
{
if ($this->path === 'php://memory') {
file_put_contents($this->path, static::encode($hash, $options));

return;
}

$dir = dirname($this->path);
if (!is_dir($dir)) {
if (file_exists($dir)) {
throw new \UnexpectedValueException(
realpath($dir).' exists and is not a directory.'
);
}
if (!@mkdir($dir, 0777, true)) {
throw new \UnexpectedValueException(
$dir.' does not exist and could not be created.'
);
}
}

$retries = 3;
while ($retries--) {
try {
$this->filePutContentsIfModified($this->path, static::encode($hash, $options). ($options & JSON_PRETTY_PRINT ? "\n" : ''));
break;
} catch (\Exception $e) {
if ($retries > 0) {
usleep(500000);
continue;
}

throw $e;
}
}
}






private function filePutContentsIfModified(string $path, string $content)
{
$currentContent = @file_get_contents($path);
if (false === $currentContent || $currentContent !== $content) {
return file_put_contents($path, $content);
}

return 0;
}












public function validateSchema(int $schema = self::STRICT_SCHEMA, ?string $schemaFile = null): bool
{
if (!Filesystem::isReadable($this->path)) {
throw new \RuntimeException('The file "'.$this->path.'" is not readable.');
}
$content = file_get_contents($this->path);
$data = json_decode($content);

if (null === $data && 'null' !== $content) {
self::validateSyntax($content, $this->path);
}

return self::validateJsonSchema($this->path, $data, $schema, $schemaFile);
}












public static function validateJsonSchema(string $source, $data, int $schema, ?string $schemaFile = null): bool
{
$isComposerSchemaFile = false;
if (null === $schemaFile) {
$isComposerSchemaFile = true;
$schemaFile = self::COMPOSER_SCHEMA_PATH;
}


if (false === strpos($schemaFile, '://')) {
$schemaFile = 'file://' . $schemaFile;
}

$schemaData = (object) ['$ref' => $schemaFile];

if ($schema === self::LAX_SCHEMA) {
$schemaData->additionalProperties = true;
$schemaData->required = [];
} elseif ($schema === self::STRICT_SCHEMA && $isComposerSchemaFile) {
$schemaData->additionalProperties = false;
$schemaData->required = ['name', 'description'];
} elseif ($schema === self::AUTH_SCHEMA && $isComposerSchemaFile) {
$schemaData = (object) ['$ref' => $schemaFile.'#/properties/config', '$schema' => "https://json-schema.org/draft-04/schema#"];
}

$validator = new Validator();
$validator->check($data, $schemaData);

if (!$validator->isValid()) {
$errors = [];
foreach ((array) $validator->getErrors() as $error) {
$errors[] = ($error['property'] ? $error['property'].' : ' : '').$error['message'];
}
throw new JsonValidationException('"'.$source.'" does not match the expected JSON schema', $errors);
}

return true;
}








public static function encode($data, int $options = 448)
{
$json = json_encode($data, $options);
if (false === $json) {
self::throwEncodeError(json_last_error());
}

return $json;
}







private static function throwEncodeError(int $code): void
{
switch ($code) {
case JSON_ERROR_DEPTH:
$msg = 'Maximum stack depth exceeded';
break;
case JSON_ERROR_STATE_MISMATCH:
$msg = 'Underflow or the modes mismatch';
break;
case JSON_ERROR_CTRL_CHAR:
$msg = 'Unexpected control character found';
break;
case JSON_ERROR_UTF8:
$msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
break;
default:
$msg = 'Unknown error';
}

throw new \RuntimeException('JSON encoding failed: '.$msg);
}










public static function parseJson(?string $json, ?string $file = null)
{
if (null === $json) {
return null;
}
$data = json_decode($json, true);
if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
self::validateSyntax($json, $file);
}

return $data;
}









protected static function validateSyntax(string $json, ?string $file = null): bool
{
$parser = new JsonParser();
$result = $parser->lint($json);
if (null === $result) {
if (defined('JSON_ERROR_UTF8') && JSON_ERROR_UTF8 === json_last_error()) {
throw new \UnexpectedValueException('"'.$file.'" is not UTF-8, could not parse as JSON');
}

return true;
}

throw new ParsingException('"'.$file.'" does not contain valid JSON'."\n".$result->getMessage(), $result->getDetails());
}
}
