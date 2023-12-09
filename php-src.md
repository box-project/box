# Document to write ideas of what should be in PHP src

## Extension name

From PackageInfo / Extension

// Some extensions name differs in how they are registered in composer.json
// and the name used when doing a `extension_loaded()` check.
// See https://github.com/box-project/box/issues/653.
private const EXTENSION_NAME_MAP = [
    'zend-opcache' => 'zend opcache',
];


## More compression algorithms


## Deprecate Metadata


## Get Manifest

## Default hash algorithm

## Deprecate OpenSSL signing

## (unrelated) Propose ::create(...) or ::__construct(...) or Foo(...) (i.e. the classname)

## PharUtils::setTimestamp()

