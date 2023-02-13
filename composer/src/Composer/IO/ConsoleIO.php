<?php declare(strict_types=1);











namespace Composer\IO;

use Composer\Question\StrictConfirmationQuestion;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;







class ConsoleIO extends BaseIO
{

protected $input;

protected $output;

protected $helperSet;

protected $lastMessage = '';

protected $lastMessageErr = '';


private $startTime;

private $verbosityMap;








public function __construct(InputInterface $input, OutputInterface $output, HelperSet $helperSet)
{
$this->input = $input;
$this->output = $output;
$this->helperSet = $helperSet;
$this->verbosityMap = [
self::QUIET => OutputInterface::VERBOSITY_QUIET,
self::NORMAL => OutputInterface::VERBOSITY_NORMAL,
self::VERBOSE => OutputInterface::VERBOSITY_VERBOSE,
self::VERY_VERBOSE => OutputInterface::VERBOSITY_VERY_VERBOSE,
self::DEBUG => OutputInterface::VERBOSITY_DEBUG,
];
}




public function enableDebugging(float $startTime)
{
$this->startTime = $startTime;
}




public function isInteractive()
{
return $this->input->isInteractive();
}




public function isDecorated()
{
return $this->output->isDecorated();
}




public function isVerbose()
{
return $this->output->isVerbose();
}




public function isVeryVerbose()
{
return $this->output->isVeryVerbose();
}




public function isDebug()
{
return $this->output->isDebug();
}




public function write($messages, bool $newline = true, int $verbosity = self::NORMAL)
{
$this->doWrite($messages, $newline, false, $verbosity);
}




public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL)
{
$this->doWrite($messages, $newline, true, $verbosity);
}




public function writeRaw($messages, bool $newline = true, int $verbosity = self::NORMAL)
{
$this->doWrite($messages, $newline, false, $verbosity, true);
}




public function writeErrorRaw($messages, bool $newline = true, int $verbosity = self::NORMAL)
{
$this->doWrite($messages, $newline, true, $verbosity, true);
}




private function doWrite($messages, bool $newline, bool $stderr, int $verbosity, bool $raw = false): void
{
$sfVerbosity = $this->verbosityMap[$verbosity];
if ($sfVerbosity > $this->output->getVerbosity()) {
return;
}

if ($raw) {
$sfVerbosity |= OutputInterface::OUTPUT_RAW;
}

if (null !== $this->startTime) {
$memoryUsage = memory_get_usage() / 1024 / 1024;
$timeSpent = microtime(true) - $this->startTime;
$messages = array_map(static function ($message) use ($memoryUsage, $timeSpent): string {
return sprintf('[%.1fMiB/%.2fs] %s', $memoryUsage, $timeSpent, $message);
}, (array) $messages);
}

if (true === $stderr && $this->output instanceof ConsoleOutputInterface) {
$this->output->getErrorOutput()->write($messages, $newline, $sfVerbosity);
$this->lastMessageErr = implode($newline ? "\n" : '', (array) $messages);

return;
}

$this->output->write($messages, $newline, $sfVerbosity);
$this->lastMessage = implode($newline ? "\n" : '', (array) $messages);
}




public function overwrite($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL)
{
$this->doOverwrite($messages, $newline, $size, false, $verbosity);
}




public function overwriteError($messages, bool $newline = true, ?int $size = null, int $verbosity = self::NORMAL)
{
$this->doOverwrite($messages, $newline, $size, true, $verbosity);
}




private function doOverwrite($messages, bool $newline, ?int $size, bool $stderr, int $verbosity): void
{

$messages = implode($newline ? "\n" : '', (array) $messages);


if (!isset($size)) {

$size = strlen(strip_tags($stderr ? $this->lastMessageErr : $this->lastMessage));
}

$this->doWrite(str_repeat("\x08", $size), false, $stderr, $verbosity);


$this->doWrite($messages, false, $stderr, $verbosity);




$fill = $size - strlen(strip_tags($messages));
if ($fill > 0) {

$this->doWrite(str_repeat(' ', $fill), false, $stderr, $verbosity);

$this->doWrite(str_repeat("\x08", $fill), false, $stderr, $verbosity);
}

if ($newline) {
$this->doWrite('', true, $stderr, $verbosity);
}

if ($stderr) {
$this->lastMessageErr = $messages;
} else {
$this->lastMessage = $messages;
}
}




public function getProgressBar(int $max = 0)
{
return new ProgressBar($this->getErrorOutput(), $max);
}




public function ask($question, $default = null)
{

$helper = $this->helperSet->get('question');
$question = new Question($question, $default);

return $helper->ask($this->input, $this->getErrorOutput(), $question);
}




public function askConfirmation($question, $default = true)
{

$helper = $this->helperSet->get('question');
$question = new StrictConfirmationQuestion($question, $default);

return $helper->ask($this->input, $this->getErrorOutput(), $question);
}




public function askAndValidate($question, $validator, $attempts = null, $default = null)
{

$helper = $this->helperSet->get('question');
$question = new Question($question, $default);
$question->setValidator($validator);
$question->setMaxAttempts($attempts);

return $helper->ask($this->input, $this->getErrorOutput(), $question);
}




public function askAndHideAnswer($question)
{

$helper = $this->helperSet->get('question');
$question = new Question($question);
$question->setHidden(true);

return $helper->ask($this->input, $this->getErrorOutput(), $question);
}




public function select($question, $choices, $default, $attempts = false, $errorMessage = 'Value "%s" is invalid', $multiselect = false)
{

$helper = $this->helperSet->get('question');
$question = new ChoiceQuestion($question, $choices, $default);
$question->setMaxAttempts($attempts ?: null); 
$question->setErrorMessage($errorMessage);
$question->setMultiselect($multiselect);

$result = $helper->ask($this->input, $this->getErrorOutput(), $question);

$isAssoc = (bool) \count(array_filter(array_keys($choices), 'is_string'));
if ($isAssoc) {
return $result;
}

if (!is_array($result)) {
return (string) array_search($result, $choices, true);
}

$results = [];
foreach ($choices as $index => $choice) {
if (in_array($choice, $result, true)) {
$results[] = (string) $index;
}
}

return $results;
}

public function getTable(): Table
{
return new Table($this->output);
}

private function getErrorOutput(): OutputInterface
{
if ($this->output instanceof ConsoleOutputInterface) {
return $this->output->getErrorOutput();
}

return $this->output;
}
}
