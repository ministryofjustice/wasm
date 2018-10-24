<?php

namespace WpEcs\Command\Aws;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Aws\HostingStackCollection;

class Start extends Command
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
            ->setName('aws:start')
            ->setDescription('Start an AWS hosting stack')
            ->addArgument(
                'instance',
                InputArgument::REQUIRED,
                'Instance identifier. Valid format: "<appname>:<env>"'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stackName = $this->getStackName($input->getArgument('instance'));
        $stack = $this->collection->getStack($stackName);
        $stack->start();
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
