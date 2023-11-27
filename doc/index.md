<style>
  .md-typeset h1,
  .md-content__button {
    display: none;
  }
</style>

![Box logo](img/box.png){: .box-image}

## Goal

The Box application simplifies the PHAR building process. Out of the box (no pun intended), the application can do many
great things:

- ⚡  Fast application bundling
- 🔨 [PHAR isolation](code-isolation.md#phar-code-isolation)
- ⚙️ Zero configuration by default
- 🚔 [Requirements checker](requirement-checker.md#requirements-checker)
- 🚨 Friendly error logging experience
- 🔍 Retrieve information about the PHAR extension or a PHAR file and its contents (`box info` or `box diff`)
- 🔐️ Verify the signature of an existing PHAR (`box verify`)
- 📝 Use Git tags and short commit hashes for versioning
- 🕵️️ Get recommendations and warnings about regarding your configuration (`box validate`)
- 🐳 [Docker support (`box docker`)](docker.md#docker-support)


## Docs

Go do the [documentation](installation.md#installation).


## Usage

Creating a PHAR should be as simple as running `box compile` (**no config required!**). It will however assume some
defaults that you might want to change. Box will by default be looking in order for the files `box.json` and
`box.json.dist` in the current working directory. A basic configuration could be for example changing the PHAR
permissions:

```json
{
    "chmod": "0700"
}
```

You can then find more advanced configuration settings in [the configuration documentation][configuration].
For more information on which command or options is available, you can run:

```shell
box help
```


## Contributing

The project provides a `Makefile` in which the most common commands have been registered such as fixing the coding
style or running the test.

```shell
make
```


## Backward Compatibility Promise (BCP)

The policy is for the major part following the same as [Symfony's one][symfony-bc-policy]. Note that the code marked
as `@private` or `@internal` are excluded from the BCP.

The text displayed by the commands (e.g. `compile` or `info`) or the content of the error/exception messages are also not subject to the BCP.


## Credits

Project originally created by: [Kevin Herrera] ([@kherge]) which has now been moved under the [Humbug umbrella][humbug].


[configuration]: configuration.md
[Kevin Herrera]: https://github.com/kherge
[@kherge]: https://github.com/kherge
[humbug]: https://github.com/humbug
[symfony-bc-policy]: https://symfony.com/doc/current/contributing/code/bc.html

