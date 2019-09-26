<?php

namespace WpEcs\Command\Aws;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Aws\HostingStackCollection;
use WpEcs\Traits\ProductionInteractionTrait;

class Stop extends Command
{
    use ProductionInteractionTrait;

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
                "Ask for confirmation before stopping a production instance"
            );

        $this->prodInteractMessage = "It looks like you're trying to stop a production instance.";
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instanceIdentifier = $input->getArgument('instance');
        $stack = $this->collection->getStack(
            $this->getStackName($instanceIdentifier)
        );
        $stack->stop();
        $output->writeln("<info>Success:</info> <comment>$instanceIdentifier</comment> is being stopped");
    }
}
