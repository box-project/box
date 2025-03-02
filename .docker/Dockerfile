FROM php:8.2-cli-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions zlib phar sodium tokenizer filter

# Include Composer as it is required for dumping the autoloader when no external Composer bin is provided.
COPY --from=composer/composer /composer /usr/bin/composer

COPY bin/box.phar /box.phar

# Adjusts PHP settings
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "phar.readonly=Off" > /usr/local/etc/php/conf.d/phar-readonly.ini

RUN mkdir -p /local
WORKDIR /local
ENTRYPOINT ["/box.phar"]
