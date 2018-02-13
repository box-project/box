## From box2 to 3.0

Migration path from [`kherge/box`][box2] to `humbug/box ^3.0`.
 
 
### Backward-compatibility (BC) breaks

- The option `configuration|c` of the command `build` has been changed for `config|c`
- Remove support for PHAR used for web purposes, which translates to the removal of the following elements of the
  `box.json.dist` configuration: `mimetypes`, `mung`, `not-found` and `web`.


<br />
<hr />


« [Table of Contents](README.md#table-of-contents) »


[box2]: https://github.com/box-project/box2
