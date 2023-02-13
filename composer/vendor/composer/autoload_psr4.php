<?php



$vendorDir = dirname(__DIR__);
$baseDir = dirname($vendorDir);

return array(
'Symfony\\Polyfill\\Php81\\' => array($vendorDir . '/symfony/polyfill-php81'),
'Symfony\\Polyfill\\Php80\\' => array($vendorDir . '/symfony/polyfill-php80'),
'Symfony\\Polyfill\\Php73\\' => array($vendorDir . '/symfony/polyfill-php73'),
'Symfony\\Polyfill\\Mbstring\\' => array($vendorDir . '/symfony/polyfill-mbstring'),
'Symfony\\Polyfill\\Intl\\Normalizer\\' => array($vendorDir . '/symfony/polyfill-intl-normalizer'),
'Symfony\\Polyfill\\Intl\\Grapheme\\' => array($vendorDir . '/symfony/polyfill-intl-grapheme'),
'Symfony\\Polyfill\\Ctype\\' => array($vendorDir . '/symfony/polyfill-ctype'),
'Symfony\\Contracts\\Service\\' => array($vendorDir . '/symfony/service-contracts'),
'Symfony\\Component\\String\\' => array($vendorDir . '/symfony/string'),
'Symfony\\Component\\Process\\' => array($vendorDir . '/symfony/process'),
'Symfony\\Component\\Finder\\' => array($vendorDir . '/symfony/finder'),
'Symfony\\Component\\Filesystem\\' => array($vendorDir . '/symfony/filesystem'),
'Symfony\\Component\\Console\\' => array($vendorDir . '/symfony/console'),
'Seld\\Signal\\' => array($vendorDir . '/seld/signal-handler/src'),
'Seld\\PharUtils\\' => array($vendorDir . '/seld/phar-utils/src'),
'Seld\\JsonLint\\' => array($vendorDir . '/seld/jsonlint/src/Seld/JsonLint'),
'React\\Promise\\' => array($vendorDir . '/react/promise/src'),
'Psr\\Log\\' => array($vendorDir . '/psr/log/Psr/Log'),
'Psr\\Container\\' => array($vendorDir . '/psr/container/src'),
'JsonSchema\\' => array($vendorDir . '/justinrainbow/json-schema/src/JsonSchema'),
'Composer\\XdebugHandler\\' => array($vendorDir . '/composer/xdebug-handler/src'),
'Composer\\Spdx\\' => array($vendorDir . '/composer/spdx-licenses/src'),
'Composer\\Semver\\' => array($vendorDir . '/composer/semver/src'),
'Composer\\Pcre\\' => array($vendorDir . '/composer/pcre/src'),
'Composer\\MetadataMinifier\\' => array($vendorDir . '/composer/metadata-minifier/src'),
'Composer\\ClassMapGenerator\\' => array($vendorDir . '/composer/class-map-generator/src'),
'Composer\\CaBundle\\' => array($vendorDir . '/composer/ca-bundle/src'),
'Composer\\' => array($baseDir . '/src/Composer'),
);
