<?php










namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Output\OutputInterface;






interface DescriptorInterface
{
public function describe(OutputInterface $output, object $object, array $options = []);
}
