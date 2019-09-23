<?php

namespace WpEcs\Traits;

use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A trait to help protect production instances from erroneous commands
 */
trait ProductionInteractionTrait
{
    /**
     * @var string
     * A message to inform of the specific action that was attempted
     */
    public $prodInteractMessage = "Production is protected, please consider your last action.";

    /**
     * @var string
     * The argument to test against. In most cases this is instance in the form of a string: <appname>:<env>
     */
    public $prodInteractArgument = "instance";

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $stackName = $this->getStackName($input->getArgument($this->prodInteractArgument));

        if (preg_match('/-prod$/', $stackName) && !$input->getOption('production')) {
            $output->writeln("<error>" . $this->prodInteractMessage . "</error>");
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("Are you sure you want to do that? [y/n]\n", false);
            if (!$helper->ask($input, $output, $question)) {
                throw new RuntimeException('Aborting');
            }
        }
    }

    /**
     * @param string $instance
     *
     * @return string
     */
    protected function getStackName($instance)
    {
        return str_replace(':', '-', $instance);
    }
}
