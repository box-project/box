.DEFAULT_GOAL := help


help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Commands
##---------------------------------------------------------------------------

clean:		## Clean all created artifacts
clean:
	git clean --exclude=.idea/ -ffdx

cs:		## Fix CS
cs: vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
	php -d zend.enable_gc=0 vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer fix

compile:	## Compile the application into the PHAR
compile:
	# Cleanup existing artefacts
	rm -f bin/box.phar

	# Build the PHAR
	php -d phar.readonly=0 bin/box compile $(args)


##
## Tests
##---------------------------------------------------------------------------

test:		## Run all the tests
test: tu e2e

tu:		## Run the unit tests
tu: vendor/bin/phpunit fixtures/default_stub.php
	php -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit

tc:		## Run the unit tests with code coverage
tc: vendor/bin/phpunit
	phpdbg -qrr -d phar.readonly=0 -d zend.enable_gc=0 bin/phpunit --coverage-html=dist/coverage --coverage-text

tm:		## Run Infection
tm:	vendor/bin/phpunit fixtures/default_stub.php
	php -d phar.readonly=0 -d zend.enable_gc=0 bin/infection

e2e:		## Run the end-to-end tests
e2e: box_dev.json
	$(MAKE) compile args='--config=box_dev.json'

	rm box.phar || true
	mv -v bin/box.phar .

	# TODO: use the build step again otherwise it is going to include the dev files
	php -d phar.readonly=0 box.phar compile

	rm box.phar bin/box.phar

blackfire:	## Profiles the compile step
blackfire: bin/box src vendor
	# Cleanup existing artefacts
	rm -f bin/box.phar

	# Re-dump the loader to account for the prefixing
	# and optimize the loader
	composer install
	composer dump-autoload --classmap-authoritative

	# Profile compiling the PHAR from the source code
	blackfire --reference=1 --samples=5 run php -d zend.enable_gc=0 -d phar.readonly=0 bin/box compile --quiet

	# Profile compiling the PHAR from the PHAR
	mv -fv bin/box.phar .
	blackfire --reference=2 --samples=5 run php -d zend.enable_gc=0 -d phar.readonly=0 box.phar compile --quiet

	# Cleanup
	composer install
	rm box.phar


##
## Rules from files
##---------------------------------------------------------------------------

composer.lock: composer.json
	composer install

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install

vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer: vendor/bamarni
	composer bin php-cs-fixer install

bin/box.phar: bin/box src vendor
	$(MAKE) compile

box_dev.json: box.json.dist
	cat box.json.dist | sed -E 's/\"key\": \".+\",//g' | sed -E 's/\"algorithm\": \".+\",//g' | sed -E 's/\"alias\": \".+\",//g' > box_dev.json

fixtures/default_stub.php:
	bin/generate_default_stub
