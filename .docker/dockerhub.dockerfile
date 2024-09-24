FROM --platform=linux/amd64 php:8.2-cli-alpine

# hadolint ignore=DL3022
COPY --chmod=755 --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions zlib phar sodium tokenizer filter

# hadolint ignore=DL3022
COPY --chmod=755 --from=composer/composer:2-bin /composer /usr/bin/composer

COPY --chmod=755 bin/box.phar /box.phar

RUN mkdir -p /local
WORKDIR /local
ENTRYPOINT ["/box.phar"]
