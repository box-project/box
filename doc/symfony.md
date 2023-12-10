# Symfony support

1. [Project files](#project-files)
1. [Project directory](#project-directory)
2. [Cache](#cache)

Examples of Symfony applications can be found in the project under `fixtures/build/dir012` (Symfony5) and
`fixtures/build/dir018` (Symfony6 with the Runtime component).

They may slightly vary to what you want to do since they are here for testing purposes, but they should be good enough
to show-case a working scenario if this doc does not prove to be enough.


## Project files

When working with a Symfony project, you usually have a few files that are necessary for the application to work but
cannot be inferred from Composer. A non-exhaustive list: `.env`, `public`, `config`, `var`.

As a result, you will likely need to add them via `directories`, `files` or their `*-bin` variant which may force you
to use the `force-autodiscovery` setting. For more information you can find check the [Including files](./configuration.md#including-files)
doc.


## Project directory

Symfony 5.1+ defines the "project dir" as the directory where the composer.json file is. Because box deletes it during PHAR compilation, you need to redefine it in your Kernel. It is usually located in `src/Kernel.php` and can be defined as follow:

```php
<?php

class Kernel extends BaseKernel
{
...
    public function getProjectDir()
    {
        return __DIR__.'/../';
    }
}
```

## Cache

What makes Symfony a bit special for shipping it into a PHAR is its compilation step. Indeed, the Symfony container can
be dumped depending on multiple parameters such the application environment, whether it is in debug mode or not and if
the cache is fresh.

A PHAR however is a readonly only environment, which means the container _cannot_ be dumped once inside the PHAR. To
prevent the issue, you need to make sure of the following:

- The cache is warmed up before being shipped within the PHAR
- The application within the PHAR is running in production mode

To achieve this with the least amount of changes is to:

- Create the `.env.local.php` file by running the following command:

```shell
composer dump-env prod
```

This will ensure when loading the variables that your application is in production mode.

- Change the following part of the `composer.json` file:

```json
"scripts": {
    "auto-scripts": {
        "cache:clear": "symfony-cmd",
        "assets:install %PUBLIC_DIR%": "symfony-cmd"
    },
    "post-install-cmd": [
        "@auto-scripts"
    ],
    "post-update-cmd": [
        "@auto-scripts"
    ]
},
```

For:

```json
"scripts": {
    "auto-scripts": {
        "cache:clear": "symfony-cmd"
    },
    "post-autoload-dump": [
        "@auto-scripts"
    ]
},
```

I.e.:

- You skip the installation of assets (which you shouldn't need in the context of a CLI application)
- Trigger the auto-scripts, which includes the cache warming phase, on the Composer dump-autoload event

This last part takes advantage of Box [dumping the autoloader][composer-autoloader-dump] by default.


<br />
<hr />

« [Docker support](docker.md#docker-support) • [Reproducible build](reproducible-builds.md#reproducible-builds) »


[composer-autoloader-dump]: configuration.md#dumping-the-composer-autoloader-dump-autoload
[symfony-runtime]: https://symfony.com/doc/current/components/runtime.html
