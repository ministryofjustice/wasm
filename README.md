# WordPress AWS Site Manager

The WordPress AWS Site Manager ('WASM' for short) is an opinionated tool which can be used to manage WordPress instances running in the MOJ Digital / Tactical Products ECS hosting platform.

WordPress instances running in this environment are based on the [`mojdigital/wordpress-base`](https://hub.docker.com/r/mojdigital/wordpress-base/) image, with a codebase structured on [`ministryofjustice/wp-template`](https://github.com/ministryofjustice/wp-template).

## Features

- **Migrate** content from one WordPress instance to another
- **Export & import** database dumps from WordPress instances
- **Execute** commands on WordPress instances
- **Bash shell** on WordPress instances

## Usage

Use WASM on the command line:

```bash
wasm
```

When called without any additional arguments, `wasm` will output a list of available commands and documentation on how to use them.

## Requirements

- PHP 7.1+
- [Composer](https://getcomposer.org/)
- [AWS Command Line Interface](https://aws.amazon.com/cli/)
- SSH client

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
