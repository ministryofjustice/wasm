<?php

namespace WpEcs\Wordpress;

use Aws\Sdk;
use WpEcs\Wordpress\AwsInstance\AwsResources;

class InstanceFactory
{
    /**
     * Create a WordPress instance object
     * Depending on the $identifier provided, this will either be an AwsInstance or a LocalInstance
     *
     * @param string $identifier
     *
     * @return AbstractInstance
     * @throws \Exception
     */
    public static function create($identifier)
    {
        if ($path = self::localIdentifier($identifier)) {
            // This is a local instance identifier
            return new LocalInstance($path);
        } elseif ($id = self::awsIdentifier($identifier)) {
            // This is an AWS instance identifier
            $sdk = new Sdk([
                'region'  => 'eu-west-2',
                'version' => 'latest',
            ]);
            $aws = new AwsResources($id['appName'], $id['env'], $sdk);
            return new AwsInstance($id['appName'], $id['env'], $aws);
        } else {
            // Could not recognise this a valid instance identifier
            $message = "Instance identifier \"$identifier\" is not valid\n";
            $message .= 'It must be in the format "<appname>:<env>" (e.g. "sitename:dev") for an AWS instance,' . "\n";
            $message .= 'or the path to a local instance directory (which must contain a docker-compose.yml file).';
            throw new \Exception($message);
        }
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
    protected static function awsIdentifier($identifier)
    {
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
     * If it is, return the canonical filesystem path to instance
     * ELse return false
     *
     * @param string $identifier
     *
     * @return string|bool
     */
    protected static function localIdentifier($identifier)
    {
        if (!is_dir($identifier)) {
            return false;
        }

        if (!file_exists("$identifier/docker-compose.yml") && !file_exists("$identifier/docker-compose.yaml")) {
            return false;
        }

        return $identifier;
    }
}
