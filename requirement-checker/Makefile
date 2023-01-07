# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

OS := $(shell uname)
ERROR_COLOR := "\033[41m"
NO_COLOR := "\033[0m"


PHPUNIT_BIN = vendor/bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)

ACTUAL_TERMINAL_DIFF = tests/TerminalCompatibility/actual_diff
EXPECTED_TERMINAL_DIFF = tests/TerminalCompatibility/expected_diff
ORIGINAL_TERMINAL = ../vendor-bin/requirement-checker/vendor/symfony/console/Terminal.php

PHP_CS_FIXER_BIN = ../vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
PHP_CS_FIXER = $(PHP_CS_FIXER_BIN)

PHAR_SRC_FILES := $(shell find src bin/check-requirements.php box.json.dist scoper.inc.php)
PHAR = dist/check-requirements.phar
PHAR_EXTRACT = ../res/requirement-checker

BOX_BIN = ../bin/box
BOX = $(BOX_BIN)


.DEFAULT_GOAL := help


.PHONY: help
help:
	@printf "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'


#
# Commands
#---------------------------------------------------------------------------

.PHONY: check
check:	 ## Runs all checks
check: cs cs_lint test

.PHONY: clean
clean: 	 ## Cleans all created artifacts
clean:
	rm -rf \
		dist \
		$(ACTUAL_TERMINAL_DIFF) \
		bin/check-requirements.phar \
		$(PHAR) \
		.phpunit.result.cache \
		.requirement-checker \
		|| true
	$(MAKE) dist


.PHONY: dump
dump:	 ## Dumps the requirement-checker
dump:
	rm -rf $(PHAR) $(PHAR_EXTRACT) || true
	$(MAKE) _dump

.PHONY: _dump
_dump: $(PHAR_EXTRACT)


#
# CS
#---------------------------------------------------------------------------


.PHONY: cs
cs:	 ## Fixes CS
cs: gitignore_sort composer_normalize php_cs_fixer

.PHONY: cs_lint
cs_lint: ## Checks CS
cs_lint: composer_normalize_lint php_cs_fixer_lint

.PHONY: gitignore_sort
gitignore_sort:
	LC_ALL=C sort -u .gitignore -o .gitignore

.PHONY: composer_normalize
composer_normalize: composer.json vendor
	composer normalize

.PHONY: composer_normalize_lint
composer_normalize_lint: composer.json vendor
	composer normalize --dry-run

.PHONY: php_cs_fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix --verbose

.PHONY: php_cs_fixer_lint
php_cs_fixer_lint: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix --verbose --dry-run --diff


#
# Tests
#---------------------------------------------------------------------------

.PHONY: test
test:	 ## Runs all the tests
test: phpunit terminal_copy

.PHONY: phpunit
phpunit:
phpunit: $(PHPUNIT_BIN) vendor
	$(PHPUNIT)

.PHONY: terminal_copy
terminal_copy: $(ACTUAL_TERMINAL_DIFF)
	diff --ignore-all-space --side-by-side --suppress-common-lines $(EXPECTED_TERMINAL_DIFF) $(ACTUAL_TERMINAL_DIFF)


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
	composer install
	touch -c vendor
	touch -c $(PHPUNIT_BIN)

vendor: composer.lock
	$(MAKE) vendor_install
composer.lock: composer.json
	@echo $(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)
	@echo "$$ composer update --lock && touch -c $(@)"

$(PHPUNIT_BIN): composer.lock
	$(MAKE) --always-make vendor_install
	touch -c $@

$(ACTUAL_TERMINAL_DIFF): src/Terminal.php vendor $(ORIGINAL_TERMINAL)
	(diff --ignore-all-space --side-by-side --suppress-common-lines $(ORIGINAL_TERMINAL) src/Terminal.php || true) > $@
	touch -c $@

$(ORIGINAL_TERMINAL):
	cd ..; $(MAKE) requirement_checker_install
	touch -c $@

$(PHP_CS_FIXER_BIN):
	cd ..; $(MAKE) php_cs_fixer_install
	touch -c $@

dist:
	mkdir -p dist
	touch dist/.gitkeep
	touch -c $@

$(PHAR): $(PHAR_SRC_FILES) $(BOX_BIN)
	$(BOX) compile --no-parallel
	touch -c $@

$(PHAR_EXTRACT): $(PHAR) $(BOX_BIN)
	$(BOX) extract $(PHAR) $(PHAR_EXTRACT)
	touch -c $@

$(BOX_BIN):
	cd ..; $(MAKE) --file=Makefile bin/box
