# Installation

1. [Phive](#phive)
1. [Composer](#composer)
1. [Homebrew](#homebrew)
1. [GitHub](#github)
1. [Docker](#docker)
1. [shivammathur/setup-php (GitHub Actions)](#shivammathursetup-php-github-actions)


## Phive

You can install Box with [Phive][phive]

```shell
phive install humbug/box
```

To upgrade `box` use the following command:

```shell
phive update humbug/box
```


## Composer

You can install Box with [Composer][composer]:

```shell
composer global require humbug/box
```

If you cannot install it because of a dependency conflict or you prefer to install it for your project, we recommend
you to take a look at [bamarni/composer-bin-plugin][bamarni/composer-bin-plugin]. Example:

```shell
composer require --dev bamarni/composer-bin-plugin
composer bin box require --dev humbug/box

vendor/bin/box
```

## Homebrew

To install box using [Homebrew](https://brew.sh), you need to tap the box formula first

```shell
brew tap box-project/box
brew install box
```

The `box` command is now available to run from anywhere in the system:

```shell
box -v
```

To upgrade `box` use the following command:

```shell
brew upgrade box
```

## GitHub

You may download the Box PHAR directly from the [GitHub release][releases] directly.
You should however beware that it is not as secure as downloading it from the other mediums.
Hence, it is recommended to check the signature when doing so:

```shell
# Do adjust the URL if you need a release other than the latest
wget -O box.phar "https://github.com/box-project/box/releases/latest/download/box.phar"
wget -O box.phar.asc "https://github.com/box-project/box/releases/latest/download/box.phar.asc"

# Check that the signature matches
gpg --verify box.phar.asc box.phar

# Check the issuer (the ID can also be found from the previous command)
gpg --keyserver hkps://keys.openpgp.org --recv-keys 41539BBD4020945DB378F98B2DF45277AEF09A2F

rm box.phar.asc
chmod +x box.phar
```

## Docker

The official docker image for the project is [`boxproject/box`][docker-image]:

```shell
docker pull boxproject/box
```

## `shivammathur/setup-php` (GitHub Actions)

Box is supported as a [shivammathur/setup-php tool]:

```yaml
- name: Setup PHP with tools
  uses: shivammathur/setup-php@v2
  with:
      php-version: '8.3'
      tools: box
```


<br />
<hr />

« [Table of Contents](/) • [Usage](usage.md) »


[releases]: https://github.com/humbug/box/releases
[composer]: https://getcomposer.org
[docker-image]: https://hub.docker.com/r/boxproject/box
[bamarni/composer-bin-plugin]: https://github.com/bamarni/composer-bin-plugin
[phive]: https://github.com/phar-io/phive
[shivammathur/setup-php tool]: https://github.com/shivammathur/setup-php?tab=readme-ov-file#wrench-tools-support
