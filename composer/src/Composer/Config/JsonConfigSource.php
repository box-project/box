<?php declare(strict_types=1);











namespace Composer\Config;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Json\JsonValidationException;
use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use Composer\Util\Silencer;







class JsonConfigSource implements ConfigSourceInterface
{



private $file;




private $authConfig;




public function __construct(JsonFile $file, bool $authConfig = false)
{
$this->file = $file;
$this->authConfig = $authConfig;
}




public function getName(): string
{
return $this->file->getPath();
}




public function addRepository(string $name, $config, bool $append = true): void
{
$this->manipulateJson('addRepository', static function (&$config, $repo, $repoConfig) use ($append): void {


if (isset($config['repositories'])) {
foreach ($config['repositories'] as $index => $val) {
if ($index === $repo) {
continue;
}
if (is_numeric($index) && ($val === ['packagist' => false] || $val === ['packagist.org' => false])) {
unset($config['repositories'][$index]);
$config['repositories']['packagist.org'] = false;
break;
}
}
}

if ($append) {
$config['repositories'][$repo] = $repoConfig;
} else {
$config['repositories'] = [$repo => $repoConfig] + $config['repositories'];
}
}, $name, $config, $append);
}




public function removeRepository(string $name): void
{
$this->manipulateJson('removeRepository', static function (&$config, $repo): void {
unset($config['repositories'][$repo]);
}, $name);
}




public function addConfigSetting(string $name, $value): void
{
$authConfig = $this->authConfig;
$this->manipulateJson('addConfigSetting', static function (&$config, $key, $val) use ($authConfig): void {
if (Preg::isMatch('{^(bitbucket-oauth|github-oauth|gitlab-oauth|gitlab-token|bearer|http-basic|platform)\.}', $key)) {
[$key, $host] = explode('.', $key, 2);
if ($authConfig) {
$config[$key][$host] = $val;
} else {
$config['config'][$key][$host] = $val;
}
} else {
$config['config'][$key] = $val;
}
}, $name, $value);
}




public function removeConfigSetting(string $name): void
{
$authConfig = $this->authConfig;
$this->manipulateJson('removeConfigSetting', static function (&$config, $key) use ($authConfig): void {
if (Preg::isMatch('{^(bitbucket-oauth|github-oauth|gitlab-oauth|gitlab-token|bearer|http-basic|platform)\.}', $key)) {
[$key, $host] = explode('.', $key, 2);
if ($authConfig) {
unset($config[$key][$host]);
} else {
unset($config['config'][$key][$host]);
}
} else {
unset($config['config'][$key]);
}
}, $name);
}




public function addProperty(string $name, $value): void
{
$this->manipulateJson('addProperty', static function (&$config, $key, $val): void {
if (strpos($key, 'extra.') === 0 || strpos($key, 'scripts.') === 0) {
$bits = explode('.', $key);
$last = array_pop($bits);
$arr = &$config[reset($bits)];
foreach ($bits as $bit) {
if (!isset($arr[$bit])) {
$arr[$bit] = [];
}
$arr = &$arr[$bit];
}
$arr[$last] = $val;
} else {
$config[$key] = $val;
}
}, $name, $value);
}




public function removeProperty(string $name): void
{
$this->manipulateJson('removeProperty', static function (&$config, $key): void {
if (strpos($key, 'extra.') === 0 || strpos($key, 'scripts.') === 0) {
$bits = explode('.', $key);
$last = array_pop($bits);
$arr = &$config[reset($bits)];
foreach ($bits as $bit) {
if (!isset($arr[$bit])) {
return;
}
$arr = &$arr[$bit];
}
unset($arr[$last]);
} else {
unset($config[$key]);
}
}, $name);
}




public function addLink(string $type, string $name, string $value): void
{
$this->manipulateJson('addLink', static function (&$config, $type, $name, $value): void {
$config[$type][$name] = $value;
}, $type, $name, $value);
}




public function removeLink(string $type, string $name): void
{
$this->manipulateJson('removeSubNode', static function (&$config, $type, $name): void {
unset($config[$type][$name]);
}, $type, $name);
$this->manipulateJson('removeMainKeyIfEmpty', static function (&$config, $type): void {
if (0 === count($config[$type])) {
unset($config[$type]);
}
}, $type);
}




private function manipulateJson(string $method, callable $fallback, ...$args): void
{
if ($this->file->exists()) {
if (!is_writable($this->file->getPath())) {
throw new \RuntimeException(sprintf('The file "%s" is not writable.', $this->file->getPath()));
}

if (!Filesystem::isReadable($this->file->getPath())) {
throw new \RuntimeException(sprintf('The file "%s" is not readable.', $this->file->getPath()));
}

$contents = file_get_contents($this->file->getPath());
} elseif ($this->authConfig) {
$contents = "{\n}\n";
} else {
$contents = "{\n    \"config\": {\n    }\n}\n";
}

$manipulator = new JsonManipulator($contents);

$newFile = !$this->file->exists();


if ($this->authConfig && $method === 'addConfigSetting') {
$method = 'addSubNode';
[$mainNode, $name] = explode('.', $args[0], 2);
$args = [$mainNode, $name, $args[1]];
} elseif ($this->authConfig && $method === 'removeConfigSetting') {
$method = 'removeSubNode';
[$mainNode, $name] = explode('.', $args[0], 2);
$args = [$mainNode, $name];
}


if (call_user_func_array([$manipulator, $method], $args)) {
file_put_contents($this->file->getPath(), $manipulator->getContents());
} else {

$config = $this->file->read();
$this->arrayUnshiftRef($args, $config);
$fallback(...$args);

foreach (['require', 'require-dev', 'conflict', 'provide', 'replace', 'suggest', 'config', 'autoload', 'autoload-dev', 'scripts', 'scripts-descriptions', 'support'] as $prop) {
if (isset($config[$prop]) && $config[$prop] === []) {
$config[$prop] = new \stdClass;
}
}
foreach (['psr-0', 'psr-4'] as $prop) {
if (isset($config['autoload'][$prop]) && $config['autoload'][$prop] === []) {
$config['autoload'][$prop] = new \stdClass;
}
if (isset($config['autoload-dev'][$prop]) && $config['autoload-dev'][$prop] === []) {
$config['autoload-dev'][$prop] = new \stdClass;
}
}
foreach (['platform', 'http-basic', 'bearer', 'gitlab-token', 'gitlab-oauth', 'github-oauth', 'preferred-install'] as $prop) {
if (isset($config['config'][$prop]) && $config['config'][$prop] === []) {
$config['config'][$prop] = new \stdClass;
}
}
$this->file->write($config);
}

try {
$this->file->validateSchema(JsonFile::LAX_SCHEMA);
} catch (JsonValidationException $e) {

file_put_contents($this->file->getPath(), $contents);
throw new \RuntimeException('Failed to update composer.json with a valid format, reverting to the original content. Please report an issue to us with details (command you run and a copy of your composer.json). '.PHP_EOL.implode(PHP_EOL, $e->getErrors()), 0, $e);
}

if ($newFile) {
Silencer::call('chmod', $this->file->getPath(), 0600);
}
}







private function arrayUnshiftRef(array &$array, &$value): int
{
$return = array_unshift($array, '');
$array[0] = &$value;

return $return;
}
}
