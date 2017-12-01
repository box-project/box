.DEFAULT_GOAL := help


help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Commands
##---------------------------------------------------------------------------

clean:		## Clean all created artifacts
clean:
	git clean --exclude=.idea/ -fdx

cs:		## Fix CS
cs: vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
	php -d zend.enable_gc=0 vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer fix

build:		## Build the PHAR
build: bin/box src vendor
	# Cleanup existing artefacts
	rm -f bin/box.phar

	# Remove unnecessary packages
	composer install --no-dev --prefer-dist

	# Re-dump the loader to account for the prefixing
	# and optimize the loader
	composer dump-autoload --classmap-authoritative --no-dev

	# Build the PHAR
	php -d zend.enable_gc=0 -d phar.readonly=0 bin/box build

	# Install back all the dependencies
	composer install


##
## Tests
##---------------------------------------------------------------------------

test:		## Run all the tests
test: tu test_legacy_lib e2e

test_legacy_lib:## Test the legacy box lib project
test_legacy_lib:
	php -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit -c box-lib/phpunit.xml.dist

tu:		## Run the unit tests
tu: vendor/bin/phpunit
	php -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit

tc:		## Run the unit tests with code coverage
tc: vendor/bin/phpunit
	phpdbg -qrr -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit --coverage-html=dist/coverage --coverage-text

e2e:		## Run the end-to-end tests
e2e: bin/box.phar
	mv -v bin/box.phar .
	php -d phar.readonly=0 box.phar build
	rm box.phar


##
## Rules from files
##---------------------------------------------------------------------------

composer.lock:
	composer update

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install

vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer: vendor/bamarni
	composer bin php-cs-fixer install

bin/box.phar:
	$(MAKE) build