# 3.0.0-alpha.0

### Features

- Allow Symfony 4.x components
- Add missing validation for the compression algorithm `compression` to provide a more user-friendly validation
- Add more information to logs when building a PHAR:
    - Display the configuration file loaded
    - Log if remove the previous PHAR file
    - Log the replacement values
    - Log the registered compactors
    - Log the main file path used
    - Remove the list of files & binary files paths added and log the number of files added for each category instead
    - Log the details of the generated stub or the stub used if a file is given or the
      default stub is used:
        - The shebang line being used
        - The banner used
    - Log the compression algorithm used
    - Log the file permissions used
    - Log the generated PHAR size
    - Log the time & memory taken by the `build` command
- Add a `working-dir|d` option to the `build` command to be able to change the working
  directory from which the build command is executed
- Reduce the number of files added to the PHAR by eliminating the duplicate files upfront
- Process files in parallel
- Allow disabling the shebang line
- Allow to disable the banner
- Allow to build a PHAR without any configuration
- Include all files when no file setting is used
- Exclude dev dependencies when building the PHAR
- Add debug mode which allows to debug the parallel processing or having access to a dump of the files added to the PHAR
- Add a PHP-Scoper integration which allows to isolate the dependencies bundled in the PHAR
- Allow the output PHAR to have a name without the extension `.phar`


### Bugfixes

- Fix Box usage when installed as a global Composer dependency
- Create the necessary directories to create the target
- Fix the usage of the `blackfilter` with absolute path: it now matches the real file paths to know if a file should be excluded or not
- Fix the usage of the `blackfilter` with base paths: the base path is now applied to the files listed in the blacklist filter as well
- `files` and `files-bin` now works with absolute paths
- `files` and `files-bin` now throws a user-friendly error when a file does not exists or is a directory instead of a file
- `directories` and `directories-bin` now works with absolute paths
- `directories` and `directories-bin` now throws a user-friendly error when a directory does not exists or is a file instead of a directory
- Normalize the paths given for the file options when superfluous spaces are used in the paths (trim the path). Applies
  to:
    - `base-path`
    - `blacklist`
    - `files`
    - `files-bin`
    - `directories`
    - `directories-bin`
    - `finder` (as well as the `Finder::append()` and `Finder::exclude()` arguments)
    - `finder-bin` (as well as the `Finder::append()` and `Finder::exclude()` arguments)
- Make main script mandatory (the value, not the setting)
- Normalize the main script path (`main`)
- Add a friendly error message when an invalid symlink is being used for a file and exclude symlinks whenever possible
- Normalize the output path (`output`)


### Misc changes

- Bump the minimal PHP version from 5.3.3 to 7.1
- Bump the minimal Symfony dependencies from 3.0 to 3.4
- Dropped the following commands:
    - `add`
    - `remove`
    - `extract`
    - `key:create`
    - `key:extract`
- Process the configuration when loading it instead of lazily processing it. Also add a friendly error message when the
  configuration is invalid
- Dump files in a temporary directory to build the PHAR from and existing directory instead of adding the files
  processed contents one by one
- Register the main script before the files instead of after
- Remove support for web PHARs
- Remove support for extractable PHARs
- Make the main script path relative to the base path
- Do not allow a config with no file registered
- Configure `index.php` as the default main script
- Rename the command `build` to `compile`


### Project changes


- Moved Box2 under the Humbug umbrella:
    - Change of project
    - The new Composer package is now `humbug/box`
- Re-organise the project in a more standard structure
    - Upgrade autoloading from PSR-0 to PSR-4
    - Move `lib/src` to `src`
    - Move `lib/test` to `tests`
    - Move the `Herrora\Box` namespace to `KevinGH\Box`
- Dropped the following deprecated packages:
    - `kherge/amend` which has been replaced by `padraic/phar-updater`
    - `phine/path`
    - `herrora-io/json`
    - `herrora-io/phpunit-test-case`
- Simplify the usage of the library as a contributor and maintainer by using a `Makefile`
- Update PHPUnit configuration
- Set-up PHP-CS-Fixer and fix the CS
- Upgrade from PHPUnit 3.7 to PHPUnit 7.0
- Add e2e tests
- Add Infection for the tests
- Add Blackfire
- Do not allow a string value for the blacklist anymore
- Remove usage of global Box constants: `BOX_PATH`, `BOX_SCHEMA_FILE`, `BOX_EXTRACT_PATTERN_OPEN`
- Leverage `nikic/iter `instead of custom functions
- Leverage `Assert` for some checks when retrieving the compactors
- Create a dedicated `FileSystem` component
