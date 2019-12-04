<?php

namespace WpEcs\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use WpEcs\Wordpress\InstanceFactory;

class Shell extends Command
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
            ->setName('shell')
            ->setDescription('Opens an interactive shell on a running WordPress instance')
            ->addArgument(
                'instance',
                InputArgument::REQUIRED,
                'Instance identifier. Valid format: "<appname>:<env>" or path to a local directory'
            )
            ->addArgument('shell', InputArgument::OPTIONAL, 'The shell to execute', 'bash');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) because we don't use $output, but the parent method defines it
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = $this->instanceFactory->create(
            $input->getArgument('instance')
        );

        $terminal = new Terminal();

        $process = $instance->newCommand(
            $input->getArgument('shell'),
            [
                '-ti',
                "-e COLUMNS={$terminal->getWidth()}",
                "-e LINES={$terminal->getHeight()}",
            ],
            ['-t']
        );
        $process->setTty(true);
        $process->setTimeout(300);
        $process->run();
    }
}
