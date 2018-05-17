# From 2.7 to 3.0

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
