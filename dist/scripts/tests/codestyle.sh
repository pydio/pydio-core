#!/bin/bash

if [ -e "php-cs-fixer.phar" ]
then
    PHPCSFIXER="php php-cs-fixer.phar"
elif hash php-cs-fixer
then
    PHPCSFIXER="php-cs-fixer"
else
    echo -e "\e[1;31mPlease install or download php-cs-fixer\e[00m";
    echo -e "\e[1;31mhttp://cs.sensiolabs.org/\e[00m";
    exit 1
fi

PHPCSFIXERARGS="fix -v --fixers=indentation,linefeed,trailing_spaces,visibility,short_tag,braces,php_closing_tag,controls_spaces,eof_ending"

echo -e "\e[1;34mChecking Formatting/coding standards\e[00m"
$PHPCSFIXER $PHPCSFIXERARGS --dry-run .
rc=$?
if [[ $rc == 0 ]]
then
    echo -e "\e[1;32mFormatting is OK\e[00m"
else
    echo -e "\e[1;31mPlease check code Formatting\e[00m"
    echo -e "\e[1;31m$PHPCSFIXER $PHPCSFIXERARGS .\e[00m"
    exit 1
fi

