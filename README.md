<p align="center">
    <img src="doc/img/box.png" width=900 alt="Box logo" />
</p>


[![Package version](https://img.shields.io/packagist/v/humbug/box.svg?style=flat-square)](https://packagist.org/packages/humbug/box)
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
    1. [Phive](doc/installation.md#phive)
    1. [Composer](doc/installation.md#composer)
    1. [Homebrew](doc/installation.md#homebrew)
    1. [GitHub](doc/installation.md#github)
    1. [Docker](doc/installation.md#docker)
    1. [shivammathur/setup-php (GitHub Actions)](doc/installation.md#shivammathursetup-php-github-actions)
1. [Usage](#usage)
1. [Configuration][the configuration documentation]
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
    1. [Forcing the timestamp (`timestamp`)](doc/configuration.md#forcing-the-timestamp-timestamp)
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
   1. [Project files](doc/symfony.md#project-files)
   1. [Project directory](doc/symfony.md#project-directory)
   2. [Cache](doc/symfony.md#cache)
1. [Reproducible builds](doc/reproducible-builds.md#reproducible-builds)
   1. [Creating a reproducible PHAR](doc/reproducible-builds.md#creating-a-reproducible-phar)
      1. [PHP-Scoper](doc/reproducible-builds.md#php-scoper)
      1. [Composer](doc/reproducible-builds.md#composer)
          1. [Composer root version](doc/reproducible-builds.md#composer-root-version)
          1. [Composer autoload suffix](doc/reproducible-builds.md#composer-autoload-suffix)
      1. [Box](doc/reproducible-builds.md#box)
          1. [PHAR alias](doc/reproducible-builds.md#phar-alias)
          1. [Requirement Checker](doc/reproducible-builds.md#requirement-checker)
          1. [Box banner](doc/reproducible-builds.md#box-banner)
      1. [PHAR](doc/reproducible-builds.md#phar)
   1. [Usages](doc/reproducible-builds.md#usages)
1. [PHAR signing best practices](doc/phar-signing.md#phar-signing-best-practices)
   1. [Built-in PHAR API](doc/phar-signing.md#built-in-phar-api)
       1. [How to sign your PHAR](doc/phar-signing.md#how-to-sign-your-phar)
       1. [How it works](doc/phar-signing.md#how-it-works)
       1. [Why it is bad](doc/phar-signing.md#why-it-is-bad)
   1. [How to (properly) sign your PHAR](doc/phar-signing.md#how-to-properly-sign-your-phar)
       1. [Create a new GPG-key](doc/phar-signing.md#create-a-new-gpg-key)
       1. [Manually signing](doc/phar-signing.md#manually-signing)
       1. [Generate the encryption key](doc/phar-signing.md#generate-the-encryption-key)
       1. [Secure your encryption key](doc/phar-signing.md#secure-your-encryption-key)
       1. [Sign your PHAR](doc/phar-signing.md#sign-your-phar)
       1. [Verifying the PHAR signature](doc/phar-signing.md#verifying-the-phar-signature)
   1. [Automatically sign in GitHub Actions](doc/phar-signing.md#automatically-sign-in-github-actions)
1. [FAQ](doc/faq.md#faq)
   1. [What is the canonical way to write a CLI entry file?](doc/faq.md#what-is-the-canonical-way-to-write-a-cli-entry-file)
       1. [The shebang](doc/faq.md#the-shebang)
       1. [The PHP_SAPI check](doc/faq.md#the-php_sapi-check)
       1. [Autoloading Composer](doc/faq.md#autoloading-composer)
   2. [Detecting that you are inside a PHAR](doc/faq.md#detecting-that-you-are-inside-a-phar)
   3. [Building a PHAR with Box as a dependency](doc/faq.md#building-a-phar-with-box-as-a-dependency)
1. [Contributing](#contributing)
1. [Upgrade guide](UPGRADE.md#upgrade-guide)
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

```shell
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
[configuration]: doc/configuration.md#configuration
[Kevin Herrera]: https://github.com/kherge
[@kherge]: https://github.com/kherge
[humbug]: https://github.com/humbug
[symfony-bc-policy]: https://symfony.com/doc/current/contributing/code/bc.html
[the configuration documentation]: doc/configuration.md#configuration
