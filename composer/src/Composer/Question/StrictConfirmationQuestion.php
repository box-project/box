<?php declare(strict_types=1);











namespace Composer\Question;

use Composer\Pcre\Preg;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Question\Question;








class StrictConfirmationQuestion extends Question
{

private $trueAnswerRegex;

private $falseAnswerRegex;









public function __construct(string $question, bool $default = true, string $trueAnswerRegex = '/^y(?:es)?$/i', string $falseAnswerRegex = '/^no?$/i')
{
parent::__construct($question, (bool) $default);

$this->trueAnswerRegex = $trueAnswerRegex;
$this->falseAnswerRegex = $falseAnswerRegex;
$this->setNormalizer($this->getDefaultNormalizer());
$this->setValidator($this->getDefaultValidator());
}




private function getDefaultNormalizer(): callable
{
$default = $this->getDefault();
$trueRegex = $this->trueAnswerRegex;
$falseRegex = $this->falseAnswerRegex;

return static function ($answer) use ($default, $trueRegex, $falseRegex) {
if (is_bool($answer)) {
return $answer;
}
if (empty($answer) && !empty($default)) {
return $default;
}

if (Preg::isMatch($trueRegex, $answer)) {
return true;
}

if (Preg::isMatch($falseRegex, $answer)) {
return false;
}

return null;
};
}




private function getDefaultValidator(): callable
{
return static function ($answer): bool {
if (!is_bool($answer)) {
throw new InvalidArgumentException('Please answer yes, y, no, or n.');
}

return $answer;
};
}
}
