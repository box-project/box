# Optimize your PHAR

1. [Review your files](#review-your-files)
1. [Compress your PHAR](#compress-your-phar)
1. [Optimize your code](#optimize-your-code)


## Review your files

By default Box try to be smart about which files are required and will attempt to use only the necessary files. You can
list the files of your PHAR with the box `info --list` command. It is however possible you want a finer control in which
case you can adapt the included files thanks to the [configuration options][include-files].

All the files in the PHAR are loaded in-memory when executing a PHAR. As a result, the more content there is to load,
the bigger the overhead will be and unlike your regular application, a PHAR will not benefit from the opcache optimisations.
The difference should however be minimal unless you have dozens of thousands of files in which case you might either
accept it, consider an alternative or contribute to the PHAR extension in order to optimise it.


## Compress your PHAR

You can also greatly enhance the size of your PHAR by compressing it:

- The [compression algorithm setting][compression-algorithm]. It is very efficient, however note that a compressed PHAR
  requires the `zip` PHP extension and has a (micro) overhead since PHP needs to uncompress the archive before using it
- [Compactors][compactors] can also help to compress some contents for example by removing the unnecessary comments and
  spaces in PHP and JSON files.


## Optimize your code

Another code performance optimisation that can be done is always use fully qualified symbols or use statements. For
example the following:

```php
<?php

namespace Acme;

use stdClass;
use const BAR;
use function foo;

new stdClass();
foo(BAR);
```

Will be more performant than:

```php
<?php

namespace Acme;

use stdClass;

new stdClass();
foo(BAR);
```

Indeed in the second case, PHP is unable to know from where `foo` or `BAR` comes from. So it will first try to find
`\Acme\foo` and `\Acme\BAR` and if not found will fallback to `\foo` and `BAR`. This fallback lookup creates a
minor overhead. Besides some functions such as `count` are optimised by opcache so using a fully qualified call
`\count` or importing it via a use statement `use function count` will be even more optimised.

However you may not want to care and change your code for such micro optimisations. But if you do, know that
[isolating your PHAR code][code-isolation] will transform every call into a fully qualified call whenever
possible enabling that optimisation for your PHAR.


<br />
<hr />

« [Requirement Checker](requirement-checker.md#requirements-checker) • [PHAR code isolation][code-isolation] »


[include-files]: configuration.md#including-files
[compression-algorithm]: configuration.md#compression-algorithm-compression
[compactors]: configuration.md#compactors-compactors
[code-isolation]: code-isolation.md#phar-code-isolation
