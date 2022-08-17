FROM php:8.1-cli-alpine

ADD releases/box.phar /usr/bin/box

RUN mkdir -p /local
WORKDIR /local
ENTRYPOINT ["/usr/bin/box"]