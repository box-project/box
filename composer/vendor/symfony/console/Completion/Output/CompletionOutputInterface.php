<?php










namespace Symfony\Component\Console\Completion\Output;

use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Output\OutputInterface;






interface CompletionOutputInterface
{
public function write(CompletionSuggestions $suggestions, OutputInterface $output): void;
}
