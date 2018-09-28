<?php

namespace WpEcs\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Wordpress\InstanceFactory;

class Exec extends Command
{
    protected $instanceFactory;

    public function __construct(InstanceFactory $instanceFactory)
    {
        $this->instanceFactory = $instanceFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('exec')
            ->setDescription('Executes a command on a running WordPress instance')
            ->addArgument(
                'instance',
                InputArgument::REQUIRED,
                'Instance identifier. Valid format: "<appname>:<env>" or path to a local directory'
            )
            ->addArgument('cmd', InputArgument::REQUIRED, 'The command to execute');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = $this->instanceFactory->create(
            $input->getArgument('instance')
        );

        $output->write(
            $instance->execute(
                $input->getArgument('cmd')
            )
        );
    }
}
