How those files were created:

empty-pdf.pdf: open a text editor and export as PDF.
simple.zip: create a sample.txt file and compress it `$ zip -r`.
simple.tar & simple.tar.{gz|bz2}:

```php
$phar = new PharData('simple.tar');
$phar->addFile('sample.txt');

$phar->compress(Phar::GZ);
// ...
```

simple.tar.phar & simple.tar.{gz|bz2}.phar: copied the tar variant and added ".phar" manually

simple.phar:

```php
$phar = new Phar('simple-phar.phar');
$phar->addFile('sample.php');
```

corrupted-phar-no-halt-compiler.phar: copied `simple-phar.phar` and removed the `__HALT_COMPILER(); ?>` token.
corrupted-simple.zip: copied `simple.zip`, opened it in a text editor and added "3 " at the beginning of the file

empty-file.{zip|phar}: created an empty file and renamed it

simple-phar-openssl-sign.phar: 
- https://www.php.net/manual/en/phar.setsignaturealgorithm.php
- use the key in `openssl-keys`

corrupted-phar-altered-stub.phar: vim the file and add a comment in the stub section
corrupted-phar-altered-binary.phar: open the file in the text editor and add characters after the part identified as an included file
