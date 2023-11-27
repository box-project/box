# Reproducible builds

1. [Creating a reproducible PHAR](#creating-a-reproducible-phar)
   1. [PHP-Scoper](#php-scoper)
   1. [Composer](#composer)
       1. [Composer root version](#composer-root-version)
       1. [Composer autoload suffix](#composer-autoload-suffix)
   1. [Box](#box)
       1. [PHAR alias](#phar-alias)
       1. [Requirement Checker](#requirement-checker)
       1. [Box banner](#box-banner)
       1. [Timestamp](#timestamp)
1. [Usages](#usages)


When building a PHAR, you sometimes want to have reproducible builds, i.e. no matter how many times you build the PHAR,
as long as the source content is identical, then the resulting PHAR should not change.

Whilst this sounds like a good idea and easy at first, it is not the default behaviour. Indeed there is a number of things
that are generated and will make the resulting PHAR different, for example the Composer autoloader classname, or the scoping
prefix if you are using PHP-Scoper.

This documentation aims at walking you through the common elements to adjust in order to achieve reproducible builds. This
is not an exhaustive piece of documentation as it will also depends on your own application too.


## Creating a reproducible PHAR

### PHP-Scoper

If you are using the [PHP-Scoper compactor][php-scoper-compactor], you will need to define a fixed prefix as otherwise a random
one is generated.

See the [PHP-Scoper prefix configuration doc][php-scoper-prefix-doc].


### Composer

#### Composer root version

By default, the git commit of the current version is included in some places in the Composer generated files. At the time
of writing, the current git reference can be found in `vendor/composer/installed.{json|php}` with the path `root.reference`.

This is not ideal as the content of the PHAR could be identical for two different git commits. In order to get rid of
this problem, you can leverage the [`COMPOSER_ROOT_VERSION`][composer-root-version]. Either by exporting it or passing
it to Box when compiling it:

```shell
COMPOSER_ROOT_VERSION=1.0.0-dev box compile
```

#### Composer autoload suffix

By default, Box will dump the Composer autoloader which usually results in a different autoloader classname. There is
exceptions to this, for example Composer tend to try to keep the known suffix if one already exist, but it is an exotic
case that is not recommended to rely on. For this reason you need to configure the [Composer autoload prefix][composer-autoload-prefix]:

```shell
composer config autoloader-suffix AppChecksum
```

Or configure it directly in your `composer.json`:

```json
{
    "config": {
        "autoloader-suffix": "AppChecksum"
    }
}
```


### Box

#### PHAR Alias

By default, Box generates a random PHAR alias so you need to set a fixed value, e.g. `my-app-name`.

See the [Box alias setting][box-alias].

The output (`string`|`null`) setting specifies the file name and path of the newly built PHAR. If the value of the
setting is not an absolute path, the path will be relative to the base path.

If not provided or set to `null`, the default value used will based on the [`main`][main]. For example if the main file
is `bin/acme.php` or `bin/acme` then the output will be `bin/acme.phar`.


#### Requirement Checker

By default, Box includes its [Requirement Checker][requirement-checker]. It will not change from a PHAR to another, so
this step should be skippable. However, the RequirementChecker shipped _does_ change based on the Box version. I.e.
building your PHAR with Box 4.3.8 will result in a different† requirement checker shipped than the one in 4.4.0.

†: By different is meant the checksum is different. The behaviour and code may be the exact same. The most likely
difference will be the namespace.

Note that this may change in the future: https://github.com/box-project/box/issues/1075.


#### Box banner

By default, Box generates a [banner][banner]. This banners includes the Box version so building the same PHAR with two
different Box versions will result in a different PHAR signature.


### Timestamp

The files unix timestamp are part of the PHAR signature, hence if they have a different timestamp (which they do as when
you add a PHAR to a file, it is changed to the time at when you added it).

To fix this, you can leverage configure the [timestamp].


## Usages

Deterministic builds are a highly desirable property to prevent targeted malware attacks. They also make it easier to
detect if there is any real change. As non-exhaustive examples in the wild: [Composer][composer] and [PHPStan][phpstan].

Another benefit of such builds is that it makes it easier to know if there was any change. You can know if two PHARs are
identical by using the `box diff` command, or extract the signature out of the `box info:signature` command:

```shell
box info:signature app.phar
```

And re-use that signature later for comparison. You will loose the ability to do a detailed diff between the two PHARs,
but it is enough to know if the PHARs are identical or not.


<br />
<hr />

« [Symfony support](symfony.md#symfony-support) • [PHAR signing best practicies](phar-signing.md#phar-signing-best-practices) »


[banner]: ./configuration.md#banner-banner
[box-alias]: ./configuration.md#alias-alias
[composer]: https://github.com/composer/composer
[composer-autoload-prefix]: https://getcomposer.org/doc/06-config.md#autoloader-suffix
[composer-root-version]: https://getcomposer.org/doc/03-cli.md#composer-root-version
[main]: configuration.md#main-main
[phpstan]: https://github.com/phpstan/phpstan
[php-scoper-compactor]: ./configuration.md#compactors-compactors
[php-scoper-prefix-doc]: https://github.com/humbug/php-scoper/blob/main/docs/configuration.md#prefix
[requirement-checker]: ./requirement-checker.md
[timestamp]: ./configuration.md#forcing-the-timestamp-timestamp
