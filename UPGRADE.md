# Upgrade guide

## From 4.0 to 4.1.0

- The requirements checker now logs its output to `stderr` instead of `stdout` per default. Set `BOX_REQUIREMENTS_CHECKER_LOG_TO_STDOUT=1` to restore the old behaviour.


## From 3.x to 4.x

- Bump to PHP 8.1 #613
- Remove the build command (#623): the `build` command has been deprecated since 3.0.0 in favour of `compile`.
- Remove support for legacy compactors (#626). Here are the replacements:
  - `Herrera\Box\Compactor\Json` -> `KevinGH\Box\Compactor\Json`
  - `Herrera\Box\Compactor\Php` -> `KevinGH\Box\Compactor\Php`
- Drop PHP5.3 support for the RequirementChecker - new min is 7.2.4+ (#674). This is to align the project with Composer.
  It is technically possible to restore support for PHP5.3 but requires some work, see
  https://github.com/box-project/box/issues/656#issuecomment-1164471935.
- Require sodium & Remove sodium compat layer (#628)


## From 3.1.3 to 3.2.0

- Changes to the `Php` compactor:
    - Invalid annotations are no longer recognised as annotations:

        ```php
        /**
          * @Annotation ()
          * @Namespaced\ Annotation
          */
        ```

        Will be transformed into:

        ```php
        /**
          * @Annotation
          * @Namespaced
          */
        ```

- The removal of common annotations is enabled by default
- The setting `annotation#ignore` no longer accepts a `string` value, only `string[]` and `null` are allowed
- Upon some annotation parsing failures, the error is thrown to the user in order to identify and fix those cases
  instead of always silently ignore the error.
- Annotations can no longer be escaped like so:

    ```php
    /**
      * \@NotEscaped
      */
    ```

    Indeed it will be compacted to:

    ```php
    /**
    @NotEscaped
    */
    ```


## From 2.7 to 3.0

The change from 2.x to 3.x is quite significant but should be really smooth for the user. The main changes are:

- Box is more verbose and provides more useful information during the compilation
- Box is _significantly_ faster (+200%!)
- The configuration is optional
- Box can figure out itself which files to include
- Automatically remove the dev dependencies
- No longer requires a `phar.readonly` or `ulimit` setting change from the user
- [Allows to scope the PHAR](doc/code-isolation.md#phar-code-isolation)
- [Allows to ship with a requirements checker](doc/requirement-checker.md#requirements-checker)


A few more features landed as well and a range of settings were added more, the whole list of BC breaks can be found
bellow.


## From 3.0.0-alpha.1 to 3.0.0-beta.2

- There is some possible BC breaks in how the PHAR is being scoped due to PHP-Scoper introducing a couple of BC breaks (cf. PHP-Scoper 0.8.0 release notes) (#255)

## From 3.0.0-alpha.7 to 3.0.0-beta.0

- The datetime value used for the datetime placeholder is now always in UTC (#245)
- The settings `datetime-format` and `datetime_format` are now always evaluated even if the `datetime` setting is not used or null (#245)
- The settings `datetime-format` and `datetime_format` now throw an exception when the format is invalid (#245)
- Disabling the shebang requires to set `shebang` to `false` instead of `null` (#251)
- Disabling the banner requires to set `banner` to `false` instead of `null` (#251)


## From 3.0.0-alpha.6 to 3.0.0-alpha.7

- Add missing doc elements and doc related tests (#240)
- Skip the tests when they require `phar.readonly` off but it is on (#241)


## From 3.0.0-alpha.5 to 3.0.0-alpha.6

- The `Json` compactor now also attempts to compress `.lock` files (e.g. the `composer.lock`) (#228)
- Remove the config JSON imports support (#237)


## From 3.0.0-alpha.3 to 3.0.0-alpha.4

- Remove the JS minifier compactor (#173)


## From 3.0.0-alpha.2 to 3.0.0-alpha.3

- Ignore the symlinks in the `vendor` directory (#157)


## From 3.0.0-alpha.1 to 3.0.0-alpha.2

- Change the default PHAR output from `default.phar` to `index.phar` (#127)
- When no configuration is provided or when the setting `main` is omitted, the value found in `composer.json#bin` will have the priority
  over the default `index.php` (#127)
- When no configuraiton is provided or when the setting `output` is omitted, the default value will depend in the `input` value as opposed
  to systematically `default.phar` (#127)


## From 3.0.0-alpha.0 to 3.0.0-alpha.1

### Backward-compatibility (BC) breaks

- Remove support for the `bootstrap` setting (#88)
- The option `compactors` no longer accepts a string value (#89)
- No longer accept `number` for `algorithm` (#89)
- No longer accept `integer` for `compression` (#89)
- The command `info` no longer supports ZIP & TAR based PHARs (#93)
- When using the `PhpScoper` compactor the `scoper-autoload.php` file is no longer dumped. Instead the whitelist statements are directly
  appended to the existing autoloader which avoids nay extra work for the user. (#94)


## From box2 to 3.0.0-alpha.0

Migration path from [`kherge/box`][box2] to `humbug/box ^3.0`.


### Backward-compatibility (BC) breaks

- Moved Box2 under the Humbug umbrella:
    - Change of project
    - The new Composer package is now `humbug/box`
- The minimal new PHP version is 7.1
- Bump the minimal Symfony dependencies from 3.0 to 3.4
- Dropped the following commands:
    - `add`
    - `remove`
    - `extract`
    - `key:create`
    - `key:extract`
- Process the configuration when loading it instead of lazily processing it
- Dropped the following deprecated packages:
    - `kherge/amend` which has been replaced by `padraic/phar-updater`
    - `phine/path`
    - `herrora-io/json`
    - `herrora-io/phpunit-test-case`
- Move the `Herrora\Box` namespace to `KevinGH\Box`
- The option `configuration|c` of the command `build` has been changed for `config|c`
- Remove support for PHAR used for web purposes, which translates to the removal of the following elements of the
  `box.json.dist` configuration: `mimetypes`, `mung`, `not-found` and `web`.
- The entry `shebang` in `box.json.dist` no longer accept a boolean value or an empty string. To remove the shebang line
  the value `null` should be provided.
- The output directory specified by `output` is now relative to the base path (`base-path`)
- No longer support git version placeholders in the output path (`output`)
- The default main script has been changed to `index.php`
- The main script is now required
- Do not allow a string value for the blacklist (`blacklist`) anymore
- Remove usage of global Box constants: `BOX_PATH`, `BOX_SCHEMA_FILE`, `BOX_EXTRACT_PATTERN_OPEN`
- Remove support for web PHARs
- Remove support for extractable PHARs
- Make the main script path relative to the base path
- Do not allow a config with no file registered
- Make main script mandatory (the value, not the setting)
- Exclude symlinks from the files collected


<br />
<hr />


« [Table of Contents](README.md#table-of-contents) »


[box2]: https://github.com/box-project/box2
