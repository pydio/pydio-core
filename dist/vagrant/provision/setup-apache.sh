#!/bin/bash

user=$1
group=$2
root=$3
conf=$4

# ----------------
# Apache setup
# ----------------
echo -ne "Enabling and restarting apache..."
sudo chown -R $1:$2 $3

sudo a2enmod rewrite> /dev/null 2>&1
sudo sed -i 's/AllowOverride None/AllowOverride All/g' $4 > /dev/null 2>&1
sudo service apache2 restart > /dev/null 2>&1

echo -ne "DONE\r"
