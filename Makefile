# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

OS := $(shell uname)
PHPNOGC = php -d zend.enable_gc=0
ERROR_COLOR := \033[41m
YELLOW_COLOR = \033[0;33m
NO_COLOR = \033[0m

COMPOSER_BIN_PLUGIN_VENDOR = vendor/bamarni/composer-bin-plugin

PHP_CS_FIXER_BIN = vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
PHP_CS_FIXER = $(PHP_CS_FIXER_BIN)

REQUIREMENT_CHECKER_EXTRACT = res/requirement-checker

DOCKER = docker run --interactive --platform=linux/amd64 --rm --workdir=/opt/box
MIN_SUPPORTED_PHP_BOX = box_php81
MIN_SUPPORTED_PHP_WITH_XDEBUG_BOX = box_php81_xdebug

WEBSITE_SRC := mkdocs.yaml $(shell find doc)
# This is defined in mkdocs.yaml#site_dir
WEBSITE_OUTPUT = dist/website


.DEFAULT_GOAL := help


.PHONY: help
help:
	@printf "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'


#
# Commands
#---------------------------------------------------------------------------

.PHONY: clean
clean: 	 		 ## Cleans all created artifacts
clean:
	git clean --exclude=.idea/ -ffdx
	rm -rf fixtures/check-requirements || true
	@# Obsolete entries; Only relevant to someone who still has old artifacts locally
	@rm -rf \
		.php-cs-fixer.cache \
		.phpunit.result.cache \
		site \
		website \
		|| true

.PHONY: compile
compile: 		 ## Compiles the application into the PHAR
compile: box
	cp -f box bin/box.phar

.PHONY: dump_requirement_checker
dump_requirement_checker:## Dumps the requirement checker
dump_requirement_checker:
	cd requirement-checker; $(MAKE) --file=Makefile dump


#
# CS commands
#---------------------------------------------------------------------------

.PHONY: compile
compile: 		 ## Compiles the application into the PHAR
compile: box
	cp -f box bin/box.phar

.PHONY: dump_requirement_checker
dump_requirement_checker:## Dumps the requirement checker
dump_requirement_checker:
	cd requirement-checker; $(MAKE) --file=Makefile dump


#
# CS commands
#---------------------------------------------------------------------------

.PHONY: cs
cs:	 ## Fixes CS
cs: root_cs requirement_checker_cs

.PHONY: root_cs
root_cs: gitignore_sort composer_normalize php_cs_fixer

.PHONY: requirement_checker_cs
requirement_checker_cs:
	cd requirement-checker; $(MAKE) --file=Makefile cs

.PHONY: cs_lint
cs_lint: ## Checks CS
cs_lint: root_cs_lint requirement_checker_cs_lint

.PHONY: root_cs_lint
root_cs_lint: composer_normalize_lint php_cs_fixer_lint

.PHONY: requirement_checker_cs_lint
requirement_checker_cs_lint:
	cd requirement-checker; $(MAKE) --file=Makefile cs_lint

.PHONY: php_cs_fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix

.PHONY: php_cs_fixer_lint
php_cs_fixer_lint: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix --ansi --verbose --dry-run --diff

.PHONY: composer_normalize
composer_normalize: composer.json vendor
	composer normalize --ansi

.PHONY: composer_normalize_lint
composer_normalize_lint: composer.json vendor
	composer normalize --ansi --dry-run

.PHONY: gitignore_sort
gitignore_sort:
	LC_ALL=C sort -u .gitignore -o .gitignore


#
# Tests commands
#---------------------------------------------------------------------------

.PHONY: test
test:		  	 ## Runs all the tests
test: tu e2e

.PHONY: tu
tu:			 ## Runs the unit tests
tu: tu_requirement_checker tu_box

.PHONY: tu_box
tu_box:			 ## Runs the unit tests
TU_BOX_DEPS = bin/phpunit fixtures/default_stub.php $(REQUIREMENT_CHECKER_EXTRACT) fixtures/composer-dump/dir001/vendor fixtures/composer-dump/dir003/vendor
tu_box: $(TU_BOX_DEPS)
	php -d phar.readonly=1 bin/phpunit --colors=always

.PHONY: tu_box_phar_readonly
tu_box_phar_readonly: 	 ## Runs the unit tests with the setting `phar.readonly` to `On`
tu_box_phar_readonly: $(TU_BOX_DEPS)
	php -d zend.enable_gc=0 -d phar.readonly=1 bin/phpunit --colors=always

.PHONY: tu_requirement_checker
tu_requirement_checker:	 ## Runs the unit tests
tu_requirement_checker:
	cd requirement-checker; $(MAKE) --file=Makefile test

.PHONY: tc
tc:			 ## Runs the unit tests with code coverage
tc: bin/phpunit
	php -d zend.enable_gc=0 bin/phpunit --coverage-html=dist/coverage --coverage-text

.PHONY: tm
INFECTION=vendor-bin/infection/vendor/bin/infection
tm:			 ## Runs Infection
tm:	$(TU_BOX_DEPS) $(INFECTION)
	$(PHPNOGC) $(INFECTION) --threads=$(shell nproc || sysctl -n hw.ncpu || 1) --only-covered --only-covering-test-cases $$INFECTION_FLAGS

.PHONY: e2e
e2e:			 ## Runs all the end-to-end tests
e2e: e2e_php_settings_checker e2e_scoper_alias e2e_scoper_expose_symbols e2e_check_requirements e2e_symfony e2e_composer_installed_versions e2e_phpstorm_stubs

.PHONY: e2e_scoper_alias
e2e_scoper_alias: 	 ## Runs the end-to-end tests to check that the PHP-Scoper config API regarding the prefix alias is working
e2e_scoper_alias: box
	./box compile --working-dir=fixtures/build/dir010 --no-parallel

.PHONY: e2e_scoper_expose_symbols
e2e_scoper_expose_symbols: 	 ## Runs the end-to-end tests to check that the PHP-Scoper config API regarding the symbols exposure is working
e2e_scoper_expose_symbols: box fixtures/build/dir011/vendor
	php fixtures/build/dir011/index.php > fixtures/build/dir011/expected-output
	./box compile --working-dir=fixtures/build/dir011 --no-parallel

	php fixtures/build/dir011/index.phar > fixtures/build/dir011/output
	cd fixtures/build/dir011 && php -r "file_put_contents('phar-Y.php', file_get_contents((new Phar('index.phar'))['src/Y.php']));"

	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir011/expected-output fixtures/build/dir011/output
	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir011/phar-Y.php fixtures/build/dir011/src/Y.php

.PHONY: e2e_check_requirements
e2e_check_requirements:	 ## Runs the end-to-end tests for the check requirements feature
e2e_check_requirements:
	cd requirement-checker; $(MAKE) --file=Makefile test_e2e

BOX_COMPILE=./box compile --working-dir=fixtures/php-settings-checker -vvv --no-ansi
ifeq ($(OS),Darwin)
	SED = sed -i ''
else
	SED = sed -i
endif
.PHONY: e2e_php_settings_checker
e2e_php_settings_checker: ## Runs the end-to-end tests for the PHP settings handler
e2e_php_settings_checker: docker-images fixtures/php-settings-checker/output-xdebug-enabled vendor box
	@echo "$(YELLOW_COLOR)No restart needed$(NO_COLOR)"
	$(DOCKER) -v "$$PWD":/opt/box $(MIN_SUPPORTED_PHP_BOX) \
		php -dphar.readonly=0 -dmemory_limit=-1 \
		$(BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee fixtures/php-settings-checker/actual-output || true
	$(SED) "s/Xdebug/xdebug/" fixtures/php-settings-checker/actual-output
	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/php-settings-checker/output-all-clear fixtures/php-settings-checker/actual-output

	@echo "$(YELLOW_COLOR)Xdebug enabled: restart needed$(NO_COLOR)"
	$(DOCKER) -v "$$PWD":/opt/box $(MIN_SUPPORTED_PHP_BOX)_xdebug \
		php -dphar.readonly=0 -dmemory_limit=-1 \
		$(BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee fixtures/php-settings-checker/actual-output || true
	$(SED) "s/Xdebug/xdebug/" fixtures/php-settings-checker/actual-output
	$(SED) "s/[0-9]* ms/100 ms/" fixtures/php-settings-checker/actual-output
	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/php-settings-checker/output-xdebug-enabled fixtures/php-settings-checker/actual-output

	@echo "$(YELLOW_COLOR)phar.readonly enabled: restart needed$(NO_COLOR)"
	$(DOCKER) -v "$$PWD":/opt/box $(MIN_SUPPORTED_PHP_BOX) \
		php -dphar.readonly=1 -dmemory_limit=-1 \
		$(BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee fixtures/php-settings-checker/actual-output || true
	$(SED) "s/Xdebug/xdebug/" fixtures/php-settings-checker/actual-output
	$(SED) "s/'-c' '.*' '\.\/box'/'-c' '\/tmp-file' 'bin\/box'/" fixtures/php-settings-checker/actual-output
	$(SED) "s/[0-9]* ms/100 ms/" fixtures/php-settings-checker/actual-output
	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/php-settings-checker/output-pharreadonly-enabled fixtures/php-settings-checker/actual-output

	@echo "$(YELLOW_COLOR)Bump min memory limit if necessary (limit lower than default)$(NO_COLOR)"
	$(DOCKER) -v "$$PWD":/opt/box $(MIN_SUPPORTED_PHP_BOX) \
		php -dphar.readonly=0 -dmemory_limit=124M \
		$(BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee fixtures/php-settings-checker/actual-output || true
	$(SED) "s/Xdebug/xdebug/" fixtures/php-settings-checker/actual-output
	$(SED) "s/'-c' '.*' '\.\/box'/'-c' '\/tmp-file' 'bin\/box'/" fixtures/php-settings-checker/actual-output
	$(SED) "s/[0-9]* ms/100 ms/" fixtures/php-settings-checker/actual-output
	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/php-settings-checker/output-min-memory-limit fixtures/php-settings-checker/actual-output

	@echo "$(YELLOW_COLOR)Bump min memory limit if necessary (limit higher than default)$(NO_COLOR)"
	$(DOCKER) -e BOX_MEMORY_LIMIT=64M -v "$$PWD":/opt/box $(MIN_SUPPORTED_PHP_BOX) \
		php -dphar.readonly=0 -dmemory_limit=1024M \
		$(BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee fixtures/php-settings-checker/actual-output || true
	$(SED) "s/Xdebug/xdebug/" fixtures/php-settings-checker/actual-output
	$(SED) "s/'-c' '.*' '\.\/box'/'-c' '\/tmp-file' 'bin\/box'/" fixtures/php-settings-checker/actual-output
	$(SED) "s/[0-9]* ms/100 ms/" fixtures/php-settings-checker/actual-output
	diff --ignore-all-space --side-by-side --suppress-common-lines  fixtures/php-settings-checker/output-set-memory-limit fixtures/php-settings-checker/actual-output

.PHONY: e2e_symfony
e2e_symfony:		 ## Packages a fresh Symfony app
e2e_symfony: fixtures/build/dir012/vendor box
	composer dump-env prod --working-dir=fixtures/build/dir012

	php fixtures/build/dir012/bin/console --version > fixtures/build/dir012/expected-output
	rm -rf fixtures/build/dir012/var/cache/prod/*

	./box compile --working-dir=fixtures/build/dir012 --no-parallel

	php fixtures/build/dir012/bin/console.phar --version > fixtures/build/dir012/actual-output

	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir012/expected-output fixtures/build/dir012/actual-output

.PHONY: e2e_composer_installed_versions
e2e_composer_installed_versions:		 ## Packages an app using Composer\InstalledVersions
e2e_composer_installed_versions: fixtures/build/dir013/vendor box
	./box compile --working-dir=fixtures/build/dir013 --no-parallel
	
	php fixtures/build/dir013/bin/run.phar > fixtures/build/dir013/actual-output

	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir013/expected-output fixtures/build/dir013/actual-output

.PHONY: e2e_phpstorm_stubs
e2e_phpstorm_stubs:		 ## Project using symbols which should be vetted by PhpStormStubs
e2e_phpstorm_stubs: box
	./box compile --working-dir=fixtures/build/dir014 --no-parallel

	php fixtures/build/dir014/index.phar > fixtures/build/dir014/actual-output

	diff fixtures/build/dir014/expected-output fixtures/build/dir014/actual-output

.PHONY: blackfire
blackfire:		 ## Profiles the compile step
blackfire: box
	# Profile compiling the PHAR from the source code
	blackfire --reference=1 --samples=5 run $(PHPNOGC) -d bin/box compile --quiet --no-parallel

	# Profile compiling the PHAR from the PHAR
	blackfire --reference=2 --samples=5 run $(PHPNOGC) -d box compile --quiet --no-parallel


#
# Website rules
#---------------------------------------------------------------------------


.PHONY: website_build
website_build:	## Builds the website
website_build:
	@echo "$(YELLOW_COLOR)To install mkdocs ensure you have Python3 & pip3 and run the following command:$(NO_COLOR)"
	@echo "$$ pip install mkdocs mkdocs-material"

	@rm -rf $(WEBSITE_OUTPUT) || true
	$(MAKE) _website_build

.PHONY: _website_build
_website_build: $(WEBSITE_OUTPUT)

.PHONY: website_serve
website_serve:	## Serves the website locally
website_serve:
	@echo "$(YELLOW_COLOR)To install mkdocs ensure you have Python3 & pip3 and run the following command:$(NO_COLOR)"
	@echo "$$ pip install mkdocs mkdocs-material"
	mkdocs serve

$(WEBSITE_OUTPUT): $(WEBSITE_SRC)
	mkdocs build --clean --strict
	@touch -c $@


#
# Rules from files
#---------------------------------------------------------------------------

# Sometimes we need to re-install the vendor. Since it has a few dependencies
# we do not want to check over and over, as unlike re-installing dependencies
# which is fast, those might have a significant overhead (e.g. checking the
# composer root version), we do not want to repeat the step of checking the
# vendor dependencies.
.PHONY: vendor_install
vendor_install:
	composer install --ansi
	touch -c vendor
	touch -c $(COMPOSER_BIN_PLUGIN_VENDOR)
	touch -c bin/phpunit

composer.lock: composer.json
	@echo "$(@) is not up to date. You may want to run the following command:"
	@echo "$$ composer update --lock && touch -c $(@)"

vendor: composer.lock
	$(MAKE) vendor_install

$(COMPOSER_BIN_PLUGIN_VENDOR): composer.lock
	$(MAKE) --always-make vendor_install

bin/phpunit: composer.lock
	$(MAKE) --always-make vendor_install
	touch -c $@

.PHONY: php_cs_fixer_install
php_cs_fixer_install: $(PHP_CS_FIXER_BIN)

$(PHP_CS_FIXER_BIN): vendor-bin/php-cs-fixer/vendor
	touch -c $@
vendor-bin/php-cs-fixer/vendor: vendor-bin/php-cs-fixer/composer.lock $(COMPOSER_BIN_PLUGIN_VENDOR)
	composer bin php-cs-fixer install
	touch -c $@
vendor-bin/php-cs-fixer/composer.lock: vendor-bin/php-cs-fixer/composer.json
	@echo "$(@) is not up to date. You may want to run the following command:"
	@echo "$$ composer bin php-cs-fixer update --lock && touch -c $(@)"

.PHONY: infection_install
infection_install: $(INFECTION)

$(INFECTION): vendor-bin/infection/vendor
	touch -c $@
vendor-bin/infection/vendor: vendor-bin/infection/composer.lock $(COMPOSER_BIN_PLUGIN_VENDOR)
	composer bin infection install
	touch -c $@
vendor-bin/infection/composer.lock: vendor-bin/infection/composer.json
	@echo "$(@) is not up to date. You may want to run the following command:"
	@echo "$$ composer bin infection update --lock && touch -c $(@)"

fixtures/composer-dump/dir001/composer.lock: fixtures/composer-dump/dir001/composer.json
	composer install --ansi --working-dir=fixtures/composer-dump/dir001
	touch -c $@

fixtures/composer-dump/dir003/composer.lock: fixtures/composer-dump/dir003/composer.json
	composer install --ansi --working-dir=fixtures/composer-dump/dir003
	touch -c $@

fixtures/composer-dump/dir001/vendor: fixtures/composer-dump/dir001/composer.lock
	composer install --ansi --working-dir=fixtures/composer-dump/dir001
	touch -c $@

fixtures/composer-dump/dir003/vendor: fixtures/composer-dump/dir003/composer.lock
	composer install --ansi --working-dir=fixtures/composer-dump/dir003
	touch -c $@

fixtures/build/dir011/vendor:
	composer install --ansi --working-dir=fixtures/build/dir011
	touch -c $@

fixtures/build/dir012/vendor:
	composer install --ansi --working-dir=fixtures/build/dir012
	touch -c $@

fixtures/build/dir013/vendor:
	composer install --ansi --working-dir=fixtures/build/dir013
	touch -c $@

.PHONY: fixtures/default_stub.php
fixtures/default_stub.php:
	php -d phar.readonly=0 bin/generate_default_stub

$(REQUIREMENT_CHECKER_EXTRACT):
	cd requirement-checker; $(MAKE) --file=Makefile _dump

box: bin src res vendor box.json.dist scoper.inc.php $(REQUIREMENT_CHECKER_EXTRACT)
	@echo "$(YELLOW_COLOR)Compile Box.$(NO_COLOR)"
	bin/box compile --ansi --no-parallel

	rm bin/_box.phar || true
	mv -v bin/box.phar bin/_box.phar

	@echo "$(YELLOW_COLOR)Compile Box with the isolated Box PHAR.$(NO_COLOR)"
	php bin/_box.phar compile --ansi --no-parallel

	mv -fv bin/box.phar box

	@echo "$(YELLOW_COLOR)Test the PHAR which has been created by the isolated PHAR.$(NO_COLOR)"
	./box compile --ansi --no-parallel

	rm bin/_box.phar

	touch -c $@

.PHONY: docker-images
docker-images:
	./.docker/build

fixtures/php-settings-checker/output-xdebug-enabled: fixtures/php-settings-checker/output-xdebug-enabled.tpl docker-images
	./fixtures/php-settings-checker/create-expected-output $(MIN_SUPPORTED_PHP_WITH_XDEBUG_BOX)
	touch -c $@


.PHONY: requirement_checker_install
requirement_checker_install: vendor-bin/requirement-checker/vendor

vendor-bin/requirement-checker/vendor: vendor-bin/requirement-checker/composer.lock $(COMPOSER_BIN_PLUGIN_VENDOR)
	composer bin requirement-checker install
	touch -c $@
vendor-bin/requirement-checker/composer.lock: vendor-bin/requirement-checker/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer bin requirement-checker update --lock && touch -c $(@)"
