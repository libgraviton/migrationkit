# migrationkit

[![Build Status](https://travis-ci.org/libgraviton/migrationkit.png?branch=develop)](https://travis-ci.org/libgraviton/migrationkit) [![Latest Stable Version](https://poser.pugx.org/graviton/migrationkit/v/stable.svg)](https://packagist.org/packages/graviton/migrationkit) [![Docker Pulls](https://img.shields.io/docker/pulls/graviton/migrationkit.svg)](https://hub.docker.com/r/graviton/migrationkit/) [![Docker Automated](https://img.shields.io/docker/automated/graviton/migrationkit.svg)](https://hub.docker.com/r/graviton/migrationkit/) [![Docker Build](https://img.shields.io/docker/build/graviton/migrationkit.svg)](https://hub.docker.com/r/graviton/migrationkit/)

This is a Symfony Console application that provides utilities in regard to generation and migration of
[Graviton](https://github.com/libgraviton/graviton) based services and service definitions.

When we generate migrations, we generate classes that can be used with `doesntmattr/mongodb-migrations`.
## Current state

This is a fairly new project and generation migrations is no simple matter.

`migrationkit` has been designed in an extendable way, so we can add new migration scenarios rather easily.

Migration need | Done?
------------ | -------------
Rename of a property on an existing entity | :white_check_mark:
Filling of newly required fields | :no_entry_sign: 
Moving of entities | :no_entry_sign:
More complex stuff.. what? | :no_entry_sign:

## Usage

The recommended way to use this is by using our docker image. It is available on Docker Hub, so you
can use it right away.

### Docker

You can execute the tool quite easily:

```bash
docker run --rm graviton/migrationkit
```

You should see the help screen with the list of commands.

To execute a given command, just add it to the `run`:

```bash
docker run --rm graviton/migrationkit graviton:migrations:generate --help
```

Feel free to wrap this with Docker Compose.

#### Solving user permissions problems

Our Docker Image comes with some support for solving user permission problems as it shall generate
resources on your local disk.

The image has the environment variables `PUID` and `PGID` that you should set to the UID and GID of
your `local user` that owns the target directories.

You can locate the UID and GID by issuing the `id` command:

```bash
user@host:~$ id
uid=1000(user) gid=1000(user) groups=1000(user),...
```

Once you know that, set those on the `run`:

```bash
docker run --rm -e PUID=1000 -e PGID=1000 graviton/migrationkit graviton:migrations:generate --help
```

This should resolve all permission problems.

### Composer

If you really want to use this in a project context, you can use composer:

```bash
composer require --dev graviton/migrationkit
``` 
 
This really *should* be a `dev` dependency for your project.

## Commands

### graviton:version-migrations:generate

> Generate migrations from your current branch to another branch and generate migrations

Assumes that the directory passed as `baseDir` is a git repository. It then copies
that directory, switches to the tag/branch defined by argument `branch` and
calls the command `graviton:migrations:generate` with those two directory,
thus allowing you to generate differences between two (possibly unpushed) branches.

One would pull `develop`, create a feature branch, make his changes and then
call this command with `develop` as a compare branch.
  
### graviton:fixture-entity:generate

> Generate random JSON payload entities according to a service definition.

This is a helper command for general work on migrations. It takes a service
definition and generates `number` (argument) files (default 10) of random fake
JSON payloads that can be `PUT`ed/`POST`ed to a Graviton instance.

They conform to the specified service definition and include all specified
fields. 

Please note that these fake data payloads may make no sense to an application that
wants to understand/do business stuff out of the structures. 

#### The `refMap` file

In order to generate valid `extref` fields, you may have to specify an `refMap` file
(option `--refMap` to the command).

This is a simple YAML file that maps the `Collection` attribute in the field to an URL.

Example:
```yaml
App: /core/app/
MyOtherEntity: /other/entity/
```

### graviton:definition-metadata:generate

> Generates YML metadata files from service definitions

Internally, `migrationkit` uses simple YAML files to do stuff. This command helps
to generate those files. You need those files for example for the 
`graviton:fixture-entity:generate` command.
 
### graviton:from-ios-schema:generate   

> Generate service definitions from the _iOS_ Schema

This fills a specific need for people that want to generate Graviton compatible service
definitions from a proprietary _iOS_ Schema format.

### graviton:migrations:generate

> Lower level migration command that allows you to specify two directories and migrate between them.

Allows you to specify two directories and generate migrations from them.
It will prompt you for possible conflicts and you may need to provide input to 
resolve them.
