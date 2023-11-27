# PHAR code isolation

1. [Why/Explanation](#whyexplanation)
1. [Isolating the PHAR](#isolating-the-phar)
1. [Debugging the scoping](#debugging-the-scoping)


## Why/Explanation

When bundling the code in a PHAR, it is equivalent to compacting all the code in a single file. However unlike in a
compiled language, the code does not change. This, when the PHAR _loads_ external code, can lead to dependency
conflicts.

To illustrate that issue with an example: we are building a console application `myapp.phar` which relies on the library
Symfony YAML 2.8.0 which execute a given PHP script.

```shell
# Usage of the application we are building
myapp.phar myscript.php
```

For the sake of the example, `myapp.phar` is using Composer and loads the YAML component right away when starting, i.e.
when running `myapp.phar`, the class `Symfony\Yaml\Yaml` _from the PHAR_ is going to be loaded. Now what `myapp.phar`
is actually going to do is scan the whole file given, and do some reflection work on each classes found. I.e. for each
class `$class` found, it will do `new \ReflectionClass($class)`.

Now if `myscript.php` is using the Symfony YAML 4.0.0 component with some new features added in 4.0.0 that are
non-existent in 2.8.0, when doing `new \ReflectionClass('Symfony\Yaml\Yaml')`, the class `Symfony\Yaml\Yaml` will be
loaded (yes, doing reflection on a class loads it!). BUT, it turns out the class `Symfony\Yaml\Yaml` is _already_
loaded: not the 4.0.0 from `myscript.php` but the one from the PHAR (`2.8.0`). **So any information you will get will
be from the wrong class!**

Is this really an issue? The answer is it depends. Here as a few real life example where this is an issue:

- A static analysis tool like [PHPStan][phpstan]
- A test framework like [PHPUnit][phpunit]
- A quality analysis tool like [SymfonyInsight][symfony-insight] which executes arbitrary code (e.g. to check)
  that the application is booting
- A piece of code that can be mixed with any code, such as a Wordpress plugin


## Isolating the PHAR

Box provides an integration with [PHP-Scoper][php-scoper]. To use it, [enable the `KevinGH\Box\Compactor\PhpScoper`
compactor][php-scoper-compactor].

If you need an extra configuration for PHP-Scoper, you can create a `scoper.inc.php` file as
[per the documentation][php-scoper-config]. The only difference is that you can ignore the `finders` setting as the
files to scope are already collected by Box.

And that's it!

Warning: keep in mind however that scoping is a very brittle process due to the nature of PHP. As such you will likely
need some adjustments in your code or the configuration.


## Debugging the scoping

As mentioned above, unless you have a very boring and predictable code (which is a good thing!), due to how dynamic
PHP is, scoping will almost guaranteed never work on the first and will require adjustments. To help with the process,
there is two recommendations:

- Have an end-to-end test for your application. On in which you can easily swap from your regular binary, the PHAR and
  the isolated PHAR. This will help to identify at which test there is an issue besides being able to easily guarantee
  your application, shipped as a PHAR or not, is somewhat working.
- Make use of Box `--debug` option in the `compile` command. It dumps the code added to the PHAR in a `.box-dump`
  directory. This allows you to more easily inspect, alter and test the code shipped in the PHAR. This way, you can
  make sure the code shipped is working before worrying about whether that code is going to work inside a PHAR.
- Use the `process` command on a specific file to check the result and the effects of the configuration on it


<br />
<hr />

« [Optimize your PHAR](optimizations.md#optimize-your-phar) • [Docker support](docker.md#docker-support) »


[phpstan]: https://github.com/phpstan/phpstan
[phpunit]: https://github.com/sebastianbergmann/phpunit
[symfony-insight]: https://insight.symfony.com/
[php-scoper]: https://github.com/humbug/php-scoper
[php-scoper-compactor]: configuration.md#compactors-compactors
[php-scoper-config]: https://github.com/humbug/php-scoper#configuration
