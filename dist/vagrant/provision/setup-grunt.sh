#!/bin/bash

target=$1

# --------------------------
# Compiling js if needed
# --------------------------
echo -ne "Compiling js and css..."
pushd ${target}/plugins > /dev/null 2>&1
find . -maxdepth 2 -type f -name Gruntfile.js -execdir bash -c "sudo npm install && grunt &" \;
popd > /dev/null 2>&1
echo -ne "DONE\r"
