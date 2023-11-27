# Configuration

1. [Base path][base-path]
1. [Main][main]
1. [Output][output]
1. [Permissions][permissions]
1. [Check requirements][check-requirements]
1. [Including files][including-files]
    1. [Force auto-discovery (`force-autodiscovery`)][force-autodiscovery]
    1. [Files (`files` and `files-bin`)][files]
    1. [Directories (`directories` and `directories-bin`)][directories]
    1. [Finder (`finder` and `finder-bin`)][finder]
    1. [Blacklist (`blacklist`)][blacklist]
    1. [Excluding the Composer files (`exclude-composer-files`)][exclude-composer-files]
    1. [Excluding dev files (`exclude-dev-files`)][exclude-dev-files]
    1. [Map (`map`)][map]
1. [Stub][stub]
    1. [Stub (`stub`)][stub-stub]
    1. [Alias (`alias`)][alias]
    1. [Shebang (`shebang`)][shebang]
    1. [Banner (`banner`)][banner]
    1. [Banner file (`banner-file`)][banner-file]
1. [Forcing the timestamp (`timestamp`)][timestamp]
1. [Dumping the Composer autoloader (`dump-autoload`)][dump-autoload]
1. [Compactors (`compactors`)][compactors]
    1. [Annotations (`annotations`)][annotations-compactor]
    1. [PHP-Scoper (`php-scoper`)][php-scoper-compactor]
1. [Compression algorithm (`compression`)][compression]
1. [Security][security]
    1. [Signing algorithm (`algorithm`)][algorithm]
    1. [The private key (`key`)][key]
    1. [The private key password (`key-pass`)][key-pass]
1. [Metadata (`metadata`)][metadata]
1. [Replaceable placeholders][placeholders]
    1. [Replacements (`replacements`)][replacements]
    1. [Replacement sigil (`replacement-sigil`)][replacement-sigil]
    1. [Datetime placeholder (`datetime`)][datetime–placeholder]
    1. [Datetime placeholder format (`datetime-format`)][datetime-placeholder-format]
    1. [Pretty git commit placeholder (`git`)][git]
    1. [Git commit placeholder (`git-commit`)][git-commit-placeholder]
    1. [Short git commit placeholder (`git-commit-short`)][git-commit-short]
    1. [Git tag placeholder (`git-tag`)][git-tag-placeholder]
    1. [Git version placeholder (`git-version`)][git-version-placeholder]


The build command will build a new PHAR based on a variety of settings.

This command relies on a configuration file for loading PHAR packaging settings. If a configuration file is not
specified through the `--config|-c option`, one of the following files will be used (in order): `box.json`,
`box.json.dist`. If no configuration file is found, Box will proceed with the default settings.

The configuration file is a JSON object saved to a file. Note that **all settings are optional**. If a setting is set
to `null`, then its default value will be picked and is strictly equivalent to not setting the value.

```json
{
    "algorithm": "?",
    "alias": "?",
    "annotations": "?",
    "banner": "?",
    "banner-file": "?",
    "base-path": "?",
    "blacklist": "?",
    "check-requirements": "?",
    "chmod": "?",
    "compactors": "?",
    "compression": "?",
    "datetime": "?",
    "datetime-format": "?",
    "directories": "?",
    "directories-bin": "?",
    "dump-autoload": "?",
    "exclude-composer-files": "?",
    "exclude-dev-files": "?",
    "files": "?",
    "files-bin": "?",
    "finder": "?",
    "finder-bin": "?",
    "force-autodiscovery": "?",
    "git": "?",
    "git-commit": "?",
    "git-commit-short": "?",
    "git-tag": "?",
    "git-version": "?",
    "intercept": "?",
    "key": "?",
    "key-pass": "?",
    "main": "?",
    "map": "?",
    "metadata": "?",
    "output": "?",
    "php-scoper": "?",
    "replacement-sigil": "?",
    "replacements": "?",
    "shebang": "?",
    "stub": "?",
    "timestamp": "?"
}
```


## Base-path (`base-path`)

The `base-path` (`string`|`null`) setting is used to specify where all of the relative file paths should resolve to.
This does not, however, alter where the built PHAR will be stored (see: `output`).

If set to `null` or not specified, the base path used is the directory containing the configuration file when a specific
configuration file is given or the current working directory otherwise.


## Main (`main`)

The main (`string`|`false`|`null`) setting is used to specify the file (relative to [`base-path`][base-path]) that will
be run when the PHAR is executed from the command line (To not confuse with the [stub][stub] which is the PHAR
bootstrapping file).

When you have a main script file that can be used as a [stub][stub], you can disable the main script by setting it to
false:

```json
{
    "stub": "bin/acme.php",
    "main": false
}
```

When the parameter is not given or set to `null`, Box tries to guess the binary of the application with the
`composer.json` file. If the [Composer `bin`][composer-bin] is set, Box will pick the first value provided. Otherwise it
will fallback on the [PHAR][phar class] default file used which is `index.php`.

The main file contents is processed by the [compactors][compactors] as the other files.

If the main file starts with a shebang line (`#!`), it will be automatically removed (the shebang line goes in the
[stub][stub] for a PHAR and is configured by the [shebang][shebang] setting).


## Output (`output`)

The output (`string`|`null`) setting specifies the file name and path of the newly built PHAR. If the value of the
setting is not an absolute path, the path will be relative to the base path.

If not provided or set to `null`, the default value used will based on the [`main`][main]. For example if the main file
is `bin/acme.php` or `bin/acme` then the output will be `bin/acme.phar`.


## Permissions (`chmod`)

The chmod (`string`|`null`) setting is used to change the file permissions of the newly built PHAR. The string contains
an octal value e.g. `0750`. By default the permissions of the created PHAR are unchanged so it should be `0644`.

Check the following [link](https://secure.php.net/manual/en/function.chmod.php) for more on the possible values.


## Check requirements (`check-requirements`)

The check requirements setting (`boolean`|`null`, default `true`) is used to allow the PHAR to check for the application
constraint before running. See more information about it [here][requirement-checker]. If not set or set to `null`, then
the requirement checker will be added. Note that this is true only if either the `composer.json`  or `composer.lock`
could have been found.

!!! Warning
    this check is still done within the PHAR. As a result, if [the required extension to open the PHAR][compression]
    due to the compression algorithm is not loaded, a hard failure will still appear: the requirement
    checker _cannot_ be executed before that.


## Including files

There is two ways to include files. The first one is to not be picky about which files are shipped in the PHAR. If you
omit any of the following options, _all_ the files found. The base directory used to find the files is either the
configuration file if one is used/specified or the current working directory otherwise. The [`blacklist`][blacklist]
setting can be used to filter out some files from that selection.

If you however want a more granular selection, you can use a combination of the following options: [`files`][files],
[`files-bin`][files], [`directories`][directories], [`directories-bin`][directories], [`finder`][finder],
[`finder-bin`][finder], [`blacklist`][blacklist].

If [`directories`][directories] or [`finder`][finder] is set (this includes empty values), Box will no longer try to
guess which files should be included or not (unless you [force the auto-discovery][force-autodiscovery]) and will give
you full control on it instead.

!!! Note
    By default, dev dependencies are excluded for both strategies. However if you still which to include a file
    or directory from a dev dependency, you can do so by adding it via one of the following setting: [`files`][files],
    [`files-bin`][files], [`directories`][directories] or [`directories-bin`][directories].


!!! Warning
    binary files are added _before_ regular files. As a result if a file is found in both regular files and
    binary files, the regular file will take precedence.


### Force auto-discovery (`force-autodiscovery`)

The `force-autodiscovery` (`bool` default `false`) setting forces Box to attempt to find which files to include even
though you are using the [`directories`][directories] or [`finder`][finder] setting.

When Box tries to find which files to include, it may remove some files such as readmes or test files. If however you
are using the [`directories`][directories] or [`finder`][finder], Box will _append_ the found files to the ones you
listed.


### Files (`files` and `files-bin`)

The `files` (`string[]`|`null` default `[]`) setting is a list of files paths relative to [`base-path`][base-path]
unless absolute. Each file will be processed by the [compactors][compactors], have their placeholder values replaced
(see: [`replacements`][placeholders]) and added to the PHAR.

This setting is not affected by the [`blacklist`][blacklist] setting.

`files-bin` is analogue to `files` except the files are added to the PHAR unmodified. This is suitable for the files
such as images, those that contain binary data or simply a file you do not want to alter at all despite using
[compactors][compactors].

!!! Warning
    Symlinks are not followed/supported.


### Directories (`directories` and `directories-bin`)

The directories (`string[]`|`null` default `[]`) setting is a list of directory paths relative to
[`base-path`][base-path]. All files will be processed by the [compactors][compactors], have their placeholder values
replaced (see: [`replacements`][placeholders]) and added to the PHAR.

Files listed in the [`blacklist`][blacklist] will not be added to the PHAR.

`directories-bin` is analogue to `directories` except the files are added to the PHAR unmodified. This is suitable for
the files such as images, those that contain binary data or simply a file you do not want to alter at all despite using
compactors.

!!! Warning
    Setting the key `directories` (regardless of its value), will disable the file auto-discovery. If you want
    to keep it, check the [force the auto-discovery][force-autodiscovery] setting.

!!! Warning
    By default Box excludes some files (e.g. dot files, readmes & co). This is done in order to attempt to
    reduce the final PHAR size. There is at the moment no way to disable this (maybe this could be done via a new setting)
    but it remains possible to include them via [`files`][files], [`files-bin`][files], `directories-bin` or your own
    [`finder`][finder] or [`finder-bin`][finder].

!!! Warning
    If some files are expected to be excluded from the [`finder`][finder] (respectively [`finder-bin`][finder]) but
    included in `directories` (respectively `directories-bin`), the those files **will be included**. The files included
    are a union of the directives.

!!! Warning
    Symlinks are not followed/supported.


### Finder (`finder` and `finder-bin`)

The finder (`object[]`|`null` default `[]`) setting is a list of JSON objects. Each object (key, value) tuple is a
(method, arguments) of the [Symfony Finder][symfony-finder] used by Box. If an array of values is provided for a single
key, the method will be called once per value in the array.

Note that the paths specified for the `in` method are relative to [`base-path`][base-path] and that the finder will
account for the files registered in the [`blacklist`][blacklist].

`finder-bin` is analogue to `finder` except the files are added to the PHAR unmodified. This is suitable for the files
such as images, those that contain binary data or simply a file you do not want to alter at all despite using
[compactors][compactors].

!!! Warning
    Setting the key `finder` (regardless of its value), will disable the file auto-discovery. If you want
    to keep it, check the [force the auto-discovery][force-autodiscovery] setting.

!!! Warning
    If some files are expected to be excluded from the [`finder`][finder] (respectively [`finder-bin`][finder]) but
    included in `directories` (respectively `directories-bin`), the those files **will be included**. The files included
    are a union of the directives.

!!! Warning
    Symlinks are not followed/supported.

Example:

```json
{
    "finder": [
          {
              "notName": "/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/",
              "exclude": [
                  "doc",
                  "test",
                  "test_old",
                  "tests",
                  "Tests",
                  "vendor-bin"
              ],
              "in": "vendor"
          },
          {
              "name": "composer.json",
              "in": "."
          }
    ]
}
```


### Blacklist (`blacklist`)

The `blacklist` (`string[]`|`null` default `[]`) setting is a list of files that must not be added. The files
blacklisted are the ones found using the other available configuration settings: [`files`][files], [`files-bin`][files],
[`directories`][directories], [`directories-bin`][directories], [`finder`][finder], [`finder-bin`][finder].

Note that all the blacklisted paths are relative to the settings configured above. For example if you have the following
file structure:

```text
project/
├── box.json.dist
├── A/
|   ├── A00
|   └── A01
└── B/
    ├── B00
    ├── B01
    └── A/
        └── BA00
```

With:

```json
{
    # other non file related settings

    "blacklist": [
        "A"
    ]
}
```

Box will try to collect all the files found in `project` (cf. [Including files][including-files]) but will exclude `A/`
and `B/A resulting in the following files being collected:

```text
project/
├── box.json.dist
└── B/
    ├── B00
    └── B01
```

If you want a more granular blacklist leverage, use the [Finders configuration][finder] instead.


### Excluding the Composer files (`exclude-composer-files`)

The `exclude-composer-files` (`boolean`|`null`, default `true`) setting will result in removing the Composer files
`composer.json`, `composer.lock` and `vendor/composer/installed.json` if they are found regardless of whether or not
they were found by Box alone or explicitly included.


### Excluding dev files (`exclude-dev-files`)

The `exclude-dev-files` (`bool` default `true`) setting will, when enabled, cause Box to attempt to exclude the files
belonging to dev only packages. For example for the given project:

<details>

<summary>composer.json</summary>

```json
{
    "require": {
        "beberlei/assert": "^3.0"
    },
    "require-dev": {
        "bamarni/composer-bin-plugin": "^1.2"
    }
}
```

</details>

The `vendor` directory will have `beberlei/assert` and `bamarni/composer-bin-plugin`. If `exclude-dev-files` is not
disabled, the `bamarni/composer-bin-plugin` package will be removed from the PHAR.

This setting will automatically be disabled when [`dump-autoload`][dump-autoload] is disabled. Indeed, otherwise some
files will not be shipped in the PHAR but may still appear in the Composer autoload classmap, resulting in an
autoloading error.


### Map (`map`)

The map (`object[]` default `[]`) setting is used to change where some (or all) files are stored inside the PHAR. The key
is a beginning of the relative path that will be matched against the file being added to the PHAR. If the key is a
match, the matched segment will be replaced with the value. If the key is empty, the value will be prefixed to all paths
(except for those already matched by an earlier key).

For example, with the following configuration excerpt:

```json
{
  "map": [
    { "my/test/path": "src/Test" },
    { "": "src/Another" }
  ]
}
```


with the following files added to the PHAR:

- `my/test/path/file.php`
- `my/test/path/some/other.php`
- `my/test/another.php`


the above files will be stored with the following paths in the PHAR:

- `src/Test/file.php`
- `src/Test/some/other.php`
- `src/Another/my/test/another.php`


## Stub

The [PHAR stub][phar.fileformat.stub] file is the PHAR bootstrapping file, i.e. the very first file executed whenever
the PHAR is executed. It usually contains things like the PHAR configuration and executing the [main script file][main].

The default PHAR stub file can be used but Box also propose a couple of options to customize the stub used.


### Stub (`stub`)

The stub (`string`|`boolean`|`null` default `true`) setting is used to specify the location of a stub file or if one
should be generated:

- `string`: Path to the stub file will be used as is inside the PHAR
- `true` (default): A new stub will be generated
- `false`: The default stub used by the PHAR class will be used

If a custom stub file is provided, none of the other options ([`shebang`][shebang], [`intercept`][intercept] and
[`alias`][alias]) are used.


### Shebang (`shebang`)

The shebang (`string`|`false`|`null`) setting is used to specify the shebang line used when generating a new stub. By
default, this line is used:

```sh
#!/usr/bin/env php
```

The shebang line can be removed altogether if set to `false`.


### Intercept (`intercept`)

The intercept (`boolean`|`null` default `false`) setting is used when generating a new stub. If setting is set to
`true`, the [Phar::interceptFileFuncs()][phar.interceptfilefuncs] method will be called in the stub.


### Alias (`alias`)

The `alias` (`string`|`null`) setting is used when generating a new stub to call the [`Phar::mapPhar()`][phar.mapphar].
This makes it easier to refer to files in the PHAR and ensure the access to internal files will always work regardless
of the location of the PHAR on the file system.

If no alias is provided, a generated unique name will be used for it in order to map the [main file][main]. Note that this
may have undesirable effects if you are using the generated [stub][stub-stub]

Example:

```php
// .phar.stub

#!/usr/bin/env php
<?php

if (class_exists('Phar')) {
    Phar::mapPhar('alias.phar');
    require 'phar://' . __FILE__ . '/index.php';
}

__HALT_COMPILER(); ?>


// index.php
<?php

if (!isset($GLOBALS['EXECUTE'])) {
    $GLOBALS['EXECUTE'] = true;
}

// On the first execution, we require that other file while
// on the second we will echo "Hello world!"
if ($GLOBALS['EXECUTE']) {
    require 'foo.php';
} else {
    echo 'Hello world!';
}


// foo.php
<?php

$GLOBALS['EXECUTE'] = false;

// Notice how we are using `phar://alias.phar` here. This will
// always work. This allows you to not have to find where the file
// is located in the PHAR neither finding the PHAR file path
require 'phar://alias.phar/index.php';

```

If you are using the default stub, [`Phar::setAlias()`][phar.setalias] will be used. Note however that this will behave
slightly differently.

Example:

```php
<?php

$phar = new Phar('index.phar'); // Warning: creating a Phar instance results in *loading* the file. From this point, the
                                // PHAR stub file has been loaded and as a result, if the PHAR had an alias the alias
                                // will be registered.
$phar->setAlias('foo.phar');
$phar->addFile('LICENSE');

file_get_contents('phar://foo.phar/LICENSE'); // Will work both inside the PHAR but as well as outside as soon as the
                                              // PHAR is loaded in-memory.
```

As you can see above, loading a PHAR which has an alias result in a non-negligible side effect. A typical case where this
might be an issue can be illustrated with box itself. For its end-to-end test, the process is along the lines of:

- 1. Build a PHAR `box.phar` from the source code
- 2. Build the PHAR `box.phar` from the source again but using the previous PHAR this time

If an alias `box-alias.phar` was registered for both for example, the building would fail. Indeed when building the second
PHAR, the first PHAR is loaded which loads the alias `box-alias.phar`. When creating the second PHAR, box would try to
register the alias `box-alias.phar` to that new PHAR but as the alias is already used, an error will be thrown.


### Banner (`banner`)

The banner (`string`|`string[]`|`false`|`null`) setting is the banner comment that will be used when a new stub is
generated. The value of this setting must not already be enclosed within a comment block as it will be automatically
done for you.

For example `Custom banner` will result in the stub file:

```php
/*
 * Custom banner
 */
```

An array of strings can be used for multilines banner:

```json
{
    "banner": [
          "This file is part of the box project.",
          "",
          "(c) Kevin Herrera <kevin@herrera.io>",
          "Théo Fidry <theo.fidry@gmail.com>",
          "",
          "This source file is subject to the MIT license that is bundled",
          "with this source code in the file LICENSE."
    ]
}
```

Will result in:

```php
/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
```

By default, the Box banner is used. If set to `false`, no banner at all will be used.

The content of this value is discarded if [`banner-file`][banner-file] is set.


### Banner file (`banner-file`)

The banner-file (`string`|`null` ignored by default) setting is like banner, except it is a path (relative to
[the base path][base-path]) to the file that will contain the comment.

Like banner, the comment must not already be enclosed in a comment block.

If this parameter is set to a different value than `null`, then the value of [`banner`][banner] will be discarded.


## Forcing the timestamp (`timestamp`)

The `timestamp` (`string`|`null`, default `null`) setting will result in Box forcing the timestamp of the PHAR. By
default, the timestamp of the PHAR is the one at which the PHAR was built. It may be useful to fix it for
[reproducible builds][reproducible-builds].

!!! Warning
    Forcing the timestamp cannot be done when using an [OpenSSL signature][algorithm].


## Dumping the Composer autoloader (`dump-autoload`)

The `dump-autoload` (`boolean`|`null`, default `true`) setting will result in Box dump the Composer autoload with the
[classmap authoritative mode][composer-classmap-authoritative] and the [`--no-dev` option][composer-no-dev-option] which
disables the `autoload-dev` rules. This is however done only if a `composer.json` file could be found. If a
`composer.lock` file is found as well, the file `vendor/composer/installed.json` will be required too.

The dumping of the autoloader will be _ignored_ if the `composer.json` file could not be found.

The autoloader is dumped at the end of the process to ensure it will take into account the eventual modifications done
by the [compactors][compactors] process.


## Compactors (`compactors`)

The compactors (`string[]`|`null` default `[]`) setting is a list of file contents compacting classes that must be
registered. A file compacting class is used to reduce the size of a specific file type. The following is a simple
example:

```php
<?php

namespace Acme;

use KevinGH\Box\Compactor\Compactor;

class MyCompactor implements Compactor
{
    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        if (preg_match('/\.txt/', \$file)) {
            return trim($contents);
        }

        return $contents;
    }
}
```

The following compactors are included with Box:

- `KevinGH\Box\Compactor\Json`: compress JSON files
- `KevinGH\Box\Compactor\Php`: strip down classes from phpdocs & comments
- `KevinGH\Box\Compactor\PhpScoper`: isolate the code using [PHP-Scoper][phpscoper]

The effects of the [compactors][compactors] and [replacement values][placeholders] can be tested with the `process`
command ✨.


### Annotations (`annotations`)

The annotations (`boolean`|`object`|`null` default `true`) setting is used to enable compacting annotations in PHP source
code.

This setting is only taken into consideration if the [`KevinGH\Box\Compactor\Php` compactor][compactors] is enabled.

By default, it removes all non real-like annotations from the PHP code. See the following example:

<details>
<summary>Original code</summary>

```php
<?php

/**
 * Function comparing the two given values
 *
 * @param int $x
 * @param int $y
 *
 * @return int
 *
 * @author Théo Fidry
 * @LICENSE MIT
 *
 * @Acme(type = "function")
 */
function foo($x, $y): int {
    // Compare the two values
    return $x <=> $y;
}
```

</details>

<details>
<summary>Compacted code</summary>

```php
<?php

/**
@Acme(type="function")










*/
function foo($x, $y): int {

 return $x <=> $y;
}
```

</details>


Note that the empty line returns are on purpose: it is to keep the same line number for the files between your source
code and the code bundled in the PHAR.

If you wish to keep all annotations, you can disable the annotations like so:

```json
{
    "annotations": false
}
```

For a more granular list, you can manually configure the list of annotations you wish to ignore:

```json
{
    "annotations": {
        "ignore": [
            "author",
            "package",
            "version",
            "see"
        ]
    }
}
```


### PHP-Scoper (`php-scoper`)

The PHP-Scoper setting (`string`|`null` default `scoper.inc.php`) points to the path to the
[PHP-Scoper configuration][php-scoper-configuration] file. For more documentation regarding PHP-Scoper, you can head to
[PHAR code isolation][PHAR code isolation] or [PHP-Scoper official documentation][php-scoper-official-doc].

Note that this setting is used only if the compactor `KevinGH\Box\Compactor\PhpScoper` is registered.


## Compression algorithm (`compression`)

The compression (`string`|`null` default `'NONE'`) setting is the compression algorithm to use when the PHAR is built. The
compression affects the individual files within the PHAR and not the PHAR as a whole
([`Phar::compressFiles()`][phar.compress]). The following is a list of the signature algorithms available:

- `GZ` (the most efficient most of the time)
- `BZ2`
- `NONE` (default)

!!! Warning
    Be aware that if compressed, the PHAR will required the appropriate extension ([`zlib`][zlib-extension] for
    `GZ` and [`bz2`][bz2-extension] for `BZ2`) to execute the PHAR. Without it, PHP will _not_ be able to open the PHAR
    at all.


## Security

### Signing algorithm (`algorithm`)

The algorithm (`string`|`null` default `SHA1`) setting is the signing algorithm to use when the PHAR is built
([`Phar::setSignatureAlgorithm()`][phar.setsignaturealgorithm]). The following is a list of the signature algorithms
available:

- `MD5`
- `SHA1`
- `SHA256`
- `SHA512`
- `OPENSSL` (deprecated)

By default, PHARs are `SHA1` signed.

The `OPENSSL` algorithm will require to provide [a key][key].

!!! warning

    The OpenSSL signature has been deprecated as of Box 4.4.0. If you are wondering why check out
    [the signing best practices].


### The private key (`key`)

The key (`string`|`null` default `null`) setting is used to specify the path to the private key file. The private key file
will be used to sign the PHAR using the `OPENSSL` signature algorithm (see [Signing algorithm][algorithm]) and the
setting will be completely ignored otherwise. If an absolute path is not provided, the path will be relative to the
current working directory.


### The private key password (`key-pass`)

The private key password  (`string`|`boolean`|`null` default `null`) setting is used to specify the pass-phrase for the
private key. If a string is provided, it will be used as is as the pass-phrase. If `true` is provided, you will be
prompted for the passphrase unless you are not in an interactive environment.

This setting will be ignored if no [key][key] has been provided.


## Metadata (`metadata`)

!!! warning

    The metadata setting has been deprecated as of Box 4.6.0. See [#1152].

The metadata (`any` default none) setting can be any value. This value will be stored as metadata that can be retrieved
from the built PHAR ([Phar::getMetadata()][phar.getmetadata]).

If you specify a callable (as a string), it will be evaluated without any arguments.

For example, if you take the following code:

```php
<?php
# callable_script.php
class MyCallbacks
{
    public static function generateMetadata()
    {
        return ['application_version' => '1.0.0-dev'];
    }
}
```

With the configuration excerpt:

```json
{
    "metadata": "MyCallbacks::generateMetadata"
}
```

Then the `Phar::getMetadata()` will return `['application_version' => '1.0.0-dev']` array.

!!! warning

    Your callable function must be readable by your autoloader.

That means, for Composer, in previous example, we require to have such kind of declaration in your `composer.json` file.

```json
{
    "autoload": {
        "files": ["/path/to/your/callable_script.php"]
    }
}
```

## Replaceable placeholders

This feature allows you to set placeholders in your code which will be replaced by different values by Box when building
the PHAR.

For example, if you take the following code:

```php
<?php

class Application
{
    public function getVersion(): string
    {
        return '@git_commit_short@';
    }
}
```

With the configuration excerpt:

```json
{
    "git-commit-short": "git_commit_short"
}
```

Then the actual code shipped in the PHAR will be:

```php
<?php

class Application
{
    public function getVersion(): string
    {
        return 'a6c5d93';
    }
}
```

The `@` is the default value of the [sigil][replacement-sigil] which is the placeholders delimited and
[`git-commit-short`][git-commit-short] is one of the built in placeholder. Box ships a few buit-in placeholders
which you can find bellow, but you can also specify any replacement value via the
[`replacements` setting][replacements].

The effects of the [compactors][compactors] and [replacement values][placeholders] can be tested with the `process`
command ✨.


### Replacements (`replacements`)

The replacements (`object`|`null`, default `{}`) setting is a map of placeholders (as keys) and their values. The
placeholders are replaced in all [non-binary files][including-files] with the specified values.

For example:

```json
{
    "replacements": {
        "foo": "bar"
    }
}
```

Will result in the string `@foo@` in your code to be replaced by `'bar'`. The delimiter `@` being the
[sigil][replacement-sigil].


### Replacement sigil (`replacement-sigil`)

The replacement sigil (`string`|`null` default `@`) is the character or chain of characters used to delimit the
placeholders. See the @[replacements][replacements] setting for examples of placeholders.


### Datetime placeholder (`datetime`)

The datetime (`string`|`null` default `null`) setting is the name of a placeholder value that will be replaced in all
[non-binary files][including-files] by the current datetime. If no value is given (`null`) then this placeholder will
be ignored.

Example value the placeholder will be replaced with: `2015-01-28 14:55:23 CEST`

The format of the date used is defined by the [`datetime-format` setting][datetime-placeholder-format].


### Datetime placeholder format (`datetime-format`)

The datetime format placeholder (`string`|`null`, default `Y-m-d H:i:s T`) setting accepts a valid
[PHP date format][php-date-format]. It can be used to change the format for the
[`datetime`][datetime–placeholder] setting.


### Pretty git tag placeholder (`git`)

The git tag placeholder (`string`|`null` default `null`) setting is the name of a placeholder value that will be replaced
in all [non-binary files][including-files] by the current git tag of the repository.

Example of value the placeholder will be replaced with:

- `2.0.0` on an exact tag match
- `2.0.0@e558e33` on a commit following a tag


### Git commit placeholder (`git-commit`)

The git commit (`string`|`null` default `null`) setting is the name of a placeholder value that will be replaced in all
[non-binary files][including-files] by the current git commit hash of the repository.

Example of value the placeholder will be replaced with: `e558e335f1d165bc24d43fdf903cdadd3c3cbd03`


### Short git commit placeholder (`git-commit-short`)

The short git commit (`string`|`null` default `null`) setting is the name of a placeholder value that will be replaced in
all [non-binary files][including-files] by the current git short commit hash of the repository.

Example of value the placeholder will be replaced with: `e558e33`


### Git tag placeholder (`git-tag`)

The git tag placeholder (`string`|`null` default `null`) setting is the name of a placeholder value that will be replaced
in all [non-binary files][including-files] by the current git tag of the repository.

Example of value the placeholder will be replaced with:

- `2.0.0` on an exact tag match
- `2.0.0-2-ge558e33` on a commit following a tag


### Git version placeholder (`git-version`)

The git version (`string`|`null` default `null`) setting is the name of a placeholder value that will be replaced in all
[non-binary files][including-files] by the one of the following (in order):

- The git repository's most recent tag.
- The git repository's current short commit hash.

The short commit hash will only be used if no tag is available.


<br />
<hr />

« [Installation](installation.md#installation) • [Requirement Checker](requirement-checker.md#requirements-checker) »


[PHAR code isolation]: code-isolation.md#phar-code-isolation
[algorithm]: #signing-algorithm-algorithm
[alias]: #alias-alias
[annotations-compactor]: #annotations-annotations
[banner-file]: #banner-file-banner-file
[banner]: #banner-banner
[base-path]: #base-path-base-path
[blacklist]: #blacklist-blacklist
[bz2-extension]: https://secure.php.net/manual/en/book.bzip2.php
[check-requirements]: #check-requirements-check-requirements
[compactors]: #compactors-compactors
[composer-bin]: https://getcomposer.org/doc/04-schema.md#bin
[composer-classmap-authoritative]: https://getcomposer.org/doc/articles/autoloader-optimization.md#optimization-level-2-a-authoritative-class-maps
[composer-no-dev-option]: https://getcomposer.org/doc/03-cli.md#dump-autoload-dumpautoload-
[compression]: #compression-algorithm-compression
[datetime-placeholder-format]: #datetime-placeholder-format-datetime-format
[datetime–placeholder]: #datetime-placeholder-datetime
[directories]: #directories-directories-and-directories-bin
[dump-autoload]: #dumping-the-composer-autoloader-dump-autoload
[exclude-composer-files]: #excluding-the-composer-files-exclude-composer-files
[exclude-dev-files]: #excluding-dev-files-exclude-dev-files
[force-autodiscovery]: #force-auto-discovery-force-autodiscovery
[files]: #files-files-and-files-bin
[finder]: #finder-finder-and-finder-bin
[git]: #pretty-git-tag-placeholder-git
[git-commit-placeholder]: #git-commit-placeholder-git-commit
[git-commit-short]: #short-git-commit-placeholder-git-commit-short
[git-tag-placeholder]: #git-tag-placeholder-git-tag
[git-version-placeholder]: #git-version-placeholder-git-version
[including-files]: #including-files
[intercept]: #intercept-intercept
[key-pass]: #the-private-key-password-key-pass
[key]: #the-private-key-key
[main]: #main-main
[map]: #map-map
[metadata]: #metadata-metadata
[output]: #output-output
[permissions]: #permissions-chmod
[phar class]: https://secure.php.net/manual/en/class.phar.php
[phar.compress]: https://secure.php.net/manual/en/phar.compress.php
[phar.fileformat.stub]: https://secure.php.net/manual/en/phar.fileformat.stub.php
[phar.getmetadata]: https://secure.php.net/manual/en/phar.getmetadata.php
[phar.interceptfilefuncs]: https://secure.php.net/manual/en/phar.interceptfilefuncs.php
[phar.mapphar]: https://secure.php.net/manual/en/phar.mapphar.php
[phar.setalias]: https://secure.php.net/manual/en/phar.setalias.php
[phar.setsignaturealgorithm]: https://secure.php.net/manual/en/phar.setsignaturealgorithm.php
[php-date-format]: https://secure.php.net/manual/en/function.date.php
[php-scoper-compactor]: #php-scoper-php-scoper
[php-scoper-configuration]: https://github.com/humbug/php-scoper#configuration
[php-scoper-official-doc]: https://github.com/humbug/php-scoper
[phpscoper]: https://github.com/humbug/php-scoper
[placeholders]: #replaceable-placeholders
[replacement-sigil]: #replacement-sigil-replacement-sigil
[replacements]: #replacements-replacements
[reproducible-builds]: reproducible-builds.md#reproducible-builds
[requirement-checker]: requirement-checker.md#requirements-checker
[security]: #security
[shebang]: #shebang-shebang
[timestamp]: #forcing-the-timestamp-timestamp
[the signing best practices]: ./phar-signing.md#phar-signing-best-practices
[stub-stub]: #stub-stub
[stub]: #stub
[symfony-finder]: https://symfony.com/doc/current//components/finder.html
[zlib-extension]: https://secure.php.net/manual/en/book.zlib.php
[#1152]: https://github.com/box-project/box/issues/1152
