<?php

namespace WpEcs\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Service\Migration;
use WpEcs\Wordpress\AbstractInstance;
use WpEcs\Wordpress\InstanceFactory;
use Exception;

class Migrate extends Command
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
            ->setName('migrate')
            ->setDescription('Migrate content between two WordPress instances')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source instance identifier. Valid format: "<appname>:<env>" or path to a local directory'
            )
            ->addArgument(
                'destination',
                InputArgument::REQUIRED,
                'Destination instance identifier. Valid format: "<appname>:<env>" or path to a local directory'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        $dest   = $input->getArgument('destination');

        $this->preventIfActionNotAllowed($source, $dest);

        $migration = $this->newMigration(
            $this->instanceFactory->create($source),
            $this->instanceFactory->create($dest),
            $output
        );
        $migration->migrate();

        $output->writeln("<info>Success:</info> Migrated <comment>$source</comment> to <comment>$dest</comment>");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) because we don't use $output, but the parent method defines it
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        $dest   = $input->getArgument('destination');

        if (!empty($source) && $source == $dest) {
            throw new InvalidArgumentException('"source" and "destination" arguments cannot be the same');
        }
    }

    /**
     * Proxy function to return a new Migration object
     * This exists to make the class more testable, since the Migration object becomes mock-able
     *
     * @param AbstractInstance $source
     * @param AbstractInstance $destination
     * @param OutputInterface $output
     *
     * @return Migration
     */
    public function newMigration(AbstractInstance $source, AbstractInstance $destination, OutputInterface $output)
    {
        return new Migration($source, $destination, $output);
    }

    public function preventIfActionNotAllowed($source, $dest)
    {
        $source = strstr($source, ':') ?? $source;
        $dest = strstr($dest, ':') ?? $dest;

        if (in_array($source, ['.', ':dev']) && $dest === ':staging') {
            $dest = ltrim($dest, ':');
            $message = "Operation cancelled: Instance identifier \"$dest\" is not valid for a migrate destination";
            throw new Exception($message, 100);
        }
    }
}
