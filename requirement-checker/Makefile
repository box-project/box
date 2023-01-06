# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

OS := $(shell uname)
ERROR_COLOR := "\033[41m"
NO_COLOR := "\033[0m"


PHPUNIT_BIN = bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)

TERMINAL_DIFF = actual_terminal_diff


.DEFAULT_GOAL := help


.PHONY: help
help:
	@printf "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'


#
# Commands
#---------------------------------------------------------------------------

.PHONY: check
check:	## Runs all checks
check: cs cs_lint test

.PHONY: clean
clean: 	 		 ## Cleans all created artifacts
clean:
	@echo TODO

.PHONY: cs
cs:	 ## Fixes CS
cs: gitignore_sort

.PHONY: cs_lint
cs_lint: ## Checks CS
cs_lint:

.PHONY: gitignore_sort
gitignore_sort:
	LC_ALL=C sort -u .gitignore -o .gitignore


#
# Tests
#---------------------------------------------------------------------------

.PHONY: test
test:		  	 ## Runs all the tests
test: phpunit terminal_copy

.PHONY: phpunit
phpunit:
phpunit: $(PHPUNIT_BIN) vendor
	$(PHPUNIT)

.PHONY: terminal_copy
terminal_copy: $(TERMINAL_DIFF)
	diff --ignore-all-space --side-by-side --suppress-common-lines requirement-checker/expected_terminal_diff requirement-checker/actual_terminal_diff


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
	touch -c $(COMPOSER_BIN_PLUGIN_VENDOR)
	touch -c $(PHPUNIT_BIN)

vendor: composer.lock
	$(MAKE) vendor_install
composer.lock: composer.json
	@echo $(ERROR_COLOR)$(@) is not up to date. You may want to run the following command:$(NO_COLOR)
	@echo "$$ composer update --lock && touch -c $(@)"

$(PHPUNIT_BIN): composer.lock
	$(MAKE) --always-make vendor_install
	touch -c $@

$(TERMINAL_DIFF): src/Terminal.php vendor $(wildcard vendor/symfony/console/Terminal.php)
	(diff --ignore-all-space --side-by-side --suppress-common-lines vendor/symfony/console/Terminal.php src/Terminal.php || true) > $@
	touch -c $@
