Box
===

[![Build Status][]](https://travis-ci.org/box-project/box2-lib)

Box is a library built on the [`Phar`][] class. It is designed to make it
easier to create new phars and modifying existing ones. Features include
compacting source files, better custom stub generation, and better OpenSSL
signing handling.

Example
-------

```php
use Herrera\Box\Box;
use Herrera\Box\StubGenerator;

$box = Box::create('test.phar');

$box->buildFromDirectory('/path/to/dir');

$box->getPhar()->setStub(
    StubGenerator::create()
        ->index('path/to/script.php')
        ->generate()
);
```

Installation
------------

Add it to your list of Composer dependencies:

```sh
$ composer require herrera-io/box=1.*
```

Usage
-----

The Box library includes many features and some are designed so that they can
be used independently of each other. This is done to allow the phar builder
better control of the phar building process.

### Compacting Source Files

Box makes uses of source file "compactors". A compactor is simply a class
that checks if the given file is supported, then manipulates its contents
to make it smaller. I will later cover how to actually use them in
**Finally, Building Phars**.

There are two ways of creating a compactor class: implement the
`CompactorInterface` interface, or extend the `Compactor` abstract class.

#### Implementing `CompactorInterface`

The [`CompactorInterface`][] interface only requires that you implement two
methods in your class: `compact($contents)` and `support($file)`. The
`$contents` argument is the contents of the source file, and the `$file`
argument is the full path to the file. How you determine which file types
are supported is entirely up to you.

In this example, this custom compactor will only modify files that end in
`.php`, and remove whitespace from the end of each line:

```php
namespace Example\Compactor;

use Herrera\Box\Compactor\CompactorInterface;

/**
 * My example compactor.
 */
class RemoveWhitespace implements CompactorInterface
{
    /**
     * Seek and destroy (whitespaces).
     *
     * @param string $source The source code.
     *
     * @return string The compacted source code.
     */
    public function compact($source)
    {
        return preg_replace('/[ \t]+$/m', '', $source);
    }

    /**
     * Make sure we support it.
     *
     * @param string $file The file path.
     *
     * @return boolean Returns TRUE if supported, FALSE if not.
     */
    public function supports($file)
    {
        return ('php' === pathinfo($file, PATHINFO_EXTENSION));
    }
}
```

#### Extending `Compactor`

An abstract compactor class is included that handles file type checking for
you. You simply need to provide the default list of file extensions supported.
These extension can be overwritten later if necessary, by the developer using
them.

This example is a variation of the previous example compactor:

```php
namespace Example\Compactor;

use Herrera\Box\Compactor\Compactor;

/**
 * My example compactor.
 */
class RemoveWhitespace extends Compactor
{
    /**
     * The default supported file extensions.
     *
     * @var array
     */
     protected $extensions = array('php');

    /**
     * Seek and destroy (whitespaces).
     *
     * @param string $source The source code.
     *
     * @return string The compacted source code.
     */
    public function compact($source)
    {
        return preg_replace('/[ \t]+$/m', '', $source);
    }
}
```

Developers can later change the supported file extensions by calling the
`Compactor->setExtensions()` method:

```php
$example = new Example\Compactor\RemoveWhitespace();

$example->setExtensions(
    array(
        'inc',
        'php'
    )
);
```

#### Bundled Compactors

The library has two compactors bundled for your convenience.

##### Compacting JavaScript

The `JavaScript` compactor will minify JavaScript files, but requires the
[`tedivm/jshrink`][] packages to work. This is included when you install
the Box library.

```php
use Herrera\Box\Compactor\Javascript;

$compactor = new Javascript();
```

##### Compacting JSON

The `JSON` compactor is very simple to use as there are no options to
configure. However, the `json` extension is required to use it. All extra
whitespace is removed from `.json` files.

```php
use Herrera\Box\Compactor\Json;

$compactor = new Json();
```

##### Compacting PHP

The `PHP` compactor will strip all comments whitespace from `.php` files.
Comments that are removed will be removed with an the same number of line
breaks as the original comment. This is done in order to preserve the line
number that is reported when errors occur in the phar.

```php
use Herrera\Box\Compactor\Php;

$compactor = new Php();
```

If you make use of Doctrine formatted annotations, you can also make use
of a special feature within the `Php` compactor. To compact comments and
preserve annotations, you will need to install the [`herrera-io/annotations`][]
library and create an instance of `Tokenizer`.

```php
use Herrera\Annotations\Tokenizer;

$compactor->setTokenizer(new Tokenizer());
```

Both line count and annotation data is preserved.

### Managing Signatures

The `Phar` class provides an easy way of extracting and verifying a phar's
signature. Simply instantiating the class will verify the phar in question.
However, the `phar` extension is required to do either task. The Box library
includes a way to extract and verify signatures without the use of the
extension.

```php
use Herrera\Box\Exception\SignatureException;
use Herrera\Box\Signature;

$sig = new Signature('/path/to/my.phar');


// same output as Phar->getSignature()
$signature = $sig->get();

try {
    // TRUE if passed, FALSE if failed
    $result = $sig->verify();
} catch (SignatureException $exception) {
    // the signature could not be verified
}
```

The `Signature::create()` method is an alias to `Signature::__construct()`
which allows for a shorthand version of the above example:

```php
if (Signature::create('/path/to/my.phar')->verify()) {
    // do the do
}
```

The purpose of being able to verify a phar without having the extension
available is more prudent in nature. In sensitive environments without the
extension available, a dev or sys admin may want to verify the integrity of
a phar they are using before making it available on the system.

### Extracting Phars

In addition to being able to verify a phar's signature without the extension,
you can also extract its contents. This feature is primarily designed to be
embedded as part of a custom stub, but it can also be used to extract any
phar.

```php
use Herrera\Box\Extract;

$extract = new Extract('/path/to/my.phar', 65538);

$extract->go('/path/to/extract/dir');
```

The first argument for the constructor is the path to the existing phar. The
second being the length of the stub. This second argument is required in order
for the `Extract` class to know where the phar's manifest begins. Usually, this
value is generated by the `Phar` class when the default stub is used.

If the value is unknown, the `Extract` class can be used to make a best guess
effort by calling the `Extract::findStubLength()` method. If the stub length
is incorrectly guessed, the `Extract` class will thrown an exception at some
point during the extraction process.

By default, the `Extract->go()` method will create a temporary directory path
and extract the contents of the phar there. The directory path specified in
the example is optional.

In order to reduce overhead, the `Extract` class will not re-extract the phar
if a special file exists in the target directory. This is used to speed up the
execution process for phars that were executed without the phar extension.

Note that if any of the files within the phar were compressed using either
gzip or bzip2, their respective extensions will be required for decompression.
If the required extension is not installed, the `Extract` class will throw an
exception.

### Generating Stubs

If appropriate for the project, a custom stub can be generated by the Box
library. You will have control over the following functions in the stub:

- Setting an alias.
- Setting a "banner" comment (such as a copyright statement).
- Embed the `Extract` class to support self-extraction.
- Setting the CLI index script.
- Enabling the use of `Phar::interceptFileFuncs()`.
- Setting the file mimetypes.
- Setting the list of server variables to "mung".
- Setting the 404 script.
- Setting the "shebang" line.
- Opting the user of `Phar::webPhar()` over `Phar::mapPhar()`.

The following is an example of a stub generated with all of the settings used:

```php
use Herrera\Box\StubGenerator;

$generator = new StubGenerator();

$banner = <<<BANNER
Copyright (c) 2013 Some Dude

Some license text here.
BANNER;

$mimetypes = array(
    'phps' => Phar::PHP
);

$rewrite = <<<REWRITE
function rewrite_url(\$uri)
{
    return \$rewritten;
}
REWRITE;

$stub = $generator
            ->alias('test.phar')
            ->banner($banner)
            ->extract(true)
            ->index('bin/cli.php')
            ->intercept(true)
            ->mimetypes($mimetypes)
            ->mung(array('REQUEST_URI'))
            ->notFound('lib/404.php')
            ->rewrite($rewrite)
            ->shebang('/home/dude/.local/php/bin/php')
            ->web(true)
            ->generate();
```

And the resulting stub:

```php
<?php
/**
 * Copyright (c) 2013 Some Dude
 *
 * Some license text here.
 */
define('BOX_EXTRACT_PATTERN_DEFAULT', '__HALT' . '_COMPILER(); ?>');
define('BOX_EXTRACT_PATTERN_OPEN', "__HALT" . "_COMPILER(); ?>\r\n");
if (class_exists('Phar')) {
Phar::webPhar('test.phar', 'bin/cli.php', 'lib/404.php', array (
  'phps' => 0,
), 'function rewrite_url($uri)
{
    return $rewritten;
}');
Phar::interceptFileFuncs();
Phar::mungServer(array (
  0 => 'REQUEST_URI',
));
} else {
$extract = new Extract(__FILE__, Extract::findStubLength(__FILE__));
$dir = $extract->go();
set_include_path($dir . PATH_SEPARATOR . get_include_path());
require "$dir/bin/cli.php";
}
// ... snip ...

__HALT_COMPILER();
```

> For the sake of brevity, the embedded `Extract` class was replaced with
> "... snip ...".

The example stub is likely overkill for what you need. By not using the
`extract()` method, you can easily shave a few hundred lines of code from
your stub, reducing its size, but you will lose the ability to execute the
phar in an environment without the `phar` extension.

### Finally, Building Phars

All these features are great, but they're even better when used together in
the `Box` class. The `Box` class is designed to automatically integrate all
of these features in a (hopefully) simple to use interface.

There are two ways of instantiating the class:

```php
user Herrera\Box\Box;

// use an existing Phar instance
$box = new Box($phar);

// or create one
$box = Box::create('/path/to/my.phar');
```

> Note that the `Box::create()` method accepts the same arguments as the
> `Phar::__construct()` method.

#### Registering Compactors

Whether you are using the bundled compactors or your own, you will need to
call the `Box->addCompactor()` method to register your class with `Box`.
All files added to the phar using `Box` will be automatically run through
the supported compactors.

```php
use Herrera\Box\Compactor\Json;
use Herrera\Box\Compactor\Php;

$box->addCompactor(new Json());
$box->addCompactor(new Php());
$box->addCompactor($custom);
```

#### Using Placeholder Values

The `Box` class offers the ability to search and replace placeholder values
in added files. Keep in mind that only scalar values are supported for any
replacements.

```php
$box->setValues(
    array(
        'match' => 'replace'
    )
);
```

With the above value to match, the following code:

```php
$myCode = 'This @match@ is now "replace".';
```

will be added to the phar as:

```php
$myCode = 'This replace is now "replace".';
```

#### Adding Files

To actually make use of the registered compactors and set placeholder value
replacements, you will need to use the `Box` class's methods for adding files.
The methods are identical to that of the `Phar` class, but automatically apply
the appropriate compactors and replacements:

- `Box->addFile()`
- `Box->addFromString()`
- `Box->buildFromDirectory()`
- `Box->buildFromIterator()`

Note that if you need you need to add a file without any alterations (such as
a binary file), you may need to add the file directly using the `Phar` instance:

```php
$phar = $box->getPhar();

$phar->addFile('...');
```

#### Setting the Stub

The `Box` class offers a simple way of adding a stub sourced from a file, and
also applying the placeholder replacements at the same time:

```php
$box->setStubUsingFile('/path/to/stub.php', true);
```

The second argument indicates that replacements should be performed on the
stub. If you leave it out, it will default to `false` which means that no
replacements will be performed and the stub will be used as is.

#### Private Key Signing

The `Box` class offers two simple ways of signing the phar using a private
key. Either method will, however, require the availability of the `openssl`
extension.

##### With a String

If you have already loaded the private key as a string variable, you can use
the `Box->sign()` method.

```php
$box->sign($key, $pass);
```

The password is only required if the key was generated with a password.

##### With a File

You can also sign the phar using a private key contained with a file.

```php
$box->signUsingFile($file, $pass);
```

[Build Status]: https://travis-ci.org/box-project/box2-lib.png?branch=master
[`Phar`]: http://us3.php.net/manual/en/class.phar.php
[`CompactorInterface`]: src/lib/Compactor/CompactorInterface.php
[`tedivm/jshrink`]: https://github.com/tedious/JShrink
[`herrera-io/annotations`]: https://github.com/herrera-io/php-annotations
