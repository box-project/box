# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

OS := $(shell uname)
ERROR_COLOR := $(shell tput setab 1)
YELLOW_COLOR := $(shell tput setaf 3)
NO_COLOR := $(shell tput sgr0)

COMPOSER_BIN_PLUGIN_VENDOR = vendor/bamarni/composer-bin-plugin

REQUIREMENT_CHECKER_EXTRACT = res/requirement-checker

BOX_BIN = bin/box
BOX = $(BOX_BIN)

SCOPED_BOX_BIN = bin/box.phar
TMP_SCOPED_BOX_BIN = bin/_box.phar
SCOPED_BOX = $(SCOPED_BOX_BIN)
SCOPED_BOX_DEPS = bin/box bin/box.bat $(shell find src res) box.json.dist scoper.inc.php vendor

DEFAULT_STUB = dist/default_stub.php

COVERAGE_DIR = dist/coverage
COVERAGE_XML_DIR = $(COVERAGE_DIR)/coverage-xml
COVERAGE_JUNIT = $(COVERAGE_DIR)/phpunit.junit.xml
COVERAGE_HTML_DIR = $(COVERAGE_DIR)/html

PHPUNIT_BIN = vendor/bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)
PHPUNIT_TEST_SRC = $(DEFAULT_STUB) $(REQUIREMENT_CHECKER_EXTRACT) fixtures/composer-dump/dir001/vendor fixtures/composer-dump/dir002/vendor fixtures/composer-dump/dir003/vendor
PHPUNIT_COVERAGE_INFECTION = XDEBUG_MODE=coverage php -dphar.readonly=0 $(PHPUNIT) --colors=always --coverage-xml=$(COVERAGE_XML_DIR) --log-junit=$(COVERAGE_JUNIT) --testsuite=Tests
PHPUNIT_COVERAGE_HTML = XDEBUG_MODE=coverage php -dphar.readonly=0 $(PHPUNIT) --colors=always --coverage-html=$(COVERAGE_HTML_DIR)

INFECTION_BIN = vendor-bin/infection/vendor/bin/infection
INFECTION := SYMFONY_DEPRECATIONS_HELPER="disabled=1" php -dzend.enable_gc=0 $(INFECTION_BIN) --skip-initial-tests --coverage=$(COVERAGE_DIR) --only-covered --show-mutations --min-msi=100 --min-covered-msi=100 --ansi --threads=max --show-mutations
INFECTION_CI = $(eval INFECTION_CI := $(INFECTION) ${INFECTION_FLAGS})$(INFECTION_CI)
INFECTION_WITH_INITIAL_TESTS := SYMFONY_DEPRECATIONS_HELPER="disabled=1" php -dzend.enable_gc=0 $(INFECTION_BIN) --only-covered --show-mutations --min-msi=100 --min-covered-msi=100 --ansi --threads=max --show-mutations
INFECTION_SRC := $(shell find src tests) phpunit.xml.dist

PHP_CS_FIXER_BIN = vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
PHP_CS_FIXER = $(PHP_CS_FIXER_BIN)

PHPBENCH_BIN = vendor-bin/phpbench/vendor/bin/phpbench
PHPBENCH = $(PHPBENCH_BIN)
PHPBENCH_WITH_COMPACTORS_VENDOR_DIR = fixtures/bench/with-compactors/vendor
PHPBENCH_WITHOUT_COMPACTORS_VENDOR_DIR = fixtures/bench/without-compactors/vendor
PHPBENCH_REQUIREMENT_CHECKER_VENDOR_DIR = fixtures/bench/requirement-checker/vendor

RECTOR_BIN = vendor-bin/rector/vendor/bin/rector
RECTOR = $(RECTOR_BIN)

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

# Needs to be included before the clean command.
include Makefile.e2e

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
		Dockerfile \
		$(SCOPED_BOX_BIN) \
		$(TMP_SCOPED_BOX_BIN) \
		fixtures/build/*/.box_dump \
		fixtures/build/*/vendor \
		fixtures/build/dir010/index.phar \
		fixtures/build/dir012/bin/console.phar \
		$(E2E_PHP_SETTINGS_CHECKER_DIR)/index.phar \
		$(E2E_SYMFONY_DIR)/var \
		$(E2E_SYMFONY_DIR)/.env.local.php \
		$(DEFAULT_STUB) \
		fixtures/composer-dump/*/vendor \
		 || true
	@# Obsolete entries; Only relevant to someone who still has old artifacts locally
	@rm -rf \
		.php-cs-fixer.cache \
		.phpunit.result.cache \
		box \
		box.phar \
		fixtures/default_stub.php \
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
	$(MAKE) dist


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
autoreview: cs_lint composer_validate box_validate phpunit_autoreview

.PHONY: composer_validate
composer_validate:
	composer validate --strict --ansi

.PHONY: box_validate
box_validate: $(BOX_BIN)
	$(BOX) validate --ansi

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
root_cs: gitignore_sort composer_normalize rector php_cs_fixer

.PHONY: requirement_checker_cs
requirement_checker_cs:
	cd requirement-checker; $(MAKE) --file=Makefile cs


.PHONY: cs_lint
cs_lint: 	 	 ## Lints CS
cs_lint: root_cs_lint requirement_checker_cs_lint

.PHONY: root_cs_lint
root_cs_lint: composer_normalize_lint rector_lint php_cs_fixer_lint

.PHONY: requirement_checker_cs_lint
requirement_checker_cs_lint:
	cd requirement-checker; $(MAKE) --file=Makefile cs_lint

.PHONY: php_cs_fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN) dist
	$(PHP_CS_FIXER) fix --ansi --verbose

.PHONY: php_cs_fixer_lint
php_cs_fixer_lint: $(PHP_CS_FIXER_BIN) dist
	$(PHP_CS_FIXER) fix --ansi --verbose --dry-run --diff

.PHONY: rector
rector: $(RECTOR_BIN)
	$(RECTOR)

.PHONY: rector_lint
rector_lint: $(RECTOR_BIN)
	$(RECTOR) --dry-run

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

.PHONY: phpunit
phpunit: $(PHPUNIT_BIN) $(PHPUNIT_TEST_SRC)
	$(PHPUNIT) --testsuite=Tests --colors=always

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

.PHONY: phpbench
phpbench: 		 ## Runs PHPBench
phpbench: $(PHPBENCH_BIN) \
			$(PHPBENCH_WITH_COMPACTORS_VENDOR_DIR) \
			$(PHPBENCH_WITHOUT_COMPACTORS_VENDOR_DIR) \
			$(PHPBENCH_REQUIREMENT_CHECKER_VENDOR_DIR)
	$(MAKE) _phpbench

.PHONY: _phpbench
_phpbench:
	$(PHPBENCH) run tests/Benchmark --report=benchmark --dump-file=dist/bench-result.xml
	php bin/bench-test.php

.PHONY: phpbench_pr
phpbench_pr: $(PHPBENCH_BIN) \
		$(PHPBENCH_WITH_COMPACTORS_VENDOR_DIR) \
		$(PHPBENCH_WITHOUT_COMPACTORS_VENDOR_DIR) \
		$(PHPBENCH_REQUIREMENT_CHECKER_VENDOR_DIR)
	$(PHPBENCH) run tests/Benchmark --report=benchmark --dump-file=dist/bench-result.xml --ref=main
	php bin/bench-test.php

.PHONY: phpbench_main
phpbench_main: $(PHPBENCH_BIN) \
		$(PHPBENCH_WITH_COMPACTORS_VENDOR_DIR) \
		$(PHPBENCH_WITHOUT_COMPACTORS_VENDOR_DIR) \
		$(PHPBENCH_REQUIREMENT_CHECKER_VENDOR_DIR)
	$(PHPBENCH) run tests/Benchmark --report=benchmark --tag=main


#---------------------------------------------------------------------------

.PHONY: test_e2e
test_e2e: e2e_scoper_alias \
	e2e_scoper_expose_symbols \
	e2e_php_settings_checker_no_restart \
	e2e_php_settings_checker_xdebug_enabled \
	e2e_php_settings_checker_readonly_enabled \
	e2e_php_settings_checker_memory_limit_lower \
	e2e_php_settings_checker_memory_limit_higher \
	e2e_symfony \
	e2e_composer_installed_versions \
	e2e_phpstorm_stubs \
	e2e_dockerfile \
	e2e_dockerfile_no_extension \
	e2e_custom_composer_bin \
	e2e_symfony_runtime \
	e2e_reproducible_build


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

.PHONY: website_check
website_check:		 ## Runs various checks for the website
website_check: markdownlint lychee website_build

.PHONY: markdownlint
markdownlint:
	@echo "$(YELLOW_COLOR)Ensure you have the nodejs & npm installed. For more information, check:$(NO_COLOR)"
	@# To keep in sync with .github/workflows/gh-pages.yaml#check-links
	npx markdownlint-cli2 "*.md|doc/**/*.md"

.PHONY: lychee
lychee:
	@echo "$(YELLOW_COLOR)Ensure you have the lychee command installed. For more information, check:$(NO_COLOR)"
	@echo "https://github.com/lycheeverse/lychee"
	@# To keep in sync with .github/workflows/gh-pages.yaml#check-links
	lychee --verbose --no-progress '*.md' 'docs/**/*.md'

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
	$(MAKE) _vendor_install

.PHONY: _vendor_install
_vendor_install:
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

.PHONY: phpbench_install
phpbench_install: $(PHPBENCH_BIN) \
			$(PHPBENCH_WITH_COMPACTORS_VENDOR_DIR) \
			$(PHPBENCH_WITHOUT_COMPACTORS_VENDOR_DIR) \
			$(PHPBENCH_REQUIREMENT_CHECKER_VENDOR_DIR)

$(PHPBENCH_BIN): vendor-bin/phpbench/vendor
	touch -c $@
vendor-bin/phpbench/vendor: vendor-bin/phpbench/composer.lock $(COMPOSER_BIN_PLUGIN_VENDOR)
	composer bin phpbench install
	touch -c $@
vendor-bin/phpbench/composer.lock: vendor-bin/phpbench/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer bin phpbench update --lock && touch -c $(@)"

.PHONY: rector_install
rector_install: $(RECTOR_BIN)

$(RECTOR_BIN): vendor-bin/rector/vendor
	touch -c $@
vendor-bin/rector/vendor: vendor-bin/rector/composer.lock $(COMPOSER_BIN_PLUGIN_VENDOR)
	composer bin rector install
	touch -c $@
vendor-bin/rector/composer.lock: vendor-bin/rector/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer bin rector update --lock && touch -c $(@)"

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

$(COVERAGE_XML_DIR): $(PHPUNIT_BIN) $(PHPUNIT_TEST_SRC) $(INFECTION_SRC)
	@# Do not include dist in the pre-requisite: we do want it to exist but its timestamp should not be tracked
	$(MAKE) dist
	$(MAKE) phpunit_coverage_infection

$(COVERAGE_JUNIT): $(PHPUNIT_BIN) $(PHPUNIT_TEST_SRC) $(INFECTION_SRC)
	@# Do not include dist in the pre-requisite: we do want it to exist but its timestamp should not be tracked
	$(MAKE) dist
	$(MAKE) phpunit_coverage_infection

fixtures/composer-dump/dir001/vendor: fixtures/composer-dump/dir001/composer.lock
	composer install --ansi --working-dir=fixtures/composer-dump/dir001
	touch -c $@
fixtures/composer-dump/dir001/composer.lock: fixtures/composer-dump/dir001/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=fixtures/composer-dump/dir001 && touch -c $(@)"

fixtures/composer-dump/dir002/vendor: fixtures/composer-dump/dir002/composer.lock
	composer install --ansi --working-dir=fixtures/composer-dump/dir002
	touch -c $@
fixtures/composer-dump/dir002/composer.lock: fixtures/composer-dump/dir002/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=fixtures/composer-dump/dir002 && touch -c $(@)"

fixtures/composer-dump/dir003/vendor: fixtures/composer-dump/dir003/composer.lock
	composer install --ansi --working-dir=fixtures/composer-dump/dir003
	touch -c $@
fixtures/composer-dump/dir003/composer.lock: fixtures/composer-dump/dir003/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock --working-dir=fixtures/composer-dump/dir003 && touch -c $(@)"

.PHONY: generate_default_stub
generate_default_stub: $(DEFAULT_STUB)

$(DEFAULT_STUB): bin/generate_default_stub
	php -dphar.readonly=0 bin/generate_default_stub
	touch -c $@

.PHONY: _dump_requirement_checker
_dump_requirement_checker: $(REQUIREMENT_CHECKER_EXTRACT)

$(REQUIREMENT_CHECKER_EXTRACT):
	cd requirement-checker; $(MAKE) --file=Makefile _dump

$(SCOPED_BOX_BIN): $(SCOPED_BOX_DEPS)
	@echo "$(YELLOW_COLOR)Compile Box.$(NO_COLOR)"
	@# Use parallelization
	$(BOX) compile --ansi

	rm $(TMP_SCOPED_BOX_BIN) || true
	mv -v bin/box.phar $(TMP_SCOPED_BOX_BIN)

	@echo "$(YELLOW_COLOR)Compile Box with the isolated Box PHAR.$(NO_COLOR)"
	php $(TMP_SCOPED_BOX_BIN) compile --ansi

	mv -fv bin/box.phar box

	@echo "$(YELLOW_COLOR)Test the PHAR which has been created by the isolated PHAR.$(NO_COLOR)"
	./box compile --ansi

	mv -fv box bin/box.phar
	rm $(TMP_SCOPED_BOX_BIN)

	touch -c $@


.PHONY: requirement_checker_install
requirement_checker_install: vendor-bin/requirement-checker/vendor

vendor-bin/requirement-checker/vendor: vendor-bin/requirement-checker/composer.lock $(COMPOSER_BIN_PLUGIN_VENDOR)
	composer bin requirement-checker install
	touch -c $@
vendor-bin/requirement-checker/composer.lock: vendor-bin/requirement-checker/composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer bin requirement-checker update --lock && touch -c $(@)"

$(PHPBENCH_WITH_COMPACTORS_VENDOR_DIR):
	composer install --working-dir=$$(dirname $@)
	touch -c $@

$(PHPBENCH_WITHOUT_COMPACTORS_VENDOR_DIR):
	composer install --working-dir=$$(dirname $@)
	touch -c $@

$(PHPBENCH_REQUIREMENT_CHECKER_VENDOR_DIR):
	composer install --working-dir=$$(dirname $@) --ignore-platform-reqs
	touch -c $@

dist:
	mkdir -p dist
	touch dist/.gitkeep
	touch -c $@
