FROM --platform=linux/amd64 php:8.3-cli-alpine AS build-stage

RUN apk add --update make git

# hadolint ignore=DL3022
COPY --chmod=755 --from=composer/composer:2-bin /composer /usr/bin/composer

RUN mkdir -p /opt/box-project/box
WORKDIR /opt/box-project/box
ADD . /opt/box-project/box
RUN make compile

FROM --platform=linux/amd64 php:8.3-cli-alpine

# hadolint ignore=DL3022
COPY --chmod=755 --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions zlib phar sodium tokenizer filter intl

COPY --chmod=755 --from=build-stage /opt/box-project/box/bin/box.phar /usr/bin/box
# hadolint ignore=DL3022
COPY --chmod=755 --from=composer/composer:2-bin /composer /usr/bin/composer

RUN mkdir -p /local
WORKDIR /local
ENTRYPOINT ["/usr/bin/box"]
