#!/usr/bin/env bash

version=$1
target=$2

echo "Installing PYDIO Core version : $version"

if [ -z "$version" ]; then
    echo "Enterprise Version not defined"
    exit 0
fi

pushd /tmp/pydio

# ----------------
# Downloading Archive
# ----------------
if [[ ${version} =~ 6.* ]]; then
echo -ne "Downloading PYDIO CORE version ${version}..."
    curl -s -O https://download.pydio.com/pub/core/archives/pydio-core-${version}.zip
    sudo unzip -q pydio-core-*
    echo -ne "DONE\r"
else

    # ----------
    # Cloning
    # ----------
    echo -ne "Cloning from git..."
    git clone git@github.com:pydio/pydio-core.git --branch ${version} pydio-core-git
    echo -ne "DONE\r"

    # --------------------------
    # Fetching dir in hierarchy
    # --------------------------
    echo -ne "Translating result..."
    pushd pydio-core* > /dev/null 2>&1
    mv -f core TOREMOVE_core
    mv -f TOREMOVE_core/src/* .
    rm -rf TOREMOVE_core
    popd > /dev/null 2>&1
    echo -ne "DONE\r"

fi

popd

sudo mv /tmp/pydio/pydio-core*/* $target
