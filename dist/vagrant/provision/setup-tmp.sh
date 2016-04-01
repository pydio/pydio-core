#!/bin/bash

# ----------------
# TMP Dirs
# ----------------
echo -ne "Preparing tmp files..."
shopt -s dotglob
sudo rm -rf /var/www/html/* /tmp/pydio
sudo mkdir -p /var/www/html /tmp/pydio
sudo chmod 777 /tmp/pydio
echo -ne "DONE\r"
