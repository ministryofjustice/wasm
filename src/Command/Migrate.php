<?php

namespace WpEcs\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Service\Migration;
use WpEcs\Traits\Debug;
use WpEcs\Traits\ProductionInteractionTrait;
use WpEcs\Wordpress\AbstractInstance;
use WpEcs\Wordpress\InstanceFactory;

class Migrate extends Command
{
    use ProductionInteractionTrait, Debug;

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
            )
            ->addArgument('urls', InputArgument::OPTIONAL, 'Multisite URL mapping. Valid format: "<src-url>:<dest-url>"')
            ->addOption(
                'production',
                'p',
                InputOption::VALUE_NONE,
                "Ask for confirmation before migrating to a production instance"
            );

        $this->prodInteractArgument = "destination";
        $this->prodInteractMessage = "It looks like you're trying to migrate data to a production instance.";
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // multisite src/dest url
        $url = [
            'src' => '',
            'dest' => ''
        ];

        $source = $input->getArgument('source');
        $dest = $input->getArgument('destination');
        $url['src'] = $input->getArgument('url-source');
        $url['dest'] = $input->getArgument('url-dest');

        $migration = $this->newMigration(
            $this->instanceFactory->create($source),
            $this->instanceFactory->create($dest),
            $url,
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
        $dest = $input->getArgument('destination');

        if (!empty($source) && $source == $dest) {
            throw new InvalidArgumentException('"source" and "destination" arguments cannot be the same');
        }
    }

    /**
     * Proxy function to return a new Migration object
     * This exists to make the class more testable, since the Migration object becomes mockable
     *
     * @param AbstractInstance $source
     * @param AbstractInstance $destination
     * @param OutputInterface $output
     *
     * @return Migration
     */
    public function newMigration(AbstractInstance $source, AbstractInstance $destination, $url, OutputInterface $output)
    {
        return new Migration($source, $destination, $url, $output);
    }
}
