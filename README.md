# WordPress AWS Site Manager

[![Build Status](https://travis-ci.org/ministryofjustice/wasm.svg?branch=master)](https://travis-ci.org/ministryofjustice/wasm) [![Maintainability](https://api.codeclimate.com/v1/badges/e0dd3ecfdd2258f11ff3/maintainability)](https://codeclimate.com/github/ministryofjustice/wasm/maintainability) [![Test Coverage](https://api.codeclimate.com/v1/badges/e0dd3ecfdd2258f11ff3/test_coverage)](https://codeclimate.com/github/ministryofjustice/wasm/test_coverage)

The WordPress AWS Site Manager ('WASM' for short) is an opinionated tool which can be used to manage WordPress instances running in the MOJ Digital / Tactical Products ECS hosting platform.

WordPress instances running in this environment are based on the [`mojdigital/wordpress-base`](https://hub.docker.com/r/mojdigital/wordpress-base/) image, with a codebase structured on [`ministryofjustice/wp-template`](https://github.com/ministryofjustice/wp-template).

## Features

- **Migrate** content from one WordPress instance to another
- **Export & import** database dumps from WordPress instances
- **Execute** commands on WordPress instances
- **Bash shell** on WordPress instances

## Installation

Ensure your machine is configured correctly to [meet the requirements](#requirements).

1. Add this GitHub repository as a package source for your global composer install:
   
   ```bash
   composer global config repositories.repo-name vcs https://github.com/ministryofjustice/wasm
   ```
   
2. Install `wasm` from the `master` branch:
   
   ```bash
   composer global require ministryofjustice/wasm:dev-master
   ```
   
3. You should now be able to run `wasm` from any directory and see the list of available commands.

**Note:** If you see an error `wasm: command not found`, it'll likely be because you don't have the composer bin directory in your PATH. Refer to the [composer requirements](#composer) section of this document.

## Usage

Use WASM on the command line:

```bash
wasm
```

When run without any additional arguments, a list of available commands will be shown.

Help for particular commands can be seen by passing the `--help` flag. For example: `wasm migrate --help` will output help documentation for the `migrate` command.

## Requirements

- PHP 7.1+
- [Composer](https://getcomposer.org/)
- [AWS Command Line Interface](https://aws.amazon.com/cli/)
- SSH client

### Composer

Composer should be accessible globally with the command `composer`.

Composer's global bin directory `~/.composer/vendor/bin/` should be added to your PATH so that binaries provided by installed packages can be run from your terminal. [Instructions available here.](https://akrabat.com/global-installation-of-php-tools-with-composer/)

### AWS Command Line Interface

The AWS CLI must be configured to use the correct AWS account and `eu-west-2` region by default.

If you'd prefer this not to be your default configuration, [environment variables can be set](https://docs.aws.amazon.com/cli/latest/userguide/cli-environment.html) prior to using this tool to ensure that the AWS CLI uses the correct profile / region.

When configured correctly, this command should list the ECS clusters used to host WordPress docker instances:

```bash
aws ecs list-clusters
```

### SSH client

The `ssh` command must be able to connect to the EC2 instances powering the ECS cluster without user interaction.

It may be necessary to configure your SSH client using `ssh -i`. This will tell it to use a specific private key when authenticating in subsequent connections.

When configured correctly, this command should connect automatically (without password prompts) to the SSH server:

```bash
ssh ec2-user@$EC2_INSTANCE_IP
```

Given that `$EC2_INSTANCE_IP` represents the hostname or IP address of an EC2 instance in the ECS cluster.

## Tests

* Unit tests are powered by PHPUnit
* Code quality tests are powered by PHP_CodeSniffer and PHP Mess Detector

### Continuous Integration

Tests are automatically run against Pull Requests in this repository:

* Travis CI runs PHPUnit tests
* CodeClimate monitors the code quality & test coverage of this repository (including PHP_CodeSniffer and PHP Mess Detector)

PRs cannot be merged unless they pass these tests.

### Local Development

Run tests against this repository locally with:

```bash
composer test
```

Or if you have the [CodeClimate CLI](https://docs.codeclimate.com/docs/command-line-interface) installed, run:

```bash
codeclimate analyze
```
