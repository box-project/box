<p align="center">
    <img src="doc/img/box.png" width=900 />
</p>


[![Package version](https://img.shields.io/packagist/v/humbug/box.svg?style=flat-square)](https://packagist.org/packages/humbug/box)
[![Build](https://github.com/box-project/box/actions/workflows/build.yml/badge.svg)](https://github.com/box-project/box/actions/workflows/build.yml)
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
- 🔍 Retrieve information about the PHAR extension or a PHAR file and its contents (`box info` or `box diff`)
- 🔐️ Verify the signature of an existing PHAR (`box verify`)
- 📝 Use Git tags and short commit hashes for versioning
- 🕵️️ Get recommendations and warnings about regarding your configuration (`box validate`)
- 🐳 [Docker support (`box docker`)](doc/docker.md#docker-support)


## Documentation

For the full documentation see https://box-project.github.io/box

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
