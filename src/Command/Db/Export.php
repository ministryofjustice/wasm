<?php

namespace WpEcs\Command\Db;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Wordpress\AbstractInstance;
use WpEcs\Wordpress\InstanceFactory;

class Export extends Command
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
            ->setName('db:export')
            ->setDescription('Export the database of a running WordPress instance')
            ->addArgument(
                'instance',
                InputArgument::REQUIRED,
                'Instance identifier. Valid format: "<appname>:<env>" or path to a local directory'
            )
            ->addArgument('filename', InputArgument::OPTIONAL, 'The file path to save to');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = $this->instanceFactory->create(
            $input->getArgument('instance')
        );

        $toFile = $this->getFilename($input, $instance);
        $fh     = fopen($toFile, 'w');
        $instance->exportDatabase($fh);
        fclose($fh);

        $output->writeln("<info>Success:</info> Database exported to <comment>$toFile</comment>");
    }

    public function getFilename(InputInterface $input, AbstractInstance $instance)
    {
        $filename = $input->getArgument('filename');

        if (is_null($filename)) {
            $filename = sprintf(
                '%s-%s.sql',
                $instance->name,
                date('Y-m-d-His')
            );
        }

        return $filename;
    }
}
