# FAQ

1. [What is the canonical way to write a CLI entry file?](#what-is-the-canonical-way-to-write-a-cli-entry-file)
   1. [The shebang](#the-shebang)
   1. [The PHP_SAPI check](#the-php_sapi-check)
   1. [Autoloading Composer](#autoloading-composer)
2. [Detecting that you are inside a PHAR](#detecting-that-you-are-inside-a-phar)
3. [Building a PHAR with Box as a dependency](#building-a-phar-with-box-as-a-dependency)


## What is the canonical way to write a CLI entry file?

A conventional CLI entry file looks like this (see bellow for further explanations):

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Acme;

use function in_array;
use const PHP_EOL;
use const PHP_SAPI;
use RuntimeException;

if (false === in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    echo PHP_EOL.'This app may only be invoked from a command line, got "'.PHP_SAPI.'"'.PHP_EOL;

    exit(1);
}

(static function (): void {
    if (file_exists($autoload = __DIR__.'/../../../autoload.php')) {
        // Is installed via Composer
        include_once $autoload;

        return;
    }

    if (file_exists($autoload = __DIR__.'/../vendor/autoload.php')) {
        // Is installed locally
        include_once $autoload;

        return;
    }

    throw new RuntimeException('Unable to find the Composer autoloader.');
})();

// Execute the application

```

### The shebang

The shebang `#!/usr/bin/env php` is required to the auto-detection of the type of the script. This allows to use it as
follows:

```shell
chmod +x bin/acme.php
./bin/acme.php
php bin/acme.php # still works
# Without the shebang line, you can only use the latter
```

In other words it is not necessary, but a nice to have if you want to make your file executable.


### The PHP_SAPI check

For PHP, available SAPIs are: Apache2 (mod_php), FPM, CGI, FastCGI and CLI. There is a few other variants but those are
the most commons ones. For more information, see the [official PHP doc][php-sapi-name].

So the following:

```php
if (false === in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    echo PHP_EOL.'This app may only be invoked from a command line, got "'.PHP_SAPI.'"'.PHP_EOL;

    exit(1);
}
```

is purely ot make sure your CLI application is not executed in a non CLI context (for example via a web server). Doing
so prevents you to have to worry about web-server related vulnerabilities such as [HTTPoxy][httpoxy].

- `cli` is the standard default you will get
- `phpdbg` when executing PHP with [PHPDBG][phpdbg]
- `embed` if you compile the PHP/ZE into another program


### Autoloading Composer

When developing a CLI application, you generally only need to worry about your local autoloader:

```php
include_once __DIR__.'/../vendor/autoload.php';
```

However, if the application is also published as a Composer package, then the autoloader may be found in a different
location:

```php
include_once __DIR__.'/../../../autoload.php';
```

In either cases however, it could be the autoloader file is missing (e.g. if the dependencies are not installed yet).
So it is wise to wrap them in a `file_exist()` check and provide a user-friendly error when no autoloader could be
found.


## Detecting that you are inside a PHAR

The easiest way to know if your script is executed from within a PHAR is to run the following:

```php
$isInPhar = '' !== Phar::running(false);
```

See [Phar::running()][phar-running] for more information.


## Building a PHAR with Box as a dependency

If you need to include Box as part of your dependencies and include it within your PHAR, you will probably encounter
the following issue when building your PHAR:

```text
Could not dump the autoloader.
[...]
Could not scan for classes inside "/path/to/vendor/humbug/php-scoper/vendor-hotfix/" which does not appear to be a file nor a folder
```

This is because by default, Box does not include VCS or dot files which results in the directory `vendor/humbug/php-scoper/vendor-hotfix/`
to be excluded (as it becomes an empty directory). To circumvent that, you will likely need:

```json
{
   "directories": ["vendor/humbug/php-scoper/vendor-hotfix"]
}
```

Note that as a result you may want to use the [`force-autodiscovery`][force-autodiscovery] setting.


<br />
<hr />

« [Symfony supports](symfony.md#symfony-support) • [Table of Contents](/) »


[httpoxy]: https://httpoxy.org/
[force-autodiscovery]: ./configuration.md#force-auto-discovery-force-autodiscovery
[phar-running]: https://www.php.net/manual/en/phar.running.php
[phpdbg]: https://www.php.net/manual/en/intro.phpdbg.php
[php-sapi-name]: https://www.php.net/manual/en/function.php-sapi-name.php

