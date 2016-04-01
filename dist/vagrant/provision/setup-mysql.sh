#!/bin/bash

# ----------------
# Database install
# ----------------
echo -ne "Installing database..."
mysql -uroot -ppassword -e "drop database if exists pydio;"  > /dev/null 2>&1
mysql -uroot -ppassword -e "create database pydio;" > /dev/null 2>&1
mysql -uroot -ppassword -e "grant all on pydio.* to 'pydio'@'localhost' identified by 'password';" > /dev/null 2>&1
sudo /etc/init.d/mysql restart > /dev/null 2>&1
echo -ne "DONE\r"
