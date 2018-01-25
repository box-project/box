# Configuration

// TODO: do not mention when an option is optional but add a red asterix with a foot note for the mandatory
// fields.
// TODO: right now this documentation is a copy/paste of the doc from the comment. This needs to be reworked

The build command will build a new PHAR based on a variety of settings.

    This command relies on a configuration file for loading
    PHAR packaging settings. If a configuration file is not
    specified through the --configuration|-c option, one of
    the following files will be used (in order): box.json,
    box.json.dist

The configuration file is actually a JSON object saved to a file. Note that all settings are optional.
//TODO: update this last bit of information as this is not true

```json
{
    "algorithm": "?",
    "alias": "?",
    "banner": "?",
    "banner-file": "?",
    "base-path": "?",
    "blacklist": "?",
    "bootstrap": "?",
    "chmod": "?",
    "compactors": "?",
    "compression": "?",
    "datetime": "?",
    "datetime_format": "?",
    "directories": "?",
    "directories-bin": "?",
    "extract": "?",
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
    "mimetypes": "?",
    "mung": "?",
    "not-found": "?",
    "output": "?",
    "replacements": "?",
    "shebang": "?",
    "stub": "?",
    "web": "?"
}
```


## Alias

The `alias` (`string) setting is used when generating a new stub to call the [`Phar::mapPhar()`](phar.mapphar) method if the PHAR is for the
CLI and the method [`Phar::webPhar()`][phar.webphar] if the PHAR is configured for the web. This makes it easier to refer to files in the
PHAR and ensure the access to internal files will always work regardless of the location of the PHAR on the file system.

If no alias is provided, the value `default.phar` will be used.

Example:

```php
<?php

$phar = new Phar('index.phar'); // Warning: creating a Phar instance results in *loading* the file. From this point, the PHAR stub file
                                // has been loaded and as a result, if the PHAR had an alias the alias will be registered.
$phar->setAlias('foo.phar');
$phar->addFile('LICENSE');

file_get_contents('phar://foo.phar/LICENSE');   // Will work both inside the PHAR but as well as outside as soon as the PHAR is loaded
                                                // in-memory.
```

As you can see above, loading a PHAR which has an alias result in a non-negligeable side effect. A typical case where this might be an issue
can be illustrated with box itself. For its end-to-end test, the process is along the lines of:

- 1. Build a PHAR `box.phar` from the source code
- 2. Build the PHAR `box.phar` from the source again but using the previous PHAR this time

If an alias `box-alias.phar` was registered for both for example, the building would fail. Indeed when building the second PHAR, the first
PHAR is loaded which loads the alias `box-alias.phar`. When creating the second PHAR, box would try to register the alias `box-alias.phar`
to that new PHAR but as the alias is already used, an error will be thrown.



<br />
<hr />

[phar.mapphar]: https://secure.php.net/manual/en/phar.mapphar.php
[phar.webphar]: https://secure.php.net/manual/en/phar.webphar.php


//TODO: rework the rest



The (optional) algorithm (string) setting is the signing algorithm to use when
the PHAR is built (Phar::setSignatureAlgorithm()). The following is a list of
the signature algorithms available:

- MD5 (Phar::MD5)
- SHA1 (Phar::SHA1)
- SHA256 (Phar::SHA256)
- SHA512 (Phar::SHA512)
- OPENSSL (Phar::OPENSSL)

Further help:

https://secure.php.net/manual/en/phar.setsignaturealgorithm.php


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


The banner (string) setting is the banner comment that will be used when a new
stub is generated. The value of this setting must not already be enclosed
within a comment block, as it will be automatically done for you.

The banner-file (string) setting is like banner, except it is a path to the
file that will contain the comment. Like banner, the comment must not already
be enclosed in a comment block.

The base-path (string) setting is used to specify where all of the relative
file paths should resolve to. This does not, however, alter where the built
PHAR will be stored (see: output). By default, the base path is the directory
containing the configuration file.

The blacklist (string[]) setting is a list of files that must not be added.
The files blacklisted are the ones found using the other available
configuration settings: directories, directories-bin, files, files-bin,
finder, finder-bin. Note that directory separators are automatically corrected
to the platform specific version.

Assuming that the base directory path is /home/user/project:

{
    "blacklist": [
        "path/to/file/1"
        "path/to/file/2"
    ],
    "directories": ["src"]
}

The following files will be blacklisted:

- /home/user/project/src/path/to/file/1
- /home/user/project/src/path/to/file/2

But not these files:

- /home/user/project/src/another/path/to/file/1
- /home/user/project/src/another/path/to/file/2


The bootstrap (string) setting allows you to specify a PHP file that will be
loaded before the build or add commands are used. This is useful for loading
third-party file contents compacting classes that were configured using the
compactors setting.

The chmod (string) setting is used to change the file permissions of the newly
built PHAR. The string contains an octal value: 0750.

Check the following link for more on the possible values:

https://secure.php.net/manual/en/function.chmod.php


The compactors (string[]) setting is a list of file contents compacting
classes that must be registered. A file compacting class is used to reduce the
size of a specific file type. The following is a simple example:

use Herrera\\Box\\Compactor\\CompactorInterface;

class MyCompactor implements CompactorInterface
{
    public function compact(\$contents)
    {
        return trim(\$contents);
    }

    public function supports(\$file)
    {
        return (bool) preg_match('/\.txt/', \$file);
    }
}

The following compactors are included with Box:

- Herrera\\Box\\Compactor\\Json
- Herrera\\Box\\Compactor\\Php


The compression (string) setting is the compression algorithm to use when the
PHAR is built. The compression affects the individual files within the PHAR,
and not the PHAR as a whole (Phar::compressFiles()). The following is a list
of the signature algorithms listed on the help page:

- BZ2 (Phar::BZ2)
- GZ (Phar::GZ)
- NONE (Phar::NONE)


The directories (string[]) setting is a list of directory paths relative to
base-path. All files ending in .php will be automatically compacted, have
their placeholder values replaced, and added to the PHAR. Files listed in the
blacklist setting will not be added.

The directories-bin (string[]) setting is similar to directories, except all
file types are added to the PHAR unmodified. This is suitable for directories
containing images or other binary data.

The extract (boolean) setting determines whether or not the generated stub
should include a class to extract the PHAR. This class would be used if the
PHAR is not available. (Increases stub file size.)

The files (string[]) setting is a list of files paths relative to base-path.
Each file will be compacted, have their placeholder files replaced, and added
to the PHAR. This setting is not affected by the blacklist setting.

The files-bin (string[]) setting is similar to files, except that all files
are added to the PHAR unmodified. This is suitable for files such as images or
those that contain binary data.

The finder (array) setting is a list of JSON objects. Each object key is a
name, and each value an argument for the methods in
the Symfony\Component\Finder\Finder class. If an array of values is provided
for a single key, the method will be called once per value in the array. Note
that the paths specified for the "in" method are relative to base-path.

The finder-bin (array) setting performs the same function, except all files
found by the finder will be treated as binary files, leaving them unmodified.

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

The intercept (boolean) setting is used when generating a new stub. If setting
is set to true, the Phar::interceptFileFuncs(); method will be called in the
stub.

For more information:

https://secure.php.net/manual/en/phar.interceptfilefuncs.php


The key (string) setting is used to specify the path to the private key file.
The private key file will be used to sign the PHAR using the OPENSSL signature
algorithm. If an absolute path is not provided, the path will be relative to
the current working directory.

The key-pass (string, boolean) setting is used to specify the passphrase for
the private key. If a string is provided, it will be used as is as the
passphrase. If true is provided, you will be prompted for the passphrase.

The main (string) setting is used to specify the file (relative to base-path)
that will be run when the PHAR is executed from the command line. If the file
was not added by any of the other file adding settings, it will be
automatically added after it has been compacted and had its placeholder values
replaced. The shebang line #! will be automatically removed if present.

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

The mimetypes (object) setting is used when generating a new stub. It is a map
of file extensions and their mimetypes. To see a list of the default mapping,
please visit:

http://www.php.net/manual/en/phar.webphar.php

The mung (array) setting is used when generating a new stub. It is a list of
server variables to modify for the PHAR. This setting is only useful when the
web setting is enabled.

The not-found (string) setting is used when generating a new stub. It
specifies the file that will be used when a file is not found inside the PHAR.
This setting is only useful when web setting is enabled.

The output (string) setting specifies the file name and path of the newly
built PHAR. If the value of the setting is not an absolute path, the path will
be relative to the current working directory.

The replacements (object) setting is a map of placeholders and their values.
The placeholders are replaced in all non-binary files with the specified
values.

The shebang (string) setting is used to specify the shebang line used when
generating a new stub. By default, this line is used:

#!/usr/bin/env php

The shebang line can be removed altogether if false or an empty string is
provided.

The stub (string, boolean) setting is used to specify the location of a stub
file, or if one should be generated. If a path is provided, the stub file will
be used as is inside the PHAR. If true is provided, a new stub will be
generated. If false (or nothing) is provided, the default stub used by the
PHAR class will be used.

The web (boolean) setting is used when generating a new stub. If true is
provided, Phar::webPhar() will be called in the stub.
