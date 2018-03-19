# Configuration

1. [Base path][base-path]
1. [Output][output]
1. [Main][main]
1. [Permissions][permissions]
1. [Including files][including-files]
    1. [Files (`files` and `files-bin`)][files]
    1. [Directories (`directories` and `directories-bin`)][directories]
    1. [Finder (`finder` and `finder-bin`)][finder]
    1. [Blacklist (`blacklist`)][blacklist]
1. [Stub][stub]
    1. [Stub (`stub`)][stub-stub]
    1. [Alias (`alias`)][alias]
    1. [Shebang (`shebang`)][shebang]
    1. [Banner (`banner`)][banner]
    1. [Banner file (`banner-file`)][banner-file]
1. [Compactors (`compactors`)][compactors]
1. [Compression algorithm (`compression`)][compression]
1. [Signing algorithm (`algorithm`)][algorithm]


// TODO: do not mention when an option is optional but add a red asterix with a foot note for the mandatory
// fields.
// TODO: right now this documentation is a copy/paste of the doc from the comment. This needs to be reworked

The build command will build a new PHAR based on a variety of settings.

This command relies on a configuration file for loading PHAR packaging settings. If a configuration file is not
specified through the `--configuration|-c option`, one of the following files will be used (in order): `box.json`,
`box.json.dist`. If no configuration file is found, Box will proceed with the default settings.

The configuration file is a JSON object saved to a file. Note that all settings are optional.
//TODO: update this last bit of information as this is not true

```json
{
    "algorithm": "?",
    "alias": "?",
    "banner": "?",
    "banner-file": "?",
    "base-path": "?",
    "blacklist": "?",
    "chmod": "?",
    "compactors": "?",
    "compression": "?",
    "datetime": "?",
    "datetime_format": "?",
    "directories": "?",
    "directories-bin": "?",
    "files": "?",
    "files-bin": "?",
    "finder": "?",
    "finder-bin": "?",
    "git-version": "?",
    "intercept": "?",
    "key": "?",
    "key-pass": "?",
    "main": "?",
    "map": "?",
    "metadata": "?",
    "output": "?",
    "replacements": "?",
    "shebang": "?"
}
```


## Base-path (`base-path`)

The `base-path` (`string`|`null`) setting is used to specify where all of the relative file paths should resolve to.
This does not, however, alter where the built PHAR will be stored (see: `output`).

If set to `null` or not specified, the base path used is the directory containing the configuration file when a specific
configuration file is given or the current working directory otherwise.


## Output (`output`)

The output (`string`) setting specifies the file name and path of the newly built PHAR. If the value of the setting is
not an absolute path, the path will be relative to the base path.

If not provided, the default value used will be `default.phar`.


## Main (`main`)

The main (`string`) setting is used to specify the file (relative to [`base-path`][base-path]) that will be run when the
PHAR is executed from the command line (To not confuse with the [stub][stub] which is the PHAR bootstrapping file).

The default file used is `index.php`.

The main file contents is processed by the [compactors][compactors] as the other files. 

If the main file starts with a shebang line (`#!`), it will be automatically removed (the shebang line goes in the
[stub][stub] for a PHAR and is configured by the [shebang][shebang] setting).


## Permissions (`chmod`)

The chmod (`string`|`null`) setting is used to change the file permissions of the newly built PHAR. The string contains
an octal value e.g. `0750`. By default the permissions of the created PHAR are unchanged so it should be `0644`.

Check the following [link](https://secure.php.net/manual/en/function.chmod.php) for more on the possible values.



## Including files

There is two ways to include files. The first one is to not be picky about which files are shipped in the PHAR. If you
omit any of the following options, _all_ the files found. The base directory used to find the files is either the
configuration file if one is used/specified or the current working directory otherwise. The [`blacklist`][blacklist]
setting can be used to filter out some files from that selection.

If you however want a more granular selection, you can use a combination of the following options: [`files`][files],
[`files-bin`][files], [`directories`][directories], [`directories-bin`][directories], [`finder`][finder],
[`finder-bin`][finder], [`blacklist`][blacklist].
If any of the settings above except for [`blacklist`][blacklist] is set (this includes empty values), those settings
will be used in order to collect the files instead to collect all the files available.

**Note:** By default, dev dependencies are excluded for both strategies. However if you still which to include a file
or directory from a dev dependency, you can do so by adding it via one of the following setting: [`files`][files],
[`files-bin`][files], [`directories`][directories] or [`directories-bin`][directories].

### Files (`files` and `files-bin`)

The `files` (`string[]`) setting is a list of files paths relative to [`base-path`][base-path] unless absolute. Each
file will be processed by the [compactors][compactors], have their placeholder values replaced (see: `replacements`)
and added to the PHAR.

This setting is not affected by the [`blacklist`][blacklist] setting.

`files-bin` is analogue to `files` except the files are added to the PHAR unmodified. This is suitable for the files
such as images, those that contain binary data or simply a file you do not want to alter at all despite using
[compactors][compactors].


### Directories (`directories` and `directories-bin`)

The directories (`string[]`) setting is a list of directory paths relative to [`base-path`][base-path]. All files will
be processed by the [compactors][compactors], have their placeholder values replaced (see: `replacements`) and added to
the PHAR.

Files listed in the [`blacklist`][blacklist] will not be added to the PHAR.

`directories-bin` is analogue to `directories` except the files are added to the PHAR unmodified. This is suitable for
the files such as images, those that contain binary data or simply a file you do not want to alter at all despite using
compactors.


### Finder (`finder` and `finder-bin`)

The finder (`object[]`) setting is a list of JSON objects. Each object (key, value) tuple is a (method, arguments)
of the [Symfony Finder][symfony-finder] used by Box. If an array of values is provided for a single key, the method will
be called once per value in the array.
 
Note that the paths specified for the `in` method are relative to [`base-path`][base-path] and that the finder will
account for the files registered in the [`blacklist`][blacklist].

`finder-bin` is analogue to `finder` except the files are added to the PHAR unmodified. This is suitable for the files
such as images, those that contain binary data or simply a file you do not want to alter at all despite using
[compactors][compactors].

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

The `blacklist` (`string[]`) setting is a list of files that must not be added. The files blacklisted are the ones found
using the other available configuration settings: [`files`][files], [`files-bin`][files], [`directories`][directories],
[`directories-bin`][directories], [`finder`][finder], [`finder-bin`][finder].


## Stub

The [PHAR stub][phar.fileformat.stub] file is the PHAR bootstrapping file, i.e. the very first file executed whenever
the PHAR is executed. It usually contains things like the PHAR configuration and executing the [main script file][main].

The default PHAR stub file can be used but Box also propose a couple of options to customize the stub used. 


### Stub (`stub`)

The stub (`string`|`boolean`) setting is used to specify the location of a stub file or if one should be generated:
- `string`: Path to the stub file will be used as is inside the PHAR
- `true` (default): A new stub will be generated
- `false`: The default stub used by the PHAR class will be used

If a custom stub file is provided, none of the other options ([`shebang`][shebang], [`intercept`][intercept] and
[`alias`][alias]) are used.


### Shebang (`shebang`)

The shebang (`string`|`null`) setting is used to specify the shebang line used when generating a new stub. By default,
this line is used:

```
#!/usr/bin/env php
```

The shebang line can be removed altogether if set to `null`.


### Intercept (`intercept`)

The intercept (`boolean`) setting is used when generating a new stub. If setting is set to `true`, the 
[Phar::interceptFileFuncs()][phar.interceptfilefuncs] method will be called in the stub.

This setting is set to `false` by default.


### Alias (`alias`)

The `alias` (`string`) setting is used when generating a new stub to call the [`Phar::mapPhar()`](phar.mapphar). This
makes it easier to refer to files in the PHAR and ensure the access to internal files will always work regardless of the
location of the PHAR on the file system.

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
slightly
differently.

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

The banner (`string`|`string[]`|`null`) setting is the banner comment that will be used when a new stub is generated. The
value of this setting must not already be enclosed within a comment block as it will be automatically done for you.

For example `Custom banner` will result in the stub file:

```
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

By default, the Box banner is used. If set to `null`, no banner at all will be used.

The content of this value is discarded if [`banner-file`][banner-file] is set.


### Banner file (`banner-file`)

The banner-file (`string`) setting is like banner, except it is a path (relative to [the base path][base-path]) to the
file that will contain the comment.

Like banner, the comment must not already be enclosed in a comment block.

If this parameter is set, then the value of [`banner`][banner] will be discarded.


## Compactors (`compactors`)

The compactors (`string[]`) setting is a list of file contents compacting classes that must be registered. A file
compacting class is used to reduce the size of a specific file type. The following is a simple example:

```php
<?php

namespace Acme;

use KevinGH\Box\Compactor;

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
- `KevinGH\Box\Compactor\PhpScoper`: isolate the code using [PhpScoper][phpscoper]


## Compression algorithm (`compression`)

The compression (`string`|`null`) setting is the compression algorithm to use when the PHAR is built. The compression
affects the individual files within the PHAR and not the PHAR as a whole ([`Phar::compressFiles()`][phar.compress]). The
following is a list of the signature algorithms available:

- `BZ2`
- `GZ` (the most efficient most of the time)
- `NONE` (default)


## Signing algorithm (`algorithm`)

The algorithm (`string`|`null`) setting is the signing algorithm to use when the PHAR is built (
[`Phar::setSignatureAlgorithm()`][phar.setsignaturealgorithm]). The following is a list of the signature algorithms
available:

- `MD5`
- `SHA1`
- `SHA256`
- `SHA512`
- `OPENSSL`

By default the PHAR is not signed.


<br />
<hr />


« [Creating a PHAR](../README.md#creating-a-phar) • [Table of Contents](../README.md#table-of-contents) »


[alias]: #alias-alias
[base-path]: #base-path-base-path
[output]: #output-output
[main]: #main-main
[including-files]: #including-files
[files]: #files-files-and-files-bin
[directories]: #directories-directories-and-directories-bin
[finder]: #finder-finder-and-finder-bin
[blacklist]: #blacklist-blacklist
[stub]: #stub
[stub-stub]: #stub-stub
[shebang]: #shebang-shebang
[banner]: #banner-banner
[banner-file]: #banner-file-banner-file
[phar.mapphar]: https://secure.php.net/manual/en/phar.mapphar.php
[phar.setalias]: https://secure.php.net/manual/en/phar.setalias.php
[phar.webphar]: https://secure.php.net/manual/en/phar.webphar.php
[phar.fileformat.stub]: https://secure.php.net/manual/en/phar.fileformat.stub.php
[phar.interceptfilefuncs]: https://secure.php.net/manual/en/phar.interceptfilefuncs.php
[phar.setsignaturealgorithm]: https://secure.php.net/manual/en/phar.setsignaturealgorithm.php
[phar.compress]: https://secure.php.net/manual/en/phar.compress.php
[symfony-finder]: https://symfony.com/doc/current//components/finder.html
[phpscoper]: https://github.com/humbug/php-scoper
[compactors]: #compactors-compactors
[permissions]: #permissions-chmod
[compression]: #compression-algorithm-compression
[algorithm]: #signing-algorithm-algorithm




//TODO: rework the rest




The annotations (boolean, object) setting is used to enable compacting
annotations in PHP source code. By setting it to true, all Doctrine-style
annotations are compacted in PHP files. You may also specify a list of
annotations to ignore, which will be stripped while protecting the remaining
annotations:

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

You may want to see this website for a list of annotations which are commonly
ignored:

https://github.com/herrera-io/php-annotations











The datetime (string) setting is the name of a placeholder value that will be
replaced in all non-binary files by the current datetime.

Example: 2015-01-28 14:55:23

The datetime_format (string) setting accepts a valid PHP date format. It can be used to change the format for the datetime setting.

Example: Y-m-d H:i:s

The git-commit (string) setting is the name of a placeholder value that will
be replaced in all non-binary files by the current Git commit hash of the
repository.

Example: e558e335f1d165bc24d43fdf903cdadd3c3cbd03

The git-commit-short (string) setting is the name of a placeholder value that
will be replaced in all non-binary files by the current Git short commit hash
of the repository.

Example: e558e33

The git-tag (string) setting is the name of a placeholder value that will be
replaced in all non-binary files by the current Git tag of the repository.

Examples:

- 2.0.0
- 2.0.0-2-ge558e33


The git-version (string) setting is the name of a placeholder value that will
be replaced in all non-binary files by the one of the following (in order):

- The git repository's most recent tag.
- The git repository's current short commit hash.

The short commit hash will only be used if no tag is available.




The key (string) setting is used to specify the path to the private key file.
The private key file will be used to sign the PHAR using the OPENSSL signature
algorithm. If an absolute path is not provided, the path will be relative to
the current working directory.

The key-pass (string, boolean) setting is used to specify the passphrase for
the private key. If a string is provided, it will be used as is as the
passphrase. If true is provided, you will be prompted for the passphrase.

The map (array) setting is used to change where some (or all) files are stored
inside the PHAR. The key is a beginning of the relative path that will be
matched against the file being added to the PHAR. If the key is a match, the
matched segment will be replaced with the value. If the key is empty, the
value will be prefixed to all paths (except for those already matched by an
earlier key).


{
  "map": [
    { "my/test/path": "src/Test" },
    { "": "src/Another" }
  ]
}


(with the files)


1. my/test/path/file.php
2. my/test/path/some/other.php
3. my/test/another.php


(will be stored as)


1. src/Test/file.php
2. src/Test/some/other.php
3. src/Another/my/test/another.php


The metadata (any) setting can be any value. This value will be stored as
metadata that can be retrieved from the built PHAR (Phar::getMetadata()).

The replacements (object) setting is a map of placeholders and their values.
The placeholders are replaced in all non-binary files with the specified
values.




