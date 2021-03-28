<?php

namespace WpEcs\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use WpEcs\Wordpress\AbstractInstance;
use WpEcs\Wordpress\LocalInstance;

class MigrationDatabaseRewrite
{
    /**
     * The destination WordPress instance
     *
     * @var AbstractInstance
     */
    protected $dest;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * The source WordPress instance
     *
     * @var AbstractInstance
     */
    protected $source;

    public function __construct(AbstractInstance $source, AbstractInstance $destination, OutputInterface $output)
    {
        $this->source = $source;
        $this->dest = $destination;
        $this->output = $output;
    }

    /**
     * Perform search & replace operations on the `destination` database
     * This step is necessary to rewrite the hostname and other content which references the `source` instance
     *
     * - Rewrite media upload URLs
     * - Rewrite website URLs
     * - Rewrite references to site domain name
     */
    public function rewrite()
    {
        // Rewrite media upload URLs
        $this->output->writeln(
            'Rewriting references to media uploads base URL...',
            OutputInterface::VERBOSITY_VERBOSE
        );

        $this->dbSearchReplace(
            $this->source->uploadsBaseUrl,
            $this->dest->uploadsBaseUrl,
            !empty($this->source->url)
        );

        // Rewrite 'http://example.com' to 'http://newdomain.com'
        // Since this contains the protocol ('http:') it will migrate between http/https
        $this->output->writeln('Rewriting references to site URL...', OutputInterface::VERBOSITY_VERBOSE);
        $this->rewriteEnvVar('WP_HOME');

        // Rewrite 'example.com' to 'new-domain.com'
        // This rewrites any other references to the domain name which didn't match WP_HOME above
        $this->output->writeln('Rewriting references to server name...', OutputInterface::VERBOSITY_VERBOSE);
        $this->rewriteEnvVar('SERVER_NAME');

        // M U L T I S I T E --> SINGLE site migrations...
        $this->multisiteSingle();

        // M U L T I S I T E --> COMPLETE migrations...
        $this->multisiteComplete();
    }

    protected function multisiteComplete()
    {
        if ($this->source->multisite && $this->source->url === null) {
            // heading
            $terminalWidth = (new Terminal())->getWidth();
            $separator = str_repeat('-', $terminalWidth);
            $this->output->writeln($separator, OutputInterface::VERBOSITY_VERBOSE);
            $this->output->writeln('|--> <info>MULTISITE</info>', OutputInterface::VERBOSITY_VERBOSE);
            $this->output->writeln($separator . "\n", OutputInterface::VERBOSITY_VERBOSE);

            $sitesList = $this->source->getBlogList();
            $customURLs = $this->source->getSubSiteUrls();

            $count = 0;
            foreach ($sitesList as $site) {
                foreach ($customURLs as $subSite => $customURL) {
                    if ($site->url === $customURL && !$this->dest->isProd()) {
                        if ($count > 0) {
                            $this->output->writeln($separator . "\n", OutputInterface::VERBOSITY_VERBOSE);
                        }

                        $localDomain = $this->dest->env('WP_HOME');
                        $site->url = rtrim($site->url, '/');

                        $this->dbSearchReplace(
                            $site->url,
                            $localDomain . '/' . $subSite,
                            true
                        );

                        $this->dbSearchReplace(
                            $this->urlHost($site->url),
                            $this->urlHost($localDomain),
                            true
                        );

                        $this->updatePath($subSite, $site->blog_id);
                        $this->options($localDomain . '/' . $subSite, 'home', $site->blog_id);
                        $this->options($localDomain . '/' . $subSite, 'siteurl', $site->blog_id);
                        $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                        $count++;
                    }
                }
            }

            // repair wp_sitemeta moj_sub_site_urls entry
            $this->dest->execute('wp --allow-root network meta delete 1 moj_sub_site_urls');
            $this->dest->execute('wp --allow-root network meta add 1 moj_sub_site_urls ' . json_encode($customURLs) . ' --format=json');
        }
    }

    protected function multisiteSingle()
    {
        if ($this->source->multisite && !empty($this->source->url)) {
            // heading
            $terminalWidth = (new Terminal())->getWidth();
            $separator = str_repeat('-', $terminalWidth);
            $this->output->writeln($separator, OutputInterface::VERBOSITY_VERBOSE);
            $this->output->writeln('|--> <info>MULTISITE</info>', OutputInterface::VERBOSITY_VERBOSE);
            $this->output->writeln($separator . "\n", OutputInterface::VERBOSITY_VERBOSE);

            $sourceURL = rtrim($this->source->url(), '/');
            $destinationURL = rtrim($this->dest->url(), '/');

            // Rewrite 'http://example.com' to 'http://newdomain.com'
            // Since this contains the protocol ('http:') it will migrate between http/https
            $this->dbSearchReplace(
                $sourceURL,
                $destinationURL,
                true
            );

            // Rewrite 'example.com' to 'new-domain.com'
            // This rewrites any other references to the domain name which didn't match url() above
            // It also splits the domain and creates relative entries if dealing with a custom domain

            // we need to handle wp_blogs table domain and path split on all sites
            $replace = $destinationURL;
            if (!$this->dest->isProd()) {
                $replace = strstr($this->urlHost($destinationURL), '/', true);
            }

            $this->dbSearchReplace(
                $this->urlHost($sourceURL),
                $replace,
                true
            );

            $this->updatePath(basename($destinationURL));
            $this->options($destinationURL, 'home', $this->dest->getBlogId());
            $this->options($destinationURL, 'siteurl', $this->dest->getBlogId());
            $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        }
    }

    public function urlHost($url)
    {
        $urlParts = parse_url($url);
        return $urlParts['host'] . (strlen($urlParts['path'] ?? '') > 1 ? $urlParts['path'] : '');
    }

    /**
     * Perform a search & replace operation on the `destination` database
     *
     * @param string $search
     * @param string $replace
     * @param bool $switch
     */
    public function dbSearchReplace(string $search, string $replace, bool $switch = false)
    {
        $command = [
            'wp',
            'search-replace',
            '--report-changed-only',
            '--allow-root',
            '--skip-columns=guid',
            '--skip-tables=wp_users' . (!empty($this->source->url) ? ',wp_sitemeta' : ''),
            $search,
            $replace,
        ];

        $this->multisiteFlags($command, $switch);

        $this->output->writeln(
            "Search for <comment>\"$search\"</comment> & replace with <comment>\"$replace\"</comment>",
            OutputInterface::VERBOSITY_VERBOSE
        );

        $result = $this->dest->execute($command);
        $this->output->writeln($result, OutputInterface::VERBOSITY_VERBOSE);
    }

    public function updatePath($path, $blogId = 0)
    {
        $blogId = ($blogId === 0 ? $this->dest->getBlogId() : $blogId);
        $path = trim($path, '/');

        $command = [
            'wp',
            'db',
            'query',
            'UPDATE `wp_blogs` SET `path` = \'/' . $path . '/\' WHERE `wp_blogs`.`blog_id` = ' . $blogId . ';',
            '--allow-root'
        ];

        $this->output->writeln(
            "Register <comment>\"$path\"</comment> for site with ID <comment>\"$blogId\"</comment>",
            OutputInterface::VERBOSITY_VERBOSE
        );

        $this->dest->execute($command);
    }

    /**
     * Search & replace the DB for an environment variable
     * Given the name of an environment variable, replace the value from `source` with the value in `destination`
     *
     * e.g. $this->rewriteEnvVar('SERVER_NAME')
     *      might perform a database search & replace for
     *      'example.com' => 'new-hostname.com'
     *
     * @param string $var
     */
    protected function rewriteEnvVar(string $var)
    {
        $this->dbSearchReplace(
            $this->source->env($var),
            $this->dest->env($var)
        );
    }

    protected function multisiteFlags(&$command, $switch = false)
    {
        if ($this->source->multisite) {
            if (!empty($this->source->url)) {
                $command[] = '--network';
            } else {
                $command[] = '--network';
                $command[] = '--url=' . ($switch ? $this->dest->env('WP_HOME') : $this->source->env('WP_HOME'));
            }
        }
    }

    protected function options($domain, $name, $blogId)
    {
        $update = 'UPDATE `wp_' . $blogId . '_options` SET `option_value` = \'' . $domain . '\'';
        $where  = 'WHERE `wp_' . $blogId . '_options`.`option_name` LIKE \'' . $name . '\';';

        $command = [
            'wp',
            '--allow-root',
            'db',
            'query',
            $update . ' ' . $where
        ];

        $this->output->writeln(
            "Updating <comment>\"" . $name . "\"</comment> option with <comment>\"" . $domain . "\"</comment>",
            OutputInterface::VERBOSITY_VERBOSE
        );

        $this->dest->execute($command);
    }
}
