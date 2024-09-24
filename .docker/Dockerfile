FROM php:8.2-cli-alpine

# hadolint ignore=DL3022
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions zlib phar sodium tokenizer filter

# hadolint ignore=DL3022
COPY --chmod=755 --from=composer/composer:2-bin /composer /usr/bin/composer

COPY bin/box.phar /box.phar

ENTRYPOINT ["/box.phar"]
