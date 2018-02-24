<p align="center">
    <img src="doc/img/box.png" width=900 />
</p>


[![Package version](https://img.shields.io/packagist/vpre/humbug/box.svg?style=flat-square)](https://packagist.org/packages/humbug/box)
[![Travis Build Status](https://img.shields.io/travis/humbug/box.svg?branch=master&style=flat-square)](https://travis-ci.org/humbug/box?branch=master)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/humbug/box.svg?branch=master&style=flat-square)](https://scrutinizer-ci.com/g/humbug/box/?branch=master)
[![Slack](https://img.shields.io/badge/slack-%23humbug-red.svg?style=flat-square)](https://symfony.com/slack-invite)
[![License](https://img.shields.io/badge/license-MIT-red.svg?style=flat-square)](LICENSE)

Fork of the unmaintained [box2 project](https://github.com/box-project/box2). This project needs your help!

Roadmap:
  - [Project board](https://github.com/humbug/box/projects/1)
  - [Medium article](https://medium.com/@tfidry/phars-roadmap-870671a847c1)


## Goal

The Box application simplifies the PHAR building process. Out of the box (no pun intended), the application can do many
great things:

- Add, replace, and remove files and stubs in existing PHARs
- Retrieve information about the PHAR extension or a PHAR file
- List the contents of a PHAR
- Verify the signature of an existing PHAR
- Generate RSA (PKCS#1 encoded) private keys for OpenSSL signing
- Extract public keys from existing RSA private keys
- Use Git tags and short commit hashes for versioning.


## Table of Contents

1. [Installation](#installation)
    1. [PHAR (preferred but NOT SUPPORTED YET)](#phar-preferred-but-not-supported-yet)
    1. [Composer](#composer)
1. [Creating a PHAR](#creating-a-phar)
1. [Configuration](doc/configuration.md)
    1. [Base path](doc/configuration.md#base-path-base-path)
    1. [Output](doc/configuration.md#output-output)
    1. [Including files](doc/configuration.md#including-files)
        1. [Files (`files` and `files-bin`)](doc/configuration.md#files-files-and-files-bin)
        1. [Directories (`directories` and `directories-bin`)](doc/configuration.md#directories-directories-and-directories-bin)
        1. [Finder (`finder` and `finder-bin`)](doc/configuration.md#finder-finder-and-finder-bin)
        1. [Blacklist (`blacklist`)](doc/configuration.md#blacklist-blacklist)
    1. [Stub](doc/configuration.md#stub)
        1. [Stub (`stub`)](doc/configuration.md#stub-stub)
        1. [Alias (`alias`)](doc/configuration.md#alias-alias)
        1. [Shebang (`shebang`)](doc/configuration.md#shebang-shebang)
        1. [Banner (`banner`)](doc/configuration.md#banner-banner)
        1. [Banner file (`banner-file`)](doc/configuration.md#banner-file-banner-file)
1. [Contributing](#contributing)
1. [Upgrade](#upgrade)
1. [Credits](#credits)


## Installation

### PHAR (preferred but NOT SUPPORTED YET)

The preferred method of installation is to use the Box PHAR, which can
be downloaded from the most recent [Github Release][releases]. Subsequent updates
can be downloaded by running:

```bash
box.phar self-update
```

As the PHAR is signed, you should also download the matching
`box.phar.pubkey` to the same location. If you rename `box.phar`
to `box`, you should also rename `box.phar.pubkey` to `box.pubkey`.


### Composer

You can install Box with Composer:

```bash
composer global require humbug/box:^3.0@dev
```

If you cannot install it because of a dependency conflict or you prefer to
install it for your project, we recommend you to take a look at
[bamarni/composer-bin-plugin][bamarni/composer-bin-plugin]. Example:

```bash
composer require --dev bamarni/composer-bin-plugin
composer bin box require --dev humbug/box:^3.0@dev
```

Keep in mind however that this library is not designed to be extended.


## Creating a Phar

To get started, you may want to check out the [example application](https://github.com/kherge/php-box-example) that is
ready to be built by Box. How your project is structured is entirely up to you. All that Box requires is that you have
a file called `box.json` at the root of your project directory. You can find a complete and detailed list of
configuration settings available by seeing the help information for the `build` command:

```sh
$ box help build
```

Once you have configured your project using `box.json` (or `box.json.dist`), you can simply run the `build`
command in the directory containing `box.json`:

```sh
$ box build -v
```

> The `-v` option enabled verbose output. This will provide you with a lot of useful information for debugging your build process. Once you are satisfied with the results, I recommend not using the verbose option. It may considerably slow down the build process.


## Contributing

The project provides a `Makefile` in which the most common commands have been
registered such as fixing the coding style or running the test.

```bash
make
# or
make help
# => will print the list of available commands
```

## Upgrade

Check the [upgrade guide](UPGRADE.md).


## Credits

Project originally created by: [Kevin Herrera] ([@kherge]) which hasnow been moved under the [Humbug umbrella][humbug].



[releases]: https://github.com/humbug/box/releases
[bamarni/composer-bin-plugin]: https://github.com/bamarni/composer-bin-plugin
[Kevin Herrera]: https://github.com/kherge
[@kherge]: https://github.com/kherge
[humbug]: https://github.com/humbug
