# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

OS := $(shell uname)
ERROR_COLOR := \033[41m
YELLOW_COLOR = \033[0;33m
NO_COLOR = \033[0m

COMPOSER_BIN_PLUGIN_VENDOR = vendor/bamarni/composer-bin-plugin

REQUIREMENT_CHECKER_EXTRACT = res/requirement-checker

BOX_BIN = bin/box
BOX = $(BOX_BIN)

SCOPED_BOX_BIN = bin/box.phar
SCOPED_BOX = $(SCOPED_BOX_BIN)
SCOPED_BOX_DEPS = bin/box bin/box.bat vendor $(shell find src res $(wildcard vendor)) box.json.dist scoper.inc.php

COVERAGE_DIR = dist/coverage
COVERAGE_XML_DIR = $(COVERAGE_DIR)/coverage-xml
COVERAGE_JUNIT = $(COVERAGE_DIR)/phpunit.junit.xml
COVERAGE_HTML_DIR = $(COVERAGE_DIR)/html

PHPUNIT_BIN = bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)
PHPUNIT_TEST_SRC = fixtures/default_stub.php $(REQUIREMENT_CHECKER_EXTRACT) fixtures/composer-dump/dir001/vendor fixtures/composer-dump/dir003/vendor
PHPUNIT_COVERAGE_INFECTION = XDEBUG_MODE=coverage php -dphar.readonly=0 $(PHPUNIT) --colors=always --coverage-xml=$(COVERAGE_XML_DIR) --log-junit=$(COVERAGE_JUNIT)
PHPUNIT_COVERAGE_HTML = XDEBUG_MODE=coverage php -dphar.readonly=0 $(PHPUNIT) --colors=always --coverage-html=$(COVERAGE_HTML_DIR)

INFECTION_BIN = vendor-bin/infection/vendor/bin/infection
INFECTION := SYMFONY_DEPRECATIONS_HELPER="disabled=1" php -dzend.enable_gc=0 $(INFECTION_BIN) --skip-initial-tests --coverage=$(COVERAGE_DIR) --only-covered --show-mutations --min-msi=100 --min-covered-msi=100 --ansi --threads=max --show-mutations
INFECTION_CI = $(eval INFECTION_CI := $(INFECTION) ${INFECTION_FLAGS})$(INFECTION_CI)
INFECTION_WITH_INITIAL_TESTS := SYMFONY_DEPRECATIONS_HELPER="disabled=1" php -dzend.enable_gc=0 $(INFECTION_BIN) --only-covered --show-mutations --min-msi=100 --min-covered-msi=100 --ansi --threads=max --show-mutations
INFECTION_SRC := $(shell find src tests) phpunit.xml.dist

PHP_CS_FIXER_BIN = vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
PHP_CS_FIXER = $(PHP_CS_FIXER_BIN)

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

.PHONY: check
check:			 ## Runs all the checks
check: requirement_checker_check box_check

.PHONY: box_check
box_check: cs autoreview test

.PHONY: requirement_checker_check
requirement_checker_check:
	cd requirement-checker; $(MAKE) --file=Makefile check


.PHONY: clean
clean: 	 		 ## Cleans all created artifacts
clean:
	rm -rf \
		dist \
		fixtures/build/dir010/index.phar \
		fixtures/build/dir011/vendor \
		fixtures/build/dir011/expected-output \
		fixtures/build/dir011/index.phar \
		fixtures/build/dir011/output \
		fixtures/build/dir011/phar-Y.php \
		fixtures/build/dir012/bin/console.phar \
		fixtures/build/dir012/var \
		fixtures/build/dir012/vendor \
		fixtures/build/dir012/.env.local.php \
		fixtures/build/dir012/actual-output \
		fixtures/build/dir013/vendor \
		fixtures/build/dir013/actual-output \
		fixtures/build/dir014/actual-output \
		fixtures/build/dir014/index.phar \
		fixtures/default_stub.php \
		 || true
	@# Obsolete entries; Only relevant to someone who still has old artifacts locally
	@rm -rf \
		.php-cs-fixer.cache \
		.phpunit.result.cache \
		box \
		fixtures/check-requirements \
		site \
		website \
		|| true

.PHONY: compile
compile: 		 ## Compiles the application into the PHAR
compile:
	@rm $(SCOPED_BOX_BIN) || true
	$(MAKE) $(SCOPED_BOX_BIN)


.PHONY: dump_requirement_checker
dump_requirement_checker:## Dumps the requirement checker
dump_requirement_checker:
	cd requirement-checker; $(MAKE) --file=Makefile dump


#
# AutoReview commands
#---------------------------------------------------------------------------

.PHONY: autoreview
autoreview: 		 ## AutoReview checks
autoreview: cs_lint phpunit_autoreview

.PHONY: phpunit_autoreview
phpunit_autoreview: $(PHPUNIT_BIN) vendor
	$(PHPUNIT) --testsuite="AutoReviewTests" --colors=always


#
# CS commands
#---------------------------------------------------------------------------

.PHONY: cs
cs:	 		 ## Fixes CS
cs: root_cs requirement_checker_cs

.PHONY: root_cs
root_cs: gitignore_sort composer_normalize php_cs_fixer

.PHONY: requirement_checker_cs
requirement_checker_cs:
	cd requirement-checker; $(MAKE) --file=Makefile cs


.PHONY: cs_lint
cs_lint: 	 	 ## Lints CS
cs_lint: root_cs_lint requirement_checker_cs_lint

.PHONY: root_cs_lint
root_cs_lint: composer_normalize_lint php_cs_fixer_lint

.PHONY: requirement_checker_cs_lint
requirement_checker_cs_lint:
	cd requirement-checker; $(MAKE) --file=Makefile cs_lint

.PHONY: php_cs_fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN) dist
	$(PHP_CS_FIXER) fix

.PHONY: php_cs_fixer_lint
php_cs_fixer_lint: $(PHP_CS_FIXER_BIN) dist
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
test: phpunit_phar_writeable infection test_e2e


#
# Unit Tests commands
#---------------------------------------------------------------------------

.PHONY: test_unit
test_unit: phpunit_phar_readonly phpunit_phar_writeable

.PHONY: phpunit_phar_readonly
phpunit_phar_readonly: $(PHPUNIT_BIN) $(PHPUNIT_TEST_SRC)
	php -dphar.readonly=0 $(PHPUNIT) --testsuite=Tests --colors=always

.PHONY: phpunit_phar_writeable
phpunit_phar_writeable: $(PHPUNIT_BIN) $(PHPUNIT_TEST_SRC)
	php -dphar.readonly=1 $(PHPUNIT) --testsuite=Tests --colors=always

.PHONY: phpunit_coverage_html
phpunit_coverage_html:      ## Runs PHPUnit with code coverage with HTML report
phpunit_coverage_html: $(PHPUNIT_BIN) dist $(PHPUNIT_TEST_SRC) vendor
	$(PHPUNIT_COVERAGE_HTML)
	@echo "You can check the report by opening the file \"$(COVERAGE_HTML_DIR)/index.html\"."

.PHONY: phpunit_coverage_infection
phpunit_coverage_infection: ## Runs PHPUnit tests with test coverage
phpunit_coverage_infection: $(PHPUNIT_BIN) dist $(PHPUNIT_TEST_SRC) vendor
	$(PHPUNIT_COVERAGE_INFECTION)
	touch -c $(COVERAGE_XML_DIR)
	touch -c $(COVERAGE_JUNIT)

.PHONY: infection
infection: $(INFECTION_BIN) dist $(PHPUNIT_TEST_SRC) vendor
	$(INFECTION_WITH_INITIAL_TESTS)

.PHONY: _infection
_infection: $(INFECTION_BIN) $(COVERAGE_XML_DIR) $(COVERAGE_JUNIT) vendor
	$(INFECTION)

.PHONY: _infection_ci
_infection_ci: $(INFECTION_BIN) $(COVERAGE_XML_DIR) $(COVERAGE_JUNIT) vendor
	$(INFECTION_CI)


#
# E2E Tests commands
#---------------------------------------------------------------------------

.PHONY: test_e2e
test_e2e: e2e_php_settings_checker e2e_scoper_alias e2e_scoper_expose_symbols e2e_check_requirements e2e_symfony e2e_composer_installed_versions e2e_phpstorm_stubs

.PHONY: e2e_scoper_alias
e2e_scoper_alias: 	 ## Runs the end-to-end tests to check that the PHP-Scoper config API regarding the prefix alias is working
e2e_scoper_alias: $(SCOPED_BOX_BIN)
	$(SCOPED_BOX) compile --working-dir=fixtures/build/dir010 --no-parallel --ansi

.PHONY: e2e_scoper_expose_symbols
e2e_scoper_expose_symbols: ## Runs the end-to-end tests to check that the PHP-Scoper config API regarding the symbols exposure is working
e2e_scoper_expose_symbols: $(SCOPED_BOX_BIN) fixtures/build/dir011/vendor
	php fixtures/build/dir011/index.php > fixtures/build/dir011/expected-output
	$(SCOPED_BOX) compile --working-dir=fixtures/build/dir011 --no-parallel --ansi

	php fixtures/build/dir011/index.phar > fixtures/build/dir011/output
	cd fixtures/build/dir011 && php -r "file_put_contents('phar-Y.php', file_get_contents((new Phar('index.phar'))['src/Y.php']));"

	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir011/expected-output fixtures/build/dir011/output
	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir011/phar-Y.php fixtures/build/dir011/src/Y.php

.PHONY: e2e_check_requirements
e2e_check_requirements:	 ## Runs the end-to-end tests for the check requirements feature
e2e_check_requirements:
	cd requirement-checker; $(MAKE) --file=Makefile test_e2e

BOX_COMPILE := $(SCOPED_BOX) compile --working-dir=fixtures/php-settings-checker -vvv --no-ansi
ifeq ($(OS),Darwin)
	SED = sed -i ''
else
	SED = sed -i
endif
.PHONY: e2e_php_settings_checker
e2e_php_settings_checker: ## Runs the end-to-end tests for the PHP settings handler
e2e_php_settings_checker: docker_images fixtures/php-settings-checker/output-xdebug-enabled vendor $(SCOPED_BOX_BIN)
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
e2e_symfony: $(SCOPED_BOX_BIN) fixtures/build/dir012/vendor
	composer dump-env prod --working-dir=fixtures/build/dir012

	php fixtures/build/dir012/bin/console --version > fixtures/build/dir012/expected-output
	rm -rf fixtures/build/dir012/var/cache/prod/*

	$(SCOPED_BOX) compile --working-dir=fixtures/build/dir012 --no-parallel --ansi

	php fixtures/build/dir012/bin/console.phar --version > fixtures/build/dir012/actual-output

	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir012/expected-output fixtures/build/dir012/actual-output

.PHONY: e2e_composer_installed_versions
e2e_composer_installed_versions: ## Packages an app using Composer\InstalledVersions
e2e_composer_installed_versions: $(SCOPED_BOX_BIN) fixtures/build/dir013/vendor
	$(SCOPED_BOX) compile --working-dir=fixtures/build/dir013 --no-parallel
	
	php fixtures/build/dir013/bin/run.phar > fixtures/build/dir013/actual-output

	diff --ignore-all-space --side-by-side --suppress-common-lines fixtures/build/dir013/expected-output fixtures/build/dir013/actual-output

.PHONY: e2e_phpstorm_stubs
e2e_phpstorm_stubs:	 ## Project using symbols which should be vetted by PhpStormStubs
e2e_phpstorm_stubs: $(SCOPED_BOX_BIN)
	$(SCOPED_BOX) compile --working-dir=fixtures/build/dir014 --no-parallel

	php fixtures/build/dir014/index.phar > fixtures/build/dir014/actual-output

	diff fixtures/build/dir014/expected-output fixtures/build/dir014/actual-output


#---------------------------------------------------------------------------


.PHONY: blackfire
blackfire:		 ## Profiles the compile step
blackfire: $(SCOPED_BOX_BIN)
	# Profile compiling the PHAR from the source code
	blackfire --reference=1 --samples=5 run $(PHPNOGC) -d $(BOX) compile --quiet --no-parallel

	# Profile compiling the PHAR from the PHAR
	blackfire --reference=2 --samples=5 run $(PHPNOGC) -d $(SCOPED_BOX) compile --quiet --no-parallel


#
# Website rules
#---------------------------------------------------------------------------


.PHONY: website_build
website_build:		 ## Builds the website
website_build:
	@echo "$(YELLOW_COLOR)To install mkdocs ensure you have Python3 & pip3 and run the following command:$(NO_COLOR)"
	@echo "$$ pip install mkdocs mkdocs-material"

	@rm -rf $(WEBSITE_OUTPUT) || true
	$(MAKE) _website_build

.PHONY: _website_build
_website_build: $(WEBSITE_OUTPUT)

.PHONY: website_serve
website_serve:		 ## Serves the website locally
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
	touch -c $(PHPUNIT_BIN)

composer.lock: composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock && touch -c $(@)"
vendor: composer.lock
	$(MAKE) vendor_install

$(COMPOSER_BIN_PLUGIN_VENDOR): composer.lock
	$(MAKE) --always-make vendor_install

$(PHPUNIT_BIN): composer.lock
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
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer bin php-cs-fixer update --lock && touch -c $(@)"

.PHONY: infection_install
infection_install: $(INFECTION_BIN)

$(INFECTION_BIN): vendor-bin/infection/vendor
	touch -c $@
vendor-bin/infection/vendor: vendor-bin/infection/composer.lock $(COMPOSER_BIN_PLUGIN_VENDOR)
	composer bin infection install
	touch -c $@
vendor-bin/infection/composer.lock: vendor-bin/infection/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer bin infection update --lock && touch -c $(@)"

$(COVERAGE_XML_DIR): $(PHPUNIT_BIN) dist $(PHPUNIT_TEST_SRC) $(INFECTION_SRC)
	$(PHPUNIT_COVERAGE_INFECTION)
	touch -c $@
	touch -c $(COVERAGE_JUNIT)

$(COVERAGE_JUNIT): $(PHPUNIT_BIN) dist $(PHPUNIT_TEST_SRC) $(INFECTION_SRC)
	$(PHPUNIT_COVERAGE_INFECTION)
	touch -c $@
	touch -c $(COVERAGE_XML_DIR)

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
	php -dphar.readonly=0 bin/generate_default_stub

$(REQUIREMENT_CHECKER_EXTRACT):
	cd requirement-checker; $(MAKE) --file=Makefile _dump

$(SCOPED_BOX_BIN): $(SCOPED_BOX_DEPS)
	@echo "$(YELLOW_COLOR)Compile Box.$(NO_COLOR)"
	$(BOX) compile --ansi --no-parallel

	rm bin/_box.phar || true
	mv -v bin/box.phar bin/_box.phar

	@echo "$(YELLOW_COLOR)Compile Box with the isolated Box PHAR.$(NO_COLOR)"
	php bin/_box.phar compile --ansi --no-parallel

	mv -fv bin/box.phar box

	@echo "$(YELLOW_COLOR)Test the PHAR which has been created by the isolated PHAR.$(NO_COLOR)"
	./box compile --ansi --no-parallel

	mv -fv box bin/box.phar
	rm bin/_box.phar

	touch -c $@

.PHONY: docker_images
docker_images:
	./.docker/build

fixtures/php-settings-checker/output-xdebug-enabled: fixtures/php-settings-checker/output-xdebug-enabled.tpl docker_images
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

dist:
	mkdir -p dist
	touch dist/.gitkeep
	touch -c $@
