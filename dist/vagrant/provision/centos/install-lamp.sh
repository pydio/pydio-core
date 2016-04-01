#!/bin/bash

# ----------------
# UI variables
# ----------------
#debconf-set-selections <<< 'mysql-server mysql-server/root_password password password'
#debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password password'

# ----------------
# APT install
# ----------------
echo -ne "Adding dependencies repositories..."
echo -ne "DONE\r"

echo -ne "Installing dependencies..."
sudo yum install -y epel-release
sudo yum install -y zip
sudo yum install -y httpd
sudo yum install -y php54

# If php54 is not part of the yum bank, then we add more repos
if [ $? -gt 0 ]; then
    sudo rpm -ivh https://www.softwarecollections.org/en/scls/rhscl/php54/epel-6-x86_64/download/rhscl-php54-epel-6-x86_64.noarch.rpm
    sudo rpm -ivh https://www.softwarecollections.org/en/scls/remi/php54more/epel-6-x86_64/download/remi-php54more-epel-6-x86_64.noarch.rpm
fi

sudo yum install -y php54 php54-php php54-php-common php54-php-cli php54-php-mcrypt php54-php-mysqlnd

sudo yum install -y mysql-server
echo -ne "DONE\r"
