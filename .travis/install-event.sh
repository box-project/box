#!/usr/bin/env bash

#
# Credits: https://github.com/amphp/amp/blob/8283532/travis/install-event.sh
#

curl -LS https://pecl.php.net/get/event-2.3.0 | tar -xz \
 && pushd event-* \
 && phpize \
 && ./configure --with-event-core --with-event-extra --with-event-pthreads \
 && make \
 && make install \
 && popd \
 && echo "extension=event.so" >> "$(php -r 'echo php_ini_loaded_file();')";
