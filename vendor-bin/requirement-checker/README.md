For the requirement checker, the classes are expected to work at versions much lower than reasonable. For this reason
we cannot just use the `symfony/console` package directly, but we need to copy/paste some classes and keep track of it
somehow.

This namespace sole purpose is to keep track of the latest, oldest supported Symfony version of the `symfony/console`
package to compare with the hard copy of the requirement checker.

For those reasons, the current `composer.json` should:

- Be configured at the lowest version supported by the requirement checker
- Reference the oldest Symfony LTS version
