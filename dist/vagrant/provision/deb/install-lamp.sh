#!/bin/bash

# ----------------
# Variables
# ----------------
sudo locale-gen "en_US.UTF-8"
debconf-set-selections <<< 'mysql-server mysql-server/root_password password password'
debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password password'

# ----------------
# APT install
# ----------------
echo -ne "Adding dependencies repositories..."
echo "deb http://httpredir.debian.org/debian jessie main" | sudo tee -a /etc/apt/sources.list > /dev/null
echo "deb-src http://httpredir.debian.org/debian jessie main" | sudo tee -a /etc/apt/sources.list > /dev/null

echo "deb http://httpredir.debian.org/debian jessie-updates main" | sudo tee -a /etc/apt/sources.list > /dev/null
echo "deb-src http://httpredir.debian.org/debian jessie-updates main" | sudo tee -a /etc/apt/sources.list > /dev/null

echo "deb http://security.debian.org/ jessie/updates main" | sudo tee -a /etc/apt/sources.list > /dev/null
echo "deb-src http://security.debian.org/ jessie/updates main" | sudo tee -a /etc/apt/sources.list > /dev/null

echo "deb http://downloads.sourceforge.net/project/ubuntuzilla/mozilla/apt all main" | sudo tee -a /etc/apt/sources.list > /dev/null
echo -ne "DONE\r"

echo -ne "Installing dependencies..."
sudo apt-get update > /dev/null 2>&1
sudo apt-get install -y zip > /dev/null 2>&1
sudo apt-get install -y apache2 > /dev/null 2>&1
sudo apt-get install -y php5-common libapache2-mod-php5 php5-cli php5-mcrypt > /dev/null 2>&1
#sudo apt-get install -y --force-yes firefox xvfb
#sudo apt-get install -y libfontconfig1 libxrender1 libasound2 libdbus-glib-1-2 libxcomposite1 libgtk2.0-0
sudo apt-get install -y mysql-server php5-mysql > /dev/null 2>&1
echo -ne "DONE\r"

exit 0
