#!/usr/bin/env php
<?php

if (!class_exists('Phar', false)) {
    dd([
        'get_declared_classes' => get_declared_classes(),
        'phpversion' => phpversion(),
        'get_loaded_extensions' => get_loaded_extensions(),
    ]);
}

echo 'Composer version 2.6.3 2023-09-15 09:38:21';
