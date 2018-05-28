.DEFAULT_GOAL := help

PHPNOGC=php -d zend.enable_gc=0

.PHONY: help
help:
	@echo "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'


#
# Commands
#---------------------------------------------------------------------------

.PHONY: clean
clean: 	 		 ## Clean all created artifacts
clean:
	git clean --exclude=.idea/ -ffdx

.PHONY: cs
PHPCSFIXER=vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
cs:	 		 ## Fix CS
cs: $(PHPCSFIXER)
	$(PHPNOGC) $(PHPCSFIXER) fix
	$(PHPNOGC) $(PHPCSFIXER) fix --config .php_cs_53.dist

.PHONY: compile
compile: 		 ## Compile the application into the PHAR
compile: box
	cp -f box bin/box.phar

.PHONY: dump-requirement-checker
dump-requirement-checker:## Dumps the requirement checker
dump-requirement-checker: requirement-checker requirement-checker/vendor
	rm rf .requirement-checker || true
	bin/box compile --working-dir requirement-checker

	php bin/dump-requirements-checker.php


#
# Tests
#---------------------------------------------------------------------------

.PHONY: test
test:		  	 ## Run all the tests
test: tu e2e

.PHONY: tu
tu:			 ## Run the unit tests
tu: tu_requirement_checker tu_box

.PHONY: tu_box
tu_box:			 ## Run the unit tests
TU_BOX_DEPS = bin/phpunit fixtures/default_stub.php .requirement-checker fixtures/composer-dump/dir001/vendor
tu_box: $(TU_BOX_DEPS)
	$(PHPNOGC) bin/phpunit

.PHONY: tu_box_phar_readonly
tu_box_phar_readonly: 	## Runs the unit tests with the setting `phar.readonly` to `On`
tu_box_phar_readonly: $(TU_BOX_DEPS)
	php -d zend.enable_gc=0 -d phar.readonly=1 bin/phpunit

.PHONY: tu_requirement_checker
tu_requirement_checker:	 ## Run the unit tests
tu_requirement_checker: requirement-checker/bin/phpunit requirement-checker/tests/DisplayNormalizer.php requirement-checker/actual_terminal_diff
	cd requirement-checker && $(PHPNOGC) bin/phpunit

	diff requirement-checker/expected_terminal_diff requirement-checker/actual_terminal_diff

.PHONY: tc
tc:			 ## Run the unit tests with code coverage
tc: bin/phpunit
	phpdbg -qrr -d zend.enable_gc=0 bin/phpunit --coverage-html=dist/coverage --coverage-text

.PHONY: tm
tm:			 ## Run Infection
tm:	$(TU_BOX_DEPS)
	$(PHPNOGC) bin/infection

.PHONY: e2e
e2e:			 ## Runs all the end-to-end tests
e2e: e2e_scoper_alias e2e_check_requirements

.PHONY: e2e_scoper_alias
e2e_scoper_alias: 	 ## Runs the end-to-end tests to check that the PHP-Scoper config API is working
e2e_scoper_alias: box
	./box compile --working-dir fixtures/build/dir010

.PHONY: e2e_check_requirements
DOCKER=docker run -i --rm -w /opt/box
PHP7PHAR=box_php72 php index.phar -vvv --no-ansi
PHP5PHAR=box_php53 php index.phar -vvv --no-ansi
e2e_check_requirements:	 ## Runs the end-to-end tests for the check requirements feature
e2e_check_requirements: box
	./.docker/build

	bin/box compile --working-dir fixtures/check-requirements/pass-no-config/

	rm fixtures/check-requirements/pass-no-config/actual-output || true
	$(DOCKER) -v "$$PWD/fixtures/check-requirements/pass-no-config":/opt/box $(PHP5PHAR) > fixtures/check-requirements/pass-no-config/actual-output
	diff fixtures/check-requirements/pass-no-config/expected-output-53 fixtures/check-requirements/pass-no-config/actual-output

	rm fixtures/check-requirements/pass-no-config/actual-output || true
	$(DOCKER) -v "$$PWD/fixtures/check-requirements/pass-no-config":/opt/box $(PHP7PHAR) > fixtures/check-requirements/pass-no-config/actual-output
	diff fixtures/check-requirements/pass-no-config/expected-output-72 fixtures/check-requirements/pass-no-config/actual-output

	bin/box compile --working-dir fixtures/check-requirements/pass-complete/

	rm fixtures/check-requirements/pass-complete/actual-output || true
	$(DOCKER) -v "$$PWD/fixtures/check-requirements/pass-complete":/opt/box $(PHP5PHAR) > fixtures/check-requirements/pass-complete/actual-output
	diff fixtures/check-requirements/pass-complete/expected-output-53 fixtures/check-requirements/pass-complete/actual-output

	rm fixtures/check-requirements/pass-complete/actual-output || true
	$(DOCKER) -v "$$PWD/fixtures/check-requirements/pass-complete":/opt/box $(PHP7PHAR) > fixtures/check-requirements/pass-complete/actual-output
	diff fixtures/check-requirements/pass-complete/expected-output-72 fixtures/check-requirements/pass-complete/actual-output

	bin/box compile --working-dir fixtures/check-requirements/fail-complete/

	rm fixtures/check-requirements/fail-complete/actual-output || true
	$(DOCKER) -v "$$PWD/fixtures/check-requirements/fail-complete":/opt/box $(PHP5PHAR) > fixtures/check-requirements/fail-complete/actual-output || true
	diff fixtures/check-requirements/fail-complete/expected-output-53 fixtures/check-requirements/fail-complete/actual-output

	rm fixtures/check-requirements/fail-complete/actual-output || true
	$(DOCKER) -v "$$PWD/fixtures/check-requirements/fail-complete":/opt/box $(PHP7PHAR) > fixtures/check-requirements/fail-complete/actual-output || true
	diff fixtures/check-requirements/fail-complete/expected-output-72 fixtures/check-requirements/fail-complete/actual-output

.PHONY: blackfire
blackfire:		 ## Profiles the compile step
blackfire: box
	# Profile compiling the PHAR from the source code
	blackfire --reference=1 --samples=5 run $(PHPNOGC) -d bin/box compile --quiet

	# Profile compiling the PHAR from the PHAR
	blackfire --reference=2 --samples=5 run $(PHPNOGC) -d box compile --quiet


#
# Rules from files
#---------------------------------------------------------------------------

composer.lock: composer.json
	composer install
	touch $@

requirement-checker/composer.lock: requirement-checker/composer.json
	composer install --working-dir requirement-checker
	touch $@

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install
	touch $@

bin/phpunit: composer.lock
	composer install
	touch $@

requirement-checker/bin/phpunit: requirement-checker/composer.lock
	composer install --working-dir requirement-checker
	touch $@

requirement-checker/vendor: requirement-checker/composer.json
	composer install --working-dir requirement-checker
	touch $@

vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer: vendor/bamarni
	composer bin php-cs-fixer install
	touch $@

fixtures/composer-dump/dir001/composer.lock:	fixtures/composer-dump/dir001/composer.json
	composer install --working-dir fixtures/composer-dump/dir001
	touch $@

fixtures/composer-dump/dir001/vendor:	fixtures/composer-dump/dir001/composer.lock
	composer install --working-dir fixtures/composer-dump/dir001
	touch $@

.PHONY: fixtures/default_stub.php
fixtures/default_stub.php:
	bin/generate_default_stub

requirement-checker/tests/DisplayNormalizer.php: tests/Console/DisplayNormalizer.php
	cat tests/Console/DisplayNormalizer.php | sed -E 's/namespace KevinGH\\Box\\Console;/namespace KevinGH\\RequirementChecker;/g' > requirement-checker/tests/DisplayNormalizer.php

.requirement-checker: requirement-checker
	$(MAKE) dump-requirement-checker

requirement-checker/actual_terminal_diff: requirement-checker/src/Terminal.php vendor/symfony/console/Terminal.php
	diff vendor/symfony/console/Terminal.php requirement-checker/src/Terminal.php > requirement-checker/actual_terminal_diff || true

vendor/symfony/console/Terminal.php: vendor

box: bin src res vendor box.json.dist scoper.inc.php .requirement-checker
	# Compile Box
	bin/box compile

	rm bin/_box.phar || true
	mv -v bin/box.phar bin/_box.phar

	# Compile Box with the isolated Box PHAR
	php bin/_box.phar compile

	mv -fv bin/box.phar box

	# Test the PHAR which has been created by the isolated PHAR
	./box compile

	rm bin/_box.phar
