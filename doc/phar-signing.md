# PHAR signing best practices

1. [Built-in PHAR API](#built-in-phar-api)
    1. [How to sign your PHAR](#how-to-sign-your-phar)
    1. [How it works](#how-it-works)
    1. [Why it is bad](#why-it-is-bad)
1. [How to (properly) sign your PHAR](#how-to-properly-sign-your-phar)
    1. [Create a new GPG-key](#create-a-new-gpg-key)
    1. [Manually signing](#manually-signing)
    1. [Generate the encryption key](#generate-the-encryption-key)
    1. [Secure your encryption key](#secure-your-encryption-key)
    1. [Sign your PHAR](#sign-your-phar)
    1. [Verifying the PHAR signature](#verifying-the-phar-signature)
1. [Automatically sign in GitHub Actions](#automatically-sign-in-github-actions)

There is two idiomatic ways to secure a PHAR:

- Using the built-in PHAR signing API (**not** recommended; read [Why it is bad](#why-it-is-bad) for the why).
- Signing the PHAR as any other generic binary (see [Sign your PHAR](#sign-your-phar)).

This doc entry goal is to show why the first method is to be avoided and how to do it "the right way".


## Built-in PHAR API

### How to sign your PHAR

This is how a PHAR can be signed:

```php
// See https://www.php.net/manual/en/phar.setsignaturealgorithm.php
$phar->setSignatureAlgorithm($algo, $privateKey);
```

There is various algorithm available. The most "secure" one would be `Phar::OPENSSL` with an
OpenSSL private key. For instance:

```shell
openssl genrsa -des3 -out acme-phar-private.pem 4096
```

```php
// E.g. $privateKeyPath = 'acme-phar-private.pem' with the example above
$privateKey = file_get_contents($privateKeyPath);

$resource = openssl_pkey_get_private($key, $privateKeyPassword);
openssl_pkey_export($resource, $private);
$details = openssl_pkey_get_details($resource);

$phar->setSignatureAlgorithm(Phar::OPENSSL, $private);

file_put_contents(
    $phar->getPath().'.pubkey',
    $details['key'],
);
```

With the example above, you will end up with two files: your PHAR (e.g. `bin/command.phar`) and its
public key (e.g. `bin/command.phar.pubkey`).


### How it works

To give more background on how PHAR archives are constructed, they are PHP files that contain a mixture of code and
other binary data. It is analogous to the [JAR files][jar], and will require PHP to execute the PHARs. The content
of a PHAR is as follows:

1. A stub: a piece of code that handles the extraction of resources.
1. A binary manifest which allows the interpreter to understand the file structure of the embedded contents that follow.
1. The actual content of the archive.
1. The signature makes up the last section of the file. It is a 4-byte signature flag which tells what signature type
   was used and then another 4-byte constant marks the file as having a signature.

When PHP later reads the archive, it can determine the signature and type by reading the end of the archive. This way,
if the content of the PHAR has been tempered with, e.g. code was injected in the archive, PHP will see that the content
of the archive does not match the signature of the PHAR and will bail out.


### Why it is bad

There is a few downsides from this signing mechanisms:

- You cannot run the PHAR without its associated public key file laying right next to it. As a result, if you were to
  move your PHAR under `/usr/local/bin`, the PHAR would no longer work due to the missing public key file.
- OpenSSL keys do not contain any identity information. So unless cleanly separated at distribution time, nobody knows
  where the pub key came from or who generated it. Which (almost) kills the very idea of signing things.

The real problem is the signature check itself. If the PHAR gets corrupted, maybe the signature got corrupted too. So
there is ways to void the signature:

- Injects code _before_ the stub, then this code will be executed before the signature check. The signature check can
  still fail if the signature was not adjusted, but this might be too late.
- Replace the signature used. An OpenSSL one will only make it slightly harder as this requires to change an external
  file (the public key), but in the context the attacker could inject code to the PHAR this is unlikely to be a real
  prevention measure.
- The entire signature check can be disabled via the [PHP ini setting `phar.require_hash`][phar-require-hash].

So to conclude, **this security mechanism CANNOT prevent modifications of the archive itself.** It is **NOT** a reliable
protection measure. It is merely a measure to prevent accidentally running a corrupted PHAR.

The good news, there is a solution.


## How to (properly) sign your PHAR

### Create a new GPG-key

The first step is to create a new GPG-key. You can either do that via a GUI or via the CLI like this:

```shell
gpg --gen-key
```

It will ask for some questions. It is recommended to use a passphrase (ideally generated and managed by a reputable
password manager). In the end, you will end up with something like this:

```shell
# $ gpg --gen-key output
pub   ed25519 2023-10-21 [SC] [expires: 2026-10-20]
      96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08
uid                      Théo Fidry <theo.fidry+phar-signing-example@example.com>
sub   cv25519 2023-10-21 [E] [expires: 2026-10-20]
```

In this case the interesting part is `96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08` which is the key ID. You can also check
the list of your GPG keys like so:

```shell
gpg --list-secret-keys --keyid-format=long

#
# Other keys displayed too
#
sec   ed25519/03B2F4DF7A20DF08 2023-10-21 [SC] [expires: 2026-10-20]
      96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08
uid                 [ultimate] Théo Fidry <theo.fidry+phar-signing-example@example.com>
ssb   cv25519/765C0E3CCBC7D7D3 2023-10-21 [E] [expires: 2026-10-20]
```

Like above, you see the key ID `96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08`.

To make the key accessible for others we should now send it to a keyserver[^1].

```shell
gpg --keyserver keys.openpgp.org --send-key 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08
```

[^1]:

    There is several OpenPGP Keyservers. It is recommended to push your keys to [keys.openpgp.org] _at least_, but you
    can also push it to other servers if you wish to.

You can also already generate a revocation certificate for the key. Should the key be compromised you can then send the
revocation certificate to the keyserver to invalidate the signing key.

```shell
gpg --output revoke-96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08.asc --gen-revoke 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08
```

This will leave you with a revocation certificate in the file `revoke-96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08.asc`
which can be added to your password manager.


### Manually signing

For manually signing your PHAR (or any file actually), you will need to have an key containing both your public and
private GPG key.


### Generate the encryption key

In order to use the key to encrypt files, you need to first export it:

```shell
gpg --export --armor 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08 > keys.asc
gpg --export-secret-key --armor 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08 >> keys.asc
```

!!! warning

    That will leave the public and private key in a single file. Anyone that has that file can sign on your behalf! So keep
    that file secure at all times and make sure it never accidentally shows up in your git repository.


### Secure your encryption key

If your goal is to save this encryption key somewhere, for example your repository, you should first encrypt it:

```shell
gpg --symmetric keys.asc
```

This will ask for a second passphrase. It is recommended to **pick a different passphrase** than for the key itself and
ideally one generated and managed by a password manager.

This leaves you with a file `keys.asc.gpg`. You can add this one to the repository and at this point you are probably
better off **deleting the `keys.asc` file**. In order to do the actual signing, you will have to decrypt it again, but
it is better to not keep that decrypted key around.


### Sign your PHAR

You first need to encrypt `keys.asc.gpg` into `keys.asc`:

```shell
# If you are locally:
gpg keys.asc.gpg
# In another environment: CI or other. You should use an environment variable
# or a temporary file to avoid printing the password in clear text.
echo $DECRYPT_KEY_PASSPHRASE | gpg --passphrase-fd 0 keys.asc.gpg
# or:
cat $(.decrypt-key-passphrase) | gpg --passphrase-fd 0 keys.asc.gpg
```

Import the decrypted key if it is not already present on the machine:

```shell
gpg --batch --yes --import keys.asc
```

Sign your file:

```shell
gpg \
   --batch \
   --passphrase="$GPG_KEY_96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08_PASSPHRASE" \
   --local-user 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08 \
   --armor \
   --detach-sign \
   bin/command.phar
# Do not forget to remove keys.asc afterwards!
```

You will now have a file `bin/command.phar.asc`.

When publishing your archive, you should publish both `bin/command.phar` and `bin/command.phar.asc`.


### Verifying the PHAR signature

First you should check the issuer's identity, usually it is provided from where you download it as part of the
documentation:

```shell
# If you are on the same machine as where you created the key, then this step is unnecessary.
# You will need this however for when verifying a different key that you do not know of yet.
gpg --keyserver hkps://keys.openpgp.org --recv-keys 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08
```

However not everyone exposes what is their GPG key ID. So sometimes to avoid bad surprises, you
can look up for similar issuers to the key ID given by the `.asc`:

```shell
# Verify the signature
gpg --verify bin/command.phar.asc bin/command.phar

# Example of output:
gpg: Signature made Sat 21 Oct 16:58:05 2023 CEST
gpg:                using EDDSA key 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08
gpg: Good signature from "Théo Fidry <theo.fidry+phar-signing-example@example.com>" [ultimate]
```

If the key ID was not provided before, you can try to look it up to check it was properly registered to a keyserver:

```shell
gpg --keyserver https://keys.openpgp.org --search-keys "theo.fidry+phar-signing-example@example.com"
```

!!! info

    Also note that when dealing with PHARs, the above steps are automatically done for you by [PHIVE][phive].


## Automatically sign in GitHub Actions

The first step is to add [environment secrets to your repository][github-environment-secrets]:

```shell
gpg --export-secret-key --armor 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08
# Paste the content into a secret environment variable
GPG_KEY_96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08

# Add the corresponding passphrase enviroment variable:
GPG_KEY_96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08_PASSPHRASE
```

Then you need to:

- Build your PHAR
- Import the GPG key
- Sign your PHAR
- Publish your PHAR

I highly recommend to build your PHAR as part of your regular workflows. Then the other steps can be enable on release
only. The following is an example of [GitHub workflow][github-workflow]:

```yaml
# .github/workflows/release.yaml
name: Release

on:
    push:
        branches: [ main ]
    pull_request: ~
    schedule:
        # Do not make it the first of the month and/or midnight since it is a very busy time
        - cron: "* 10 6 * *"
    release:
        types: [ created ]

# See https://stackoverflow.com/a/72408109
concurrency:
    group: ${{ github.workflow }}-${{ github.event.pull_request.number || github.ref }}
    cancel-in-progress: true

jobs:
    build-phar:
        runs-on: ubuntu-latest
        name: Build PHAR
        steps:
            -   name: Checkout
                uses: actions/checkout@v3
                with:
                    fetch-depth: 0

            -   name: Setup PHP
                uses: shivammathur/setup-php@v2
                with:
                    php-version: '8.1'
                    ini-values: phar.readonly=0
                    tools: composer
                    coverage: none

            -   name: Install Composer dependencies
                uses: ramsey/composer-install@v2

            -   name: Build PHAR
                run: ...

            # Smoke test.
            # It is recommended ot have some sorts of tests for your PHAR.
            -   name: Ensure the PHAR works
                run: bin/command.phar --version

            # The following section is done only for releases
            -   name: Import GPG key
                if: github.event_name == 'release'
                uses: crazy-max/ghaction-import-gpg@v5
                with:
                    gpg_private_key: ${{ secrets.GPG_KEY_96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08 }}
                    passphrase: ${{ secrets.GPG_KEY_96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08_PASSPHRASE }}

            -   name: Sign the PHAR
                if: github.event_name == 'release'
                run: |
                    gpg --local-user 96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08 \
                        --batch \
                        --yes \
                        --passphrase="${{ secrets.GPG_KEY_96C8013A3CC293C465EE3FBB03B2F4DF7A20DF08_PASSPHRASE }}" \
                        --detach-sign \
                        --output bin/command.phar.asc \
                        bin/command.phar

            -   name: Upload PHAR to the release
                uses: softprops/action-gh-release@v1
                with:
                   token: ${{ secrets.GITHUB_TOKEN }}
                   files: |
                      box.phar
                      box.phar.asc
```

A more complete real-life example can be found in the [Box release workflow][box-release-workflow].



<br />
<hr />

« [Reproducible build](reproducible-builds.md#reproducible-builds) • [FAQ](faq.md#faq) »

<hr />

Credits:

- [Andreas Heigl, January 19, 2017, _Encrypt a build-result – automaticaly_](https://andreas.heigl.org/2017/01/19/encrypt-a-build-result-automaticaly/)
- [Arne Blankerts](https://github.com/theseer)
- [Jeff Channell, July 13, 2017, _Code Injection in Signed PHP Archives (Phar)_](https://blog.sucuri.net/2017/07/code-injection-in-phar-signed-php-archives.html)

[box-release-workflow]: https://github.com/box-project/box/blob/main/.github/workflows/release.yaml
[keys.openpgp.org]: https://keys.openpgp.org/about
[github-environment-secrets]: https://docs.github.com/en/actions/security-guides/using-secrets-in-github-actions
[github-workflow]: https://docs.github.com/en/actions/using-workflows
[phar-require-hash]: https://www.php.net/manual/en/phar.configuration.php#ini.phar.require-hash
[phive]: https://phar.io/
[jar]: https://docs.oracle.com/javase/8/docs/technotes/guides/jar/jarGuide.html
