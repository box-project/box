.DEFAULT_GOAL := help


help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Tests
##---------------------------------------------------------------------------

test:		## Run all the tests
test: vendor/bin/phpunit
	php -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit

tc: vendor/bin/phpunit
	phpdbg -qrr -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit --coverage-html=dist/coverage --coverage-text


##
## Rules from files
##---------------------------------------------------------------------------

vendor: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install
