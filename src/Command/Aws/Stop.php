<?php

namespace WpEcs\Command\Aws;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use WpEcs\Aws\HostingStackCollection;

class Stop extends Command
{
    /**
     * @var HostingStackCollection
     */
    protected $collection;

    public function __construct(HostingStackCollection $collection)
    {
        $this->collection = $collection;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('aws:stop')
            ->setDescription('Stop an AWS hosting stack')
            ->addArgument(
                'instance',
                InputArgument::REQUIRED,
                'Instance identifier. Valid format: "<appname>:<env>"'
            )
            ->addOption(
                'production',
                'p',
                InputOption::VALUE_NONE,
                "Don't ask for confirmation before stopping a production instance"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stackName = $this->getStackName($input->getArgument('instance'));
        $stack = $this->collection->getStack($stackName);
        $stack->stop();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $stackName = $this->getStackName($input->getArgument('instance'));

        if (preg_match('/-prod$/', $stackName) && !$input->getOption('production')) {
            $output->writeln("<error>It looks like you're trying to stop a production instance.</error>");
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
