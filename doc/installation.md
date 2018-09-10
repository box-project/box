# Installation

1. [PHAR](#phar)
1. [Composer](#composer)

## PHAR

The preferred method of installation is to use the Box PHAR which can be downloaded from the most recent
[Github Release][releases]. This method ensures you will not have any dependency conflict issue.


### Composer

You can install Box with [Composer][composer]:

```bash
composer global require humbug/box
```

If you cannot install it because of a dependency conflict or you prefer to install it for your project, we recommend
you to take a look at [bamarni/composer-bin-plugin][bamarni/composer-bin-plugin]. Example:

```bash
composer require --dev bamarni/composer-bin-plugin
composer bin box require --dev humbug/box

$ vendor/bin/box
```


<br />
<hr />

« [Table of Contents](../README.md#table-of-contents) • [Configuration](configuration.md) »


[releases]: https://github.com/humbug/box/releases
[composer]: https://getcomposer.org
[bamarni/composer-bin-plugin]: https://github.com/bamarni/composer-bin-plugin
