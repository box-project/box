## Upgrade Guide

The project provides an [Upgrade Guide](https://github.com/box-project/box/blob/master/UPGRADE.md) to easily
move to a new version.


## Contributing

The project provides a `Makefile` in which the most common commands have been registered such as fixing the coding
style or running the test.

```bash
make
```


## Backward Compatibility Promise (BCP)

The policy is for the major part following the same as [Symfony's one][symfony-bc-policy]. Note that the code marked
as `@private` or `@internal` are excluded from the BCP.

The text displayed by the commands (e.g. `compile` or `info`) or the content of the error/exception messages are also not subject to the BCP.


## Credits

Project originally created by: [Kevin Herrera][kherge] which has now been moved under the [Humbug umbrella][humbug].


## License

The project is release under the [MIT License][MIT]


## Sponsorship

You can support this project via [Github Sponsorship][sponsor]


[kherge]: https://github.com/kherge
[humbug]: https://github.com/humbug
[symfony-bc-policy]: https://symfony.com/doc/current/contributing/code/bc.html
[MIT]: https://github.com/box-project/box/blob/master/LICENSE
[sponsor]: https://github.com/sponsors/theofidry