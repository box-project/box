<p align="center">
    <img src="doc/img/box.png" width=900 />
</p>


[![Package version](https://img.shields.io/packagist/vpre/humbug/box.svg?style=flat-square)](https://packagist.org/packages/humbug/box)
[![Travis Build Status](https://img.shields.io/travis/humbug/box.svg?branch=master&style=flat-square)](https://travis-ci.org/humbug/box?branch=master)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/humbug/box.svg?branch=master&style=flat-square)](https://scrutinizer-ci.com/g/humbug/box/?branch=master)
[![Slack](https://img.shields.io/badge/slack-%23humbug-red.svg?style=flat-square)](https://symfony.com/slack-invite)
[![License](https://img.shields.io/badge/license-MIT-red.svg?style=flat-square)](LICENSE)

Fork of the unmaintained [box2 project][box2]. This project needs your help!

Upgrading from [Box2][box2]? Checkout the [upgrade guide](UPGRADE.md#from-27-to-30)!

## Goal

The Box application simplifies the PHAR building process. Out of the box (no pun intended), the application can do many
great things:

- ⚡  Fast application bundling
- 🔨 [PHAR isolation](doc/code-isolation.md#phar-code-isolation)
- ⚙️ Zero configuration by default
- 🚔 [Requirements checker](doc/requirement-checker.md#requirements-checker)
- 🚨 Friendly error logging experience 
- 🔍 Retrieve information about the PHAR extension or a PHAR file and its contents (`box info`)
- 🕵️‍♀️ Verify the signature of an existing PHAR (`box verify`)
- 📝 Use Git tags and short commit hashes for versioning


## Table of Contents

1. [Installation](doc/installation.md#installation)
    1. [PHAR](doc/installation.md#phar)
    1. [Composer](doc/installation.md#composer)
1. [Uage](#usage)
1. [Configuration](doc/configuration.md#configuration)
    1. [Base path](doc/configuration.md#base-path-base-path)
    1. [Main](doc/configuration.md#main-main)
    1. [Output](doc/configuration.md#output-output)
    1. [Permissions](doc/configuration.md#permissions-chmod)
    1. [Check requirements](doc/configuration.md#check-requirements-check-requirements)
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
    1. [Dumping the Composer autoloader (`dump-autoload`)](doc/configuration.md#dumping-the-composer-autoloader-dump-autoload)
    1. [Compactors (`compactors`)](doc/configuration.md#compactors-compactors)
    1. [Compression algorithm (`compression`)](doc/configuration.md#compression-algorithm-compression)
    1. [Signing algorithm (`algorithm`)](doc/configuration.md#signing-algorithm-algorithm)
1. [Requirements checker](doc/requirement-checker.md#requirements-checker)
    1. [Configuration](doc/requirement-checker.md#configuration)
        1. [PHP version requirements](doc/requirement-checker.md#php-version-requirements)
        1. [Extension configuration requirements](doc/requirement-checker.md#extension-configuration-requirements)
        1. [Polyfills](doc/requirement-checker.md#polyfills)
    1. [Integration with a custom stub](#integration-with-a-custom-stub)
1. [Optimize your PHAR](doc/optimizations.md#optimize-your-phar)
    1. [Review your files](doc/optimizations.md#review-your-files)
    1. [Compress your PHAR](doc/optimizations.md#compress-your-phar)
    1. [Optimize your code](doc/optimizations.md#optimize-your-code)
1. [PHAR code isolation](doc/code-isolation.md#phar-code-isolation)
    1. [Why/Explanation](doc/code-isolation.md#whyexplanation)
    1. [Isolating the PHAR](doc/code-isolation.md#isolating-the-phar)
    1. [Debugging the scoping](doc/code-isolation.md#debugging-the-scoping)
1. [Contributing](#contributing)
1. [Upgrade guide](UPGRADE.md#from-27-to-30)
1. [Backward Compatibility Promise (BCP)](#backward-compatibility-promise-bcp)
1. [Credits](#credits)


## Usage

Creating a PHAR should be as simple as running `box compile` (**no config required!**). It will however assume some
defaults that you might want to change. Box will by default be looking in order for the files `box.json` and
`box.json.dist` in the current working directory. A basic configuration could be for example changing the PHAR
permissions:

```json
{
    "chmod": "0755"
}
```

You can then find more advanced configuration settings in [the configuration documentation](doc/configuration.md).
For more information on which command or options is available, you can run:

```
box help
```


## Contributing

The project provides a `Makefile` in which the most common commands have been registered such as fixing the coding
style or running the test.

```bash
make
```


## Backward Compatibility Promise (BCP)

The policy is for the major part following the same as [Symfony's one][symfony-bc-policy]. Note that the code marked
as `@private` or `@internal` are excluded from the BCP. 


## Credits

Project originally created by: [Kevin Herrera] ([@kherge]) which has now been moved under the [Humbug umbrella][humbug].


[box2]: https://github.com/box-project/box2
[Kevin Herrera]: https://github.com/kherge
[@kherge]: https://github.com/kherge
[humbug]: https://github.com/humbug
