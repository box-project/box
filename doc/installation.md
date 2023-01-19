# Installation

1. [PHAR](#phar)
1. [Phive](#phive)
1. [Composer](#composer)
1. [Homebrew](#homebrew)


## PHAR

The preferred method of installation is to use the Box PHAR which can be downloaded from the most recent
[Github Release][releases]. This method ensures you will not have any dependency conflict issue.


## Phive

You can install Box with [Phive][phive]

```bash
$ phive install humbug/box
```

To upgrade `box` use the following command:

```bash
$ phive update humbug/box
```


## Composer

You can install Box with [Composer][composer]:

```bash
$ composer global require humbug/box
```

If you cannot install it because of a dependency conflict or you prefer to install it for your project, we recommend
you to take a look at [bamarni/composer-bin-plugin][bamarni/composer-bin-plugin]. Example:

```bash
$ composer require --dev bamarni/composer-bin-plugin
$ composer bin box require --dev humbug/box

$ vendor/bin/box
```

## Homebrew

To install box using [Homebrew](https://brew.sh), you need to tap the box formula first

```bash
$ brew tap box-project/box
$ brew install box
```

The `box` command is now available to run from anywhere in the system:

```bash
$ box -v
```

To upgrade `box` use the following command:

```bash
$ brew upgrade box
```

<br />
<hr />

« [Table of Contents](/) • [Usage](usage.md) »


[releases]: https://github.com/humbug/box/releases
[composer]: https://getcomposer.org
[bamarni/composer-bin-plugin]: https://github.com/bamarni/composer-bin-plugin
[phive]: https://github.com/phar-io/phive
