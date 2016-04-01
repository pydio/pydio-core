#!/bin/bash

# ------------------------
# Installing dependencies
# ------------------------
echo -ne "Installing GIT install dependencies..."
sudo apt-get install -y git-core npm > /dev/null 2>&1
sudo ln -sf /usr/bin/nodejs /usr/bin/node
sudo npm install --silent -g grunt-cli
echo -ne "DONE\r"

echo -ne "Enabling GIT access..."
mkdir -p /root/.ssh && touch /root/.ssh/known_hosts && ssh-keyscan -H "github.com" >> /root/.ssh/known_hosts && chmod 600 /root/.ssh/known_hosts
echo -ne "DONE\r"
