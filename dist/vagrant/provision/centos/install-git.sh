#!/bin/bash

# ------------------------
# Installing dependencies
# ------------------------
echo -ne "Installing GIT install dependencies..."
sudo yum -y install git-core npm

if [ -f "/usr/bin/nodejs" -a ! -f "/usr/bin/node" ]; then
    sudo ln -sf /usr/bin/nodejs /usr/bin/node
fi
sudo npm install --silent -g grunt-cli
echo -ne "DONE\r"

echo -ne "Enabling GIT access..."
sudo mkdir -p /root/.ssh && sudo touch /root/.ssh/known_hosts && ssh-keyscan -H "github.com" | sudo tee -a /root/.ssh/known_hosts && sudo chmod 600 /root/.ssh/known_hosts
echo -ne "DONE\r"
