#!/bin/bash

#To add this script as a pre-commit hooks
# cd into your repo root directory
# ln -s ../../dist/scripts/tests/pre-commit.sh .git/hooks/pre-commit

#go to repo root
cd "$(git rev-parse --show-toplevel)"

EXIT=0

echo -e "\e[1;34mChecking syntax error in modified php files\e[00m"
output=$(git diff --cached --name-only -- *.php | xargs -n1 php --syntax-check | grep -v 'No syntax errors detected in')
if [[ $output ]]
then
    echo -e '\e[00;31mPlease check files syntax\e[00m'
    EXIT=1
else
    echo -e "\e[1;32mSyntax is OK\e[00m"
fi

echo -e "\e[1;34mChecking mandatory formatting/coding standards\e[00m"
if hash php-cs-fixer
then
    FIXERS1="indentation,linefeed,trailing_spaces,short_tag,braces,php_closing_tag,controls_spaces,eof_ending,visibility"
    PHPCS="php-cs-fixer fix -v --fixers="
    git diff --cached --name-only | xargs -n1 $PHPCS$FIXERS1 --dry-run
    rc=$?
    if [[ $rc == 0 ]]
    then
        echo -e "\e[1;32mFormatting is OK\e[00m"
    else
        echo -e "\e[1;31mPlease check code Formatting\e[00m"
        echo -e "\e[1;31mYou can run\e[00m"
        echo -e "\e[1;31m$PHPCS$FIXERS1 <filename>\e[00m"
        echo -e "\e[1;31mto correct the formatting (please re-check the result)\e[00m"
        EXIT=1
    fi
else
    echo -e "\e[1;31mphp-cs-fixer must be in your path to run this test\e[00m";
fi

if [[ $EXIT != 0 ]]
then
    echo ""
    echo -e "\e[1;31mYou can bypass these tests with 'git commit --no-verify'\e[00m"
fi

exit $EXIT
