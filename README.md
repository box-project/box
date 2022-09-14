<p align="center">
    <img src="doc/img/box.png" width=900 />
</p>


[![Package version](https://img.shields.io/packagist/v/humbug/box.svg?style=flat-square)](https://packagist.org/packages/humbug/box)
[![Build](https://github.com/box-project/box/actions/workflows/build.yml/badge.svg)](https://github.com/box-project/box/actions/workflows/build.yml)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/humbug/box.svg?branch=master&style=flat-square)](https://scrutinizer-ci.com/g/humbug/box/?branch=master)
[![Slack](https://img.shields.io/badge/slack-%23humbug-red.svg?style=flat-square)](https://symfony.com/slack-invite)
[![License](https://img.shields.io/badge/license-MIT-red.svg?style=flat-square)](LICENSE)

Upgrading from [Box2][box2]? Checkout the [upgrade guide](UPGRADE.md#from-27-to-30)!

## Goal

The Box application simplifies the PHAR building process. Out of the box (no pun intended), the application can do many
great things:

- ⚡  Fast application bundling
- 🔨 [PHAR isolation](doc/code-isolation.md#phar-code-isolation)
- ⚙️ Zero configuration by default
- 🚔 [Requirements checker](doc/requirement-checker.md#requirements-checker)
- 🚨 Friendly error logging experience 
- 🔍 Retrieve information about the PHAR extension or a PHAR file and its contents (`box info` or `box diff`)
- 🔐️ Verify the signature of an existing PHAR (`box verify`)
- 📝 Use Git tags and short commit hashes for versioning
- 🕵️️ Get recommendations and warnings about regarding your configuration (`box validate`)
- 🐳 [Docker support (`box docker`)](doc/docker.md#docker-support)

For the full documentation see https://box-project.github.io/box.


## Table of Contents

1. [Installation](doc/installation.md#installation)
    1. [PHAR](doc/installation.md#phar)
    1. [Phive](doc/installation.md#phive)
    1. [Composer](doc/installation.md#composer)
    1. [Homebrew](doc/installation.md#homebrew)
1. [Usage](#usage)
1. [Configuration](doc/configuration.md#configuration)
    1. [Base path (`base-path`)](doc/configuration.md#base-path-base-path)
    1. [Main (`main`)](doc/configuration.md#main-main)
    1. [Output (`output`)](doc/configuration.md#output-output)
    1. [Permissions (`chmod`)](doc/configuration.md#permissions-chmod)
    1. [Check requirements (`check-requirements`)](doc/configuration.md#check-requirements-check-requirements)
    1. [Including files](doc/configuration.md#including-files)
        1. [Force auto-discovery (`force-autodiscovery`)](doc/configuration.md#force-auto-discovery-force-autodiscovery)
        1. [Files (`files` and `files-bin`)](doc/configuration.md#files-files-and-files-bin)
        1. [Directories (`directories` and `directories-bin`)](doc/configuration.md#directories-directories-and-directories-bin)
        1. [Finder (`finder` and `finder-bin`)](doc/configuration.md#finder-finder-and-finder-bin)
        1. [Blacklist (`blacklist`)](doc/configuration.md#blacklist-blacklist)
        1. [Excluding the Composer files (`exclude-composer-files`)](doc/configuration.md#excluding-the-composer-files-exclude-composer-files)
        1. [Excluding dev files (`exclude-dev-files`)](doc/configuration.md#excluding-dev-files-exclude-dev-files)
        1. [Map (`map`)](doc/configuration.md#map-map)
    1. [Stub](doc/configuration.md#stub)
        1. [Stub (`stub`)](doc/configuration.md#stub-stub)
        1. [Alias (`alias`)](doc/configuration.md#alias-alias)
        1. [Shebang (`shebang`)](doc/configuration.md#shebang-shebang)
        1. [Banner (`banner`)](doc/configuration.md#banner-banner)
        1. [Banner file (`banner-file`)](doc/configuration.md#banner-file-banner-file)
    1. [Dumping the Composer autoloader (`dump-autoload`)](doc/configuration.md#dumping-the-composer-autoloader-dump-autoload)
    1. [Compactors (`compactors`)](doc/configuration.md#compactors-compactors)
        1. [Annotations (`annotations`)](doc/configuration.md#annotations-annotations)
        1. [PHP-Scoper (`php-scoper`)](doc/configuration.md#php-scoper-php-scoper)
    1. [Compression algorithm (`compression`)](doc/configuration.md#compression-algorithm-compression)
    1. [Security](doc/configuration.md#security)
        1. [Signing algorithm (`algorithm`)](doc/configuration.md#signing-algorithm-algorithm)
        1. [The private key (`key`)](doc/configuration.md#the-private-key-key)
        1. [The private key password (`key-pass`)](doc/configuration.md#the-private-key-password-key-pass)
    1. [Metadata (`metadata`)](doc/configuration.md#metadata-metadata)
    1. [Replaceable placeholders](doc/configuration.md#replaceable-placeholders)
        1. [Replacements (`replacements`)](doc/configuration.md#replacements-replacements)
        1. [Replacement sigil (`replacement-sigil`)](doc/configuration.md#replacement-sigil-replacement-sigil)
        1. [Datetime placeholder (`datetime`)](doc/configuration.md#datetime-placeholder-datetime)
        1. [Datetime placeholder format (`datetime-format`)](doc/configuration.md#datetime-placeholder-format-datetime-format)
        1. [Pretty git commit placeholder (`git`)](doc/configuration.md#pretty-git-tag-placeholder-git)
        1. [Git commit placeholder (`git-commit`)](doc/configuration.md#git-commit-placeholder-git-commit)
        1. [Short git commit placeholder (`git-commit-short`)](doc/configuration.md#short-git-commit-placeholder-git-commit-short)
        1. [Git tag placeholder (`git-tag`)](doc/configuration.md#git-tag-placeholder-git-tag)
        1. [Git version placeholder (`git-version`)](doc/configuration.md#git-version-placeholder-git-version)
1. [Requirements checker](doc/requirement-checker.md#requirements-checker)
    1. [Configuration](doc/requirement-checker.md#configuration)
        1. [PHP version requirements](doc/requirement-checker.md#php-version-requirements)
        1. [Extension configuration requirements](doc/requirement-checker.md#extension-configuration-requirements)
        1. [Polyfills](doc/requirement-checker.md#polyfills)
    1. [Integration with a custom stub](doc/requirement-checker.md#integration-with-a-custom-stub)
1. [Optimize your PHAR](doc/optimizations.md#optimize-your-phar)
    1. [Review your files](doc/optimizations.md#review-your-files)
    1. [Compress your PHAR](doc/optimizations.md#compress-your-phar)
    1. [Optimize your code](doc/optimizations.md#optimize-your-code)
1. [PHAR code isolation](doc/code-isolation.md#phar-code-isolation)
    1. [Why/Explanation](doc/code-isolation.md#whyexplanation)
    1. [Isolating the PHAR](doc/code-isolation.md#isolating-the-phar)
    1. [Debugging the scoping](doc/code-isolation.md#debugging-the-scoping)
1. [Docker support](doc/docker.md#docker-support)
1. [Symfony support](doc/symfony.md#symfony-support)
1. [FAQ](doc/faq.md#faq)
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
    "chmod": "0700"
}
```

You can then find more advanced configuration settings in [the configuration documentation][configuration].
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

The text displayed by the commands (e.g. `compile` or `info`) or the content of the error/exception messages are also not subject to the BCP.


## Credits

Project originally created by: [Kevin Herrera] ([@kherge]) which has now been moved under the [Humbug umbrella][humbug].


[box2]: https://github.com/box-project/box2
[Kevin Herrera]: https://github.com/kherge
[@kherge]: https://github.com/kherge
[humbug]: https://github.com/humbug
[symfony-bc-policy]: https://symfony.com/doc/current/contributing/code/bc.html
