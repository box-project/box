Add main file -> register main file
exclude dev deps -> remove dev dependencies

Box scripts:
    - dump-composer-autoloader

cp . /tmp/box-build

? Removing the existing PHAR "/Users/tfidry/Project/Humbug/box/bin/box.phar"
? Checking Composer compatibility
    > Supported version detected
? Setting replacement values
  + @release-date@: 2023-12-10 19:02:10 UTC
? Registering compactors
  + KevinGH\Box\Compactor\Php
  + KevinGH\Box\Compactor\PhpScoper
  + KevinGH\Box\Compactor\Json
? Adding main file: /Users/tfidry/Project/Humbug/box/bin/box
? Adding requirements checker
? Adding binary files
    > 35 file(s)
? Auto-discover files? Yes
? Removing dev files | Keeping dev files
? Processing files
    > 2242 file(s)
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using banner:
    > This file is part of the box project.
    >
    > (c) Kevin Herrera <kevin@herrera.io>
    > Théo Fidry <theo.fidry@gmail.com>
    >
    > This source file is subject to the MIT license that is bundled
    > with this source code in the file LICENSE.
? Executing user scripts
  - @composer config autoloader-suffix @prefix'
  - @composer config autoloader-suffix @{prefix}CheckSum
  - @composer config platform.php --unset
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? Compressing with the algorithm "GZ"
    > Warning: the extension "zlib" will now be required to execute the PHAR
? Setting file permissions to 0755
* Done.
