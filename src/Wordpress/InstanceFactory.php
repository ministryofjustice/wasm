<?php

namespace WpEcs\Wordpress;

use Aws\Sdk;
use WpEcs\Wordpress\AwsInstance\AwsResources;
use Exception;

class InstanceFactory
{
    /**
     * Create a WordPress instance object
     * Depending on the $identifier provided, this will either be an AwsInstance or a LocalInstance
     *
     * @param string $identifier
     *
     * @return AbstractInstance
     * @throws Exception
     */
    public function create(string $identifier)
    {
        $local = $this->localIdentifier($identifier);
        if (isset($local['path'])) {
            // This is a local instance identifier
            return new LocalInstance($local['path'], $local['url']);
        }

        $awsId = $this->awsIdentifier($identifier);
        if ($awsId) {
            // This is an AWS instance identifier
            $sdk = new Sdk([
                'region' => 'eu-west-2',
                'version' => 'latest',
            ]);
            $aws = new AwsResources($awsId['appName'], $awsId['env'], $sdk);
            return new AwsInstance($awsId['appName'], $awsId['env'], $awsId['url'], $aws);
        }

        // Could not recognise this as a valid instance identifier
        $message = "Instance identifier \"$identifier\" is not valid\n";
        $message .= 'Use the format "<appname>:<env>[:<site>]" (e.g. "sitename:dev" / sitename:dev:sub-site)' . "\n";
        $message .= 'for an AWS instance, or the path to a local instance directory (this must contain a' . "\n";
        $message .= 'docker-compose.yml file).' . "\n\n";
        $message .= '"<site>" is an optional sub-site identifier used in Multisite for sub-site management.' . "\n";
        $message .= 'See: https://make.wordpress.org/cli/handbook/references/config/#global-parameters)' . "\n";
        throw new Exception($message);
    }

    /**
     * Check if the $identifier is for an AWS instance
     * If it is, return its component parts ('appName' and 'env') for instantiating an AwsInstance object
     * Else return false
     *
     * @param string $identifier
     *
     * @return array|bool
     */
    protected function awsIdentifier($identifier)
    {
        if (preg_match('/^([a-z0-9-]+):(dev|staging|prod):?([.a-z0-9-]+)?$/', $identifier, $matches)) {
            return [
                'appName' => $matches[1],
                'env' => $matches[2],
                'url' => ($matches[3] ?? null)
            ];
        }

        return false;
    }

    /**
     * Check if the $identifier is for a local instance
     * If it is, return the filesystem path to instance
     * Else return false
     *
     * @param string $identifier
     *
     * @return false|array
     */
    protected function localIdentifier($identifier)
    {
        $local['path'] = $identifier;
        if (preg_match('/^(\.):?([.a-z0-9-]+)?$/', $identifier, $matches)) {
            $local['path'] = $matches[1];
            $local['url'] = $matches[2] ?? null;
        }

        // $identifier must be a directory
        if (!is_dir($local['path'])) {
            return false;
        }

        // The directory must contain a docker-compose config file
        if (!file_exists($local['path'] . "/docker-compose.yml") &&
            !file_exists($local['path'] . "/docker-compose.yaml")
        ) {
            return false;
        }

        return $local;
    }
}
