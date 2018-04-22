.DEFAULT_GOAL := help

PHPNOGC=php -d zend.enable_gc=0

.PHONY: help
help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Commands
##---------------------------------------------------------------------------

.PHONY: clean
clean:		## Clean all created artifacts
clean:
	git clean --exclude=.idea/ -ffdx

.PHONY: cs
PHPCSFIXER=vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
cs:		## Fix CS
cs: vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
	$(PHPNOGC) $(PHPCSFIXER) fix

.PHONY: compile
compile:	## Compile the application into the PHAR
compile:
	# Cleanup existing artefacts
	rm -f bin/box.phar

	# Build the PHAR
	php bin/box compile $(args)


##
## Tests
##---------------------------------------------------------------------------

.PHONY: test
test:		## Run all the tests
test: tu e2e

.PHONY: tu
tu:		## Run the unit tests
tu: bin/phpunit fixtures/default_stub.php
	$(PHPNOGC) bin/phpunit

.PHONY: tc
tc:		## Run the unit tests with code coverage
tc: bin/phpunit
	phpdbg -qrr -d zend.enable_gc=0 bin/phpunit --coverage-html=dist/coverage --coverage-text

.PHONY: tm
tm:		## Run Infection
tm:	bin/phpunit fixtures/default_stub.php
	$(PHPNOGC) bin/infection

.PHONY: e2e
e2e:		## Run the end-to-end tests
e2e: e2e_scoper_alias

.PHONY: e2e_scoper_alias
e2e_scoper_alias: 	## Runs the end-to-end tests to check that the PHP-Scoper config API is working
e2e_scoper_alias: box.phar
	php box.phar compile --working-dir fixtures/build/dir010

.PHONY: blackfire
blackfire:	## Profiles the compile step
blackfire: box.phar
	# Profile compiling the PHAR from the source code
	blackfire --reference=1 --samples=5 run $(PHPNOGC) bin/box compile --quiet

	# Profile compiling the PHAR from the PHAR
	mv -fv bin/box.phar .
	blackfire --reference=2 --samples=5 run $(PHPNOGC) box.phar compile --quiet


#
# Rules from files
#---------------------------------------------------------------------------

composer.lock: composer.json
	composer install

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

bin/phpunit: composer.lock
	composer install

vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer: vendor/bamarni
	composer bin php-cs-fixer install

bin/box.phar: bin/box src vendor
	$(MAKE) compile

.PHONY: fixtures/default_stub.php
fixtures/default_stub.php:
	bin/generate_default_stub

box.phar: bin src res vendor box.json.dist scoper.inc.php
	# Compile Box
	bin/box compile

	rm bin/_box.phar || true
	mv -v bin/box.phar bin/_box.phar

	# Compile Box with the isolated Box PHAR
	php bin/_box.phar compile

	rm box.phar || true
	mv -v bin/box.phar .

	# Test the PHAR which has been created by the isolated PHAR
	php box.phar compile

	rm bin/_box.phar
