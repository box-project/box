# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

OS := $(shell uname)
ERROR_COLOR := $(shell tput setab 1)
YELLOW_COLOR := $(shell tput setaf 3)
NO_COLOR := $(shell tput sgr0)


PHPUNIT_BIN = vendor/bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)

ACTUAL_TERMINAL_DIFF = tests/TerminalCompatibility/actual_diff
EXPECTED_TERMINAL_DIFF = tests/TerminalCompatibility/expected_diff
ORIGINAL_TERMINAL = ../vendor-bin/requirement-checker/vendor/symfony/console/Terminal.php

PHP_CS_FIXER_BIN = ../vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
PHP_CS_FIXER = $(PHP_CS_FIXER_BIN)

PHAR_PREFIX = dist/prefix
PHAR_SRC_FILES := $(shell find src bin/check-requirements.php box.json.dist scoper.inc.php) $(wildcard $(PHAR_PREFIX))
PHAR = dist/check-requirements.phar
PHAR_EXTRACT = ../res/requirement-checker

BOX_BIN = ../bin/box
BOX = $(BOX_BIN)

RECTOR_BIN = ../vendor-bin/rector/vendor/bin/rector
RECTOR = $(RECTOR_BIN)

DIFF = diff --strip-trailing-cr --ignore-all-space --side-by-side --suppress-common-lines


.DEFAULT_GOAL := help


.PHONY: help
help:
	@printf "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'


#
# Commands
#---------------------------------------------------------------------------

.PHONY: check
check:	 	## Runs all checks
check: cs autoreview test

.PHONY: clean
clean: 	 	## Cleans all created artifacts
clean:
	rm -rf \
		dist \
		$(ACTUAL_TERMINAL_DIFF) \
		$(PHAR) \
		.phpunit.result.cache \
		.requirement-checker \
		|| true
	@# Obsolete entries; Only relevant to someone who still has old artifacts locally
	@rm -rf \
		bin/check-requirements.phar \
		.phpunit.result.cache \
		.requirement-checker \
		fixtures/fail-complete/actual-output \
		fixtures/fail-complete/expected-output-725 \
		fixtures/fail-complete/expected-output-725-skipped \
		fixtures/fail-complete/expected-output-current-min-php \
		fixtures/fail-complete/index.phar \
		fixtures/pass-complete/actual-output \
		fixtures/pass-complete/expected-output-725 \
		fixtures/pass-complete/expected-output-current-min-php \
		fixtures/pass-complete/index.phar \
		fixtures/pass-no-config/actual-output \
		fixtures/pass-no-config/expected-output-725 \
		fixtures/pass-no-config/expected-output-725-skipped \
		fixtures/pass-no-config/expected-output-current-min-php \
		fixtures/pass-no-config/index.phar \
		|| true
	$(MAKE) dist


.PHONY: dump
dump:	 	## Dumps the requirement-checker
dump:
	rm -rf $(PHAR) $(PHAR_EXTRACT) || true
	$(MAKE) _dump

.PHONY: _dump
_dump: $(PHAR_EXTRACT)

#
# AutoReview
#---------------------------------------------------------------------------

.PHONY: autoreview
autoreview:	## AutoReview checks
autoreview: cs_lint phpunit_autoreview

.PHONY: phpunit_autoreview
phpunit_autoreview:
phpunit_autoreview: $(PHPUNIT_BIN) vendor
	$(PHPUNIT) --testsuite="AutoReview" --colors=always


#
# CS
#---------------------------------------------------------------------------


.PHONY: cs
cs:	 	## Fixes CS
cs: gitignore_sort composer_normalize rector php_cs_fixer

.PHONY: cs_lint
cs_lint: 	## Checks CS
cs_lint: composer_normalize_lint rector_lint php_cs_fixer_lint

.PHONY: gitignore_sort
gitignore_sort:
	LC_ALL=C sort -u .gitignore -o .gitignore

.PHONY: composer_normalize
composer_normalize: composer.json vendor
	composer normalize

.PHONY: composer_normalize_lint
composer_normalize_lint: composer.json vendor
	composer normalize --ansi --dry-run

.PHONY: php_cs_fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix --ansi --verbose

.PHONY: php_cs_fixer_lint
php_cs_fixer_lint: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix --ansi --verbose --dry-run --diff

.PHONY: rector
rector: rector_src rector_tests

.PHONY: rector_src
rector_src: $(RECTOR_BIN)
	$(RECTOR) --config=rector-src.php

.PHONY: rector_tests
rector_tests: $(RECTOR_BIN)
	$(RECTOR) --config=rector-tests.php

.PHONY: rector_lint
rector_lint: rector_lint_src rector_lint_tests

.PHONY: rector_lint_src
rector_lint_src: $(RECTOR_BIN)
	$(RECTOR) --config=rector-src.php --dry-run

.PHONY: rector_lint_tests
rector_lint_tests: $(RECTOR_BIN)
	$(RECTOR) --config=rector-tests.php --dry-run


#
# Tests
#---------------------------------------------------------------------------

.PHONY: test
test:		## Runs all the tests
test: test_unit test_e2e


#
# Unit Tests
#---------------------------------------------------------------------------

.PHONY: test_unit
test_unit: phpunit terminal_copy

.PHONY: phpunit
phpunit:
phpunit: $(PHPUNIT_BIN) vendor
	$(PHPUNIT) --testsuite="UnitTests" --colors=always

.PHONY: terminal_copy
terminal_copy: $(ACTUAL_TERMINAL_DIFF)
	$(DIFF) $(EXPECTED_TERMINAL_DIFF) $(ACTUAL_TERMINAL_DIFF)


#
# E2E Tests
#---------------------------------------------------------------------------

include Makefile.e2e

.PHONY: test_e2e
test_e2e: docker_images _test_e2e_pass_no_config_min_composer_php _test_e2e_pass_no_config_min_box_php _test_e2e_pass_complete_min_composer_php _test_e2e_pass_complete_min_box_php _test_e2e_fail_complete_min_composer_php _test_e2e_fail_complete_min_box_php _test_e2e_skip_min_composer_php _test_e2e_pass_complete_requirement_checker_not_disabled _test_e2e_fail_conflict


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
	touch -c $(PHPUNIT_BIN)

vendor: composer.lock
	$(MAKE) vendor_install
composer.lock: composer.json
	@echo "$(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)"
	@echo "$$ composer update --lock && touch -c $(@)"

$(PHPUNIT_BIN): composer.lock
	$(MAKE) --always-make vendor_install
	touch -c $@

$(ACTUAL_TERMINAL_DIFF): src/Terminal.php vendor $(ORIGINAL_TERMINAL)
	$(DIFF) $(ORIGINAL_TERMINAL) src/Terminal.php > $@ || true
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

.PHONY: dump_prefix
dump_prefix:
	rm $(PHAR_PREFIX) || true
	$(MAKE) $(PHAR_PREFIX)

$(PHAR_PREFIX): bin/dump-prefix.php
	php bin/dump-prefix.php

$(PHAR): $(PHAR_SRC_FILES) $(BOX_BIN) $(PHAR_PREFIX)
	composer config autoloader-suffix $(shell cat $(PHAR_PREFIX))
	$(BOX) compile --no-parallel
	composer config autoloader-suffix --unset
	touch -c $@

$(PHAR_EXTRACT): $(PHAR) $(BOX_BIN)
	$(BOX) extract $(PHAR) $(PHAR_EXTRACT)
	rm -rf $(PHAR_EXTRACT)/.phar
	touch -c $@

$(BOX_BIN):
	cd ..; $(MAKE) --file=Makefile bin/box

$(RECTOR_BIN):
	cd ..; $(MAKE) --file=Makefile rector_install
