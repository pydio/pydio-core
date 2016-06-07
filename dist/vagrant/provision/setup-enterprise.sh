#!/usr/bin/env bash

version=$1

echo "Installing PYDIO Enterprise version : $version"

if [ -z "$version" ]; then
    echo "Enterprise Version not defined"
    exit 0
fi

if [ ! -d "/var/www/html/plugins" ]; then
    echo "Core version not installed"
    exit 2
fi

pushd /tmp/pydio

# ----------------
# Downloading Archive
# ----------------
if [[ ${version} =~ 6.* ]]; then
    ##########################
    # TODO - replace keys
    ##########################
    echo -ne "Downloading PYDIO ENTERPRISE version ${version}..."
    curl -s -u USER:PASSWORD -O https://download.pydio.com/auth/enterprise/archives/pydio-enterprise-${version}.zip
    sudo unzip -q pydio-enterprise*
    echo -ne "DONE\r"
else
    # ----------
    # Cloning
    # ----------
    echo -ne "Cloning from git..."
    git clone git@github.com:pydio/pydio-enterprise.git --branch ${version} pydio-enterprise-git
    echo -ne "DONE\r"

    # --------------------------
    # Fetching dir in hierarchy
    # --------------------------
    echo -ne "Translating result..."
    pushd pydio-enterprise* > /dev/null 2>&1
    mv -f src TOREMOVE_src
    mv -f TOREMOVE_src/* .
    rm -rf TOREMOVE_src
    popd > /dev/null 2>&1
    echo -ne "DONE\r"
fi

popd

# --------------------------
# Moving all to resting dir
# --------------------------
echo -ne "Transferring files to DocumentRoot..."
shopt -s dotglob > /dev/null 2>&1
sudo find /tmp/pydio/pydio-enterprise*/plugins -mindepth 1 -maxdepth 1 -type d -exec bash -c 'rm -rf /var/www/html/plugins/$(basename {}) && mv {} ${target}/plugins/' \; > /dev/null 2>&1
echo -ne "DONE\r"
