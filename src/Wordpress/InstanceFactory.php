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
    public function create($identifier)
    {
        $path = $this->localIdentifier($identifier);
        if ($path) {
            // This is a local instance identifier
            return new LocalInstance($path);
        }

        $awsId = $this->awsIdentifier($identifier);
        if ($awsId) {
            // This is an AWS instance identifier
            $sdk = new Sdk([
                'region'  => 'eu-west-2',
                'version' => 'latest',
            ]);
            $aws = new AwsResources($awsId['appName'], $awsId['env'], $sdk);
            return new AwsInstance($awsId['appName'], $awsId['env'], $aws);
        }

        // Could not recognise this as a valid instance identifier
        $message = "Instance identifier \"$identifier\" is not valid\n";
        $message .= 'It must be in the format "<appname>:<env>" (e.g. "sitename:dev") for an AWS instance,' . "\n";
        $message .= 'or the path to a local instance directory (which must contain a docker-compose.yml file).';
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
        $matches = [];
        if (preg_match('/^([a-z0-9-]+):(dev|staging|prod)$/', $identifier, $matches)) {
            return [
                'appName' => $matches[1],
                'env'     => $matches[2],
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
     * @return string|bool
     */
    protected function localIdentifier($identifier)
    {
        // $identifier must be a directory
        if (!is_dir($identifier)) {
            return false;
        }

        // The directory must contain a docker-compose config file
        if (!file_exists("$identifier/docker-compose.yml") &&
            !file_exists("$identifier/docker-compose.yaml")
        ) {
            return false;
        }

        return $identifier;
    }
}
