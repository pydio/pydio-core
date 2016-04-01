#!/usr/bin/env bash

echo -e "-------------------------"
echo -e " Populate data in system"
echo -e "-------------------------"

# ----------------
# APT install
# ----------------
sudo apt-get update > /dev/null 2>&1
sudo apt-get install -y git-core python
sudo curl "https://bootstrap.pypa.io/get-pip.py" -o "get-pip.py"
sudo python get-pip.py

# ----------------
# TMP Dirs
# ----------------
sudo rm -rf /tmp/pydio
sudo mkdir -p /tmp/pydio
sudo chmod 777 /tmp/pydio
cd /tmp/pydio

# ----------------
# Git install
# ----------------
git clone https://github.com/pydio/pydio-integration-tests.git
cd pydio-integration-tests

cp configs/server.sample.json configs/server.0.json
cp configs/workspace.sample.json configs/workspace.0.json

sed -i 's/"user":[^,]*/"user":"admin"/g' configs/server.0.json
sed -i 's/"password":[^,]*/"password":"password"/g' configs/server.0.json
sed -i 's/"ADMIN_USER_PASS":[^,]*/"ADMIN_USER_PASS":"password"/g' configs/server.0.json
sed -i 's/"ADMIN_USER_PASS2":[^,]*/"ADMIN_USER_PASS2":"password"/g' configs/server.0.json
sed -i 's/DB_USER/pydio/g' configs/server.0.json
sed -i 's/DB_PASS/password/g' configs/server.0.json
sed -i 's/DB_HOST/localhost/g' configs/server.0.json
sed -i 's/DB_NAME/pydio/g' configs/server.0.json

# ----------------
# PIP Install
# ----------------
pip install six
pip install pytest
pip install keyring
pip install selenium
pip install requests

python main.py
