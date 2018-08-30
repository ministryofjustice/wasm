<?php

namespace WpEcs\Wordpress;

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
        if ($id = self::awsIdentifier($identifier)) {
            // This is an AWS instance identifier
            return new AwsInstance($id['appName'], $id['env']);
        } elseif ($path = self::localIdentifier($identifier)) {
            // This is a local instance identifier
            return new LocalInstance($path);
        } else {
            // Could not recognise this a valid instance identifier
            throw new \Exception("Invalid identifier \"$identifier\"");
        }
    }

    public static function validateIdentifier($identifier)
    {
        return (
            self::awsIdentifier($identifier) !== false ||
            self::localIdentifier($identifier) !== false
        );
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
        if (preg_match('/^([a-z-]+):(dev|staging|prod)$/', $identifier, $matches)) {
            return [
                'appName' => $matches[1],
                'env' => $matches[2],
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
        $path = realpath($identifier);

        if (!$path) {
            return false;
        }

        if (!is_dir($path)) {
            return false;
        }

        if (!file_exists("$path/docker-compose.yml") && !file_exists("$path/docker-compose.yaml")) {
            return false;
        }

        return $path;
    }
}
