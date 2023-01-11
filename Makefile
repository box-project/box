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
SCOPED_BOX_DEPS = bin/box bin/box.bat $(shell find src res) box.json.dist scoper.inc.php vendor

COVERAGE_DIR = dist/coverage
COVERAGE_XML_DIR = $(COVERAGE_DIR)/coverage-xml
COVERAGE_JUNIT = $(COVERAGE_DIR)/phpunit.junit.xml
COVERAGE_HTML_DIR = $(COVERAGE_DIR)/html

PHPUNIT_BIN = vendor/bin/phpunit
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

DOCKER_RUN = docker run --interactive --platform=linux/amd64 --rm --workdir=/opt/box
# Matches the minimum PHP version supported by Box.
DOCKER_MIN_BOX_VERSION_IMAGE_TAG = box_php81
DOCKER_MIN_BOX_XDEBUG_PHP_VERSION_IMAGE_TAG = box_php81_xdebug

E2E_SCOPER_EXPOSE_SYMBOLS_DIR = fixtures/build/dir011
E2E_SCOPER_EXPOSE_SYMBOLS_OUTPUT_DIR = dist/dir011
E2E_SCOPER_EXPOSE_SYMBOLS_EXPECTED_SCOPED_FILE := $(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/src/Y.php
E2E_SCOPER_EXPOSE_SYMBOLS_ACTUAL_SCOPED_FILE := $(E2E_SCOPER_EXPOSE_SYMBOLS_OUTPUT_DIR)/phar-Y.php
E2E_SCOPER_EXPOSE_SYMBOLS_EXPECTED_OUTPUT := $(E2E_SCOPER_EXPOSE_SYMBOLS_OUTPUT_DIR)/expected-output
E2E_SCOPER_EXPOSE_SYMBOLS_ACTUAL_OUTPUT := $(E2E_SCOPER_EXPOSE_SYMBOLS_OUTPUT_DIR)/actual-output

E2E_PHP_SETTINGS_CHECKER_DIR = fixtures/php-settings-checker
E2E_PHP_SETTINGS_CHECKER_OUTPUT_DIR = dist/php-settings-checker
E2E_PHP_SETTINGS_CHECKER_EXPECTED_XDEBUG_ENABLED_OUTPUT = $(E2E_PHP_SETTINGS_CHECKER_OUTPUT_DIR)/output-xdebug-enabled
E2E_PHP_SETTINGS_CHECKER_EXPECTED_OUTPUT_XDEBUG_ENABLED_TEMPLATE = $(E2E_PHP_SETTINGS_CHECKER_DIR)/output-xdebug-enabled.tpl
E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT = $(E2E_PHP_SETTINGS_CHECKER_OUTPUT_DIR)/actual-output
E2E_PHP_SETTINGS_CHECKER_BOX_COMPILE := $(SCOPED_BOX) compile --working-dir=$(E2E_PHP_SETTINGS_CHECKER_DIR) -vvv --no-ansi

E2E_SYMFONY_DIR = fixtures/build/dir012
E2E_SYMFONY_OUTPUT_DIR = dist/dir012
E2E_SYMFONY_EXPECTED_OUTPUT := $(E2E_SYMFONY_OUTPUT_DIR)/expected-output
E2E_SYMFONY_ACTUAL_OUTPUT := $(E2E_SYMFONY_OUTPUT_DIR)/actual-output

E2E_COMPOSER_INSTALLED_DIR = fixtures/build/dir013
E2E_COMPOSER_INSTALLED_OUTPUT_DIR = dist/dir013
E2E_COMPOSER_INSTALLED_EXPECTED_OUTPUT := $(E2E_COMPOSER_INSTALLED_DIR)/expected-output
E2E_COMPOSER_INSTALLED_ACTUAL_OUTPUT := $(E2E_COMPOSER_INSTALLED_OUTPUT_DIR)/actual-output

E2E_PHPSTORM_STUBS_DIR = fixtures/build/dir014
E2E_PHPSTORM_STUBS_OUTPUT_DIR = dist/dir014
E2E_PHPSTORM_STUBS_EXPECTED_OUTPUT := $(E2E_PHPSTORM_STUBS_DIR)/expected-output
E2E_PHPSTORM_STUBS_ACTUAL_OUTPUT := $(E2E_PHPSTORM_STUBS_OUTPUT_DIR)/actual-output

ifeq ($(OS),Darwin)
	SED = sed -i ''
else
	SED = sed -i
endif
DIFF = diff --strip-trailing-cr --ignore-all-space --side-by-side --suppress-common-lines


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
		fixtures/build/*/.box_dump \
		fixtures/build/*/vendor \
		fixtures/build/dir010/index.phar \
		fixtures/build/dir012/bin/console.phar \
		$(E2E_PHP_SETTINGS_CHECKER_DIR)/index.phar \
		$(E2E_SYMFONY_DIR)/var \
		$(E2E_SYMFONY_DIR)/.env.local.php \
		fixtures/default_stub.php \
		fixtures/composer-dump/*/vendor \
		 || true
	@# Obsolete entries; Only relevant to someone who still has old artifacts locally
	@rm -rf \
		.php-cs-fixer.cache \
		.phpunit.result.cache \
		box \
		fixtures/check-requirements \
		fixtures/build/*/index.phar \
		$(E2E_PHP_SETTINGS_CHECKER_DIR)/actual-output \
		$(E2E_SYMFONY_DIR)/actual-output \
		$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/expected-output \
		$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/output \
		$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/phar-Y.php \
		$(E2E_COMPOSER_INSTALLED_DIR)/actual-output \
		$(E2E_PHPSTORM_STUBS_DIR)/actual-output \
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
e2e_scoper_expose_symbols:
e2e_scoper_expose_symbols: $(SCOPED_BOX_BIN) $(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/vendor
	@# Check that the PHP-Scoper config API regarding the symbols exposure is working
	mkdir -p $(E2E_SCOPER_EXPOSE_SYMBOLS_OUTPUT_DIR)
	php $(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/index.php > $(E2E_SCOPER_EXPOSE_SYMBOLS_EXPECTED_OUTPUT)

	$(SCOPED_BOX) compile --working-dir=$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR) --no-parallel --ansi
	php $(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/index.phar > $(E2E_SCOPER_EXPOSE_SYMBOLS_ACTUAL_OUTPUT)
	mv -fv $(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/index.phar $(E2E_SCOPER_EXPOSE_SYMBOLS_OUTPUT_DIR)
	cd $(E2E_SCOPER_EXPOSE_SYMBOLS_OUTPUT_DIR) && php -r "file_put_contents('phar-Y.php', file_get_contents((new Phar('index.phar'))['src/Y.php']));"

	$(DIFF) $(E2E_SCOPER_EXPOSE_SYMBOLS_EXPECTED_OUTPUT) $(E2E_SCOPER_EXPOSE_SYMBOLS_ACTUAL_OUTPUT)
	$(DIFF) $(E2E_SCOPER_EXPOSE_SYMBOLS_EXPECTED_SCOPED_FILE) $(E2E_SCOPER_EXPOSE_SYMBOLS_ACTUAL_SCOPED_FILE)

.PHONY: e2e_php_settings_checker
e2e_php_settings_checker: docker_images _e2e_php_settings_checker

.PHONY: _e2e_php_settings_checker
_e2e_php_settings_checker: $(SCOPED_BOX_BIN) $(E2E_PHP_SETTINGS_CHECKER_EXPECTED_XDEBUG_ENABLED_OUTPUT)
	@echo "$(YELLOW_COLOR)No restart needed$(NO_COLOR)"
	$(DOCKER_RUN) -v "$$PWD":/opt/box $(DOCKER_MIN_BOX_VERSION_IMAGE_TAG) \
		php -dphar.readonly=0 -dmemory_limit=-1 \
		$(E2E_PHP_SETTINGS_CHECKER_BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT) || true
	$(SED) "s/Xdebug/xdebug/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(DIFF) $(E2E_PHP_SETTINGS_CHECKER_DIR)/output-all-clear $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)

	@echo ""
	@echo "$(YELLOW_COLOR)Xdebug enabled: restart needed$(NO_COLOR)"
	$(DOCKER_RUN) -v "$$PWD":/opt/box $(DOCKER_MIN_BOX_XDEBUG_PHP_VERSION_IMAGE_TAG) \
		php -dphar.readonly=0 -dmemory_limit=-1 \
		$(E2E_PHP_SETTINGS_CHECKER_BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT) || true
	$(SED) "s/Xdebug/xdebug/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(SED) "s/[0-9]* ms/100 ms/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(DIFF) $(E2E_PHP_SETTINGS_CHECKER_EXPECTED_XDEBUG_ENABLED_OUTPUT) $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)

	@echo ""
	@echo "$(YELLOW_COLOR)phar.readonly enabled: restart needed$(NO_COLOR)"
	$(DOCKER_RUN) -v "$$PWD":/opt/box $(DOCKER_MIN_BOX_VERSION_IMAGE_TAG) \
		php -dphar.readonly=1 -dmemory_limit=-1 \
		$(E2E_PHP_SETTINGS_CHECKER_BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT) || true
	$(SED) "s/Xdebug/xdebug/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(SED) "s/'-c' '.*' '\.\/box'/'-c' '\/tmp-file' 'bin\/box'/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(SED) "s/[0-9]* ms/100 ms/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(DIFF) $(E2E_PHP_SETTINGS_CHECKER_DIR)/output-pharreadonly-enabled $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)

	@echo ""
	@echo "$(YELLOW_COLOR)Bump min memory limit if necessary (limit lower than default)$(NO_COLOR)"
	$(DOCKER_RUN) -v "$$PWD":/opt/box $(DOCKER_MIN_BOX_VERSION_IMAGE_TAG) \
		php -dphar.readonly=0 -dmemory_limit=124M \
		$(E2E_PHP_SETTINGS_CHECKER_BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT) || true
	$(SED) "s/Xdebug/xdebug/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(SED) "s/'-c' '.*' '\.\/box'/'-c' '\/tmp-file' 'bin\/box'/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(SED) "s/[0-9]* ms/100 ms/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(DIFF) $(E2E_PHP_SETTINGS_CHECKER_DIR)/output-min-memory-limit $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)

	@echo ""
	@echo "$(YELLOW_COLOR)Bump min memory limit if necessary (limit higher than default)$(NO_COLOR)"
	$(DOCKER_RUN) -e BOX_MEMORY_LIMIT=64M -v "$$PWD":/opt/box $(DOCKER_MIN_BOX_VERSION_IMAGE_TAG) \
		php -dphar.readonly=0 -dmemory_limit=1024M \
		$(E2E_PHP_SETTINGS_CHECKER_BOX_COMPILE) \
		| grep '\[debug\]' \
		| tee $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT) || true
	$(SED) "s/Xdebug/xdebug/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(SED) "s/'-c' '.*' '\.\/box'/'-c' '\/tmp-file' 'bin\/box'/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(SED) "s/[0-9]* ms/100 ms/" $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)
	$(DIFF) $(E2E_PHP_SETTINGS_CHECKER_DIR)/output-set-memory-limit $(E2E_PHP_SETTINGS_CHECKER_ACTUAL_OUTPUT)

.PHONY: e2e_symfony
e2e_symfony: $(SCOPED_BOX_BIN) $(E2E_SYMFONY_DIR)/vendor $(E2E_SYMFONY_DIR)/.env.local.php
	@# Packages a fresh Symfony app
	@mkdir -p $(E2E_SYMFONY_OUTPUT_DIR)
	php $(E2E_SYMFONY_DIR)/bin/console --version --no-ansi > $(E2E_SYMFONY_EXPECTED_OUTPUT)

	@# Clear the cache: we want to make sure it works on a clean installation
	$(E2E_SYMFONY_DIR)/bin/console cache:pool:clear cache.global_clearer --env=prod --ansi
	$(E2E_SYMFONY_DIR)/bin/console cache:clear --env=prod --ansi
	rm -rf $(E2E_SYMFONY_DIR)/var/cache/prod/*

	$(SCOPED_BOX) compile --working-dir=$(E2E_SYMFONY_DIR) --no-parallel --ansi

	php $(E2E_SYMFONY_DIR)/bin/console.phar --version --no-ansi > $(E2E_SYMFONY_ACTUAL_OUTPUT)
	mv -fv $(E2E_SYMFONY_DIR)/bin/console.phar $(E2E_SYMFONY_OUTPUT_DIR)/console.phar

	$(DIFF) $(E2E_SYMFONY_EXPECTED_OUTPUT) $(E2E_SYMFONY_ACTUAL_OUTPUT)

.PHONY: e2e_composer_installed_versions
e2e_composer_installed_versions: $(SCOPED_BOX_BIN) $(E2E_COMPOSER_INSTALLED_DIR)/vendor
	@# Packages an app using Composer\InstalledVersions
	$(SCOPED_BOX) compile --working-dir=$(E2E_COMPOSER_INSTALLED_DIR) --no-parallel --ansi
	
	php $(E2E_COMPOSER_INSTALLED_DIR)/bin/run.phar > $(E2E_COMPOSER_INSTALLED_ACTUAL_OUTPUT)
	mv -fv $(E2E_COMPOSER_INSTALLED_DIR)/bin/run.phar > $(E2E_COMPOSER_INSTALLED_OUTPUT_DIR)/run.phar

	$(DIFF) $(E2E_COMPOSER_INSTALLED_EXPECTED_OUTPUT) $(E2E_COMPOSER_INSTALLED_ACTUAL_OUTPUT)

.PHONY: e2e_phpstorm_stubs
e2e_phpstorm_stubs: $(SCOPED_BOX_BIN)
	@# Project using symbols which should be vetted by PhpStormStubs
	@mkdir -p $(E2E_PHPSTORM_STUBS_OUTPUT_DIR)
	$(SCOPED_BOX) compile --working-dir=$(E2E_PHPSTORM_STUBS_DIR) --no-parallel --ansi

	php $(E2E_PHPSTORM_STUBS_DIR)/index.phar > $(E2E_PHPSTORM_STUBS_ACTUAL_OUTPUT)
	mv -fv $(E2E_PHPSTORM_STUBS_DIR)/index.phar $(E2E_PHPSTORM_STUBS_OUTPUT_DIR)/index.phar

	$(DIFF) $(E2E_PHPSTORM_STUBS_EXPECTED_OUTPUT) $(E2E_PHPSTORM_STUBS_ACTUAL_OUTPUT)


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

fixtures/composer-dump/dir001/vendor: fixtures/composer-dump/dir001/composer.lock
	composer install --ansi --working-dir=fixtures/composer-dump/dir001
	touch -c $@
fixtures/composer-dump/dir001/composer.lock: fixtures/composer-dump/dir001/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=fixtures/composer-dump/dir001 && touch -c $(@)"

fixtures/composer-dump/dir003/vendor: fixtures/composer-dump/dir003/composer.lock
	composer install --ansi --working-dir=fixtures/composer-dump/dir003
	touch -c $@
fixtures/composer-dump/dir003/composer.lock: fixtures/composer-dump/dir003/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=fixtures/composer-dump/dir003 && touch -c $(@)"

$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/vendor: $(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/composer.lock
	composer install --ansi --working-dir=$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)
	touch -c $@
$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/composer.lock: $(E2E_SCOPER_EXPOSE_SYMBOLS_DIR)/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=$(E2E_SCOPER_EXPOSE_SYMBOLS_DIR) && touch -c $(@)"

$(E2E_SYMFONY_DIR)/.env.local.php: $(E2E_SYMFONY_DIR)/vendor $(E2E_SYMFONY_DIR)/.env
	composer dump-env prod --working-dir=$(E2E_SYMFONY_DIR) --ansi
	touch -c $@
$(E2E_SYMFONY_DIR)/vendor:
	composer install --ansi --working-dir=$(E2E_SYMFONY_DIR)
	touch -c $@
$(E2E_SYMFONY_DIR)/composer.lock: $(E2E_SYMFONY_DIR)/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=$(E2E_SYMFONY_DIR) && touch -c $(@)"

$(E2E_COMPOSER_INSTALLED_DIR)/vendor: $(E2E_COMPOSER_INSTALLED_DIR)/composer.lock
	composer install --ansi --working-dir=$(E2E_COMPOSER_INSTALLED_DIR)
	touch -c $@
$(E2E_COMPOSER_INSTALLED_DIR)/composer.lock: $(E2E_COMPOSER_INSTALLED_DIR)/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=$(E2E_COMPOSER_INSTALLED_DIR) && touch -c $(@)"

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

$(E2E_PHP_SETTINGS_CHECKER_EXPECTED_XDEBUG_ENABLED_OUTPUT): $(E2E_PHP_SETTINGS_CHECKER_EXPECTED_OUTPUT_XDEBUG_ENABLED_TEMPLATE)
	./fixtures/php-settings-checker/create-expected-output $(DOCKER_MIN_BOX_XDEBUG_PHP_VERSION_IMAGE_TAG)
	mkdir -p $(E2E_PHP_SETTINGS_CHECKER_OUTPUT_DIR)
	mv -fv $(E2E_PHP_SETTINGS_CHECKER_DIR)/output-xdebug-enabled $(E2E_PHP_SETTINGS_CHECKER_EXPECTED_XDEBUG_ENABLED_OUTPUT)
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
