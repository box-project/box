.DEFAULT_GOAL := help


help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Tests
##---------------------------------------------------------------------------

test:		## Run all the tests
test: vendor/bin/phpunit
	php -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit


##
## Rules from files
##---------------------------------------------------------------------------

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install
