## Pydio Core

[Homepage](https://pydio.com/) |
[GitHub-Repository](https://github.com/pydio/pydio-core) |
[Issue-Tracker](https://github.com/pydio/pydio-core/issues) 
| ![Latest Stable](https://img.shields.io/badge/stable-7.0.4-brightgreen.svg) 
| ![License Badge](https://img.shields.io/badge/License-AGPL%203%2B-blue.svg)
| [![Codacy Badge](https://api.codacy.com/project/badge/3b5cafea44e949e789d1928687e04032)](https://www.codacy.com/app/charles_3085/pydio-core) 
|  [![Build Status](https://travis-ci.org/pydio/pydio-core.svg)](https://travis-ci.org/pydio/pydio-core) 

This is the main source code repository of Pydio (formerly AjaXplorer), containing all the PHP server and HTML5 Web GUI.

* Latest Stable release : 7.0.4
* Latest Dev release : 6.5.5 (Final Release Candidate for Pydio 7)
* License: [AGPLv3](https://www.gnu.org/licenses/agpl.html)
* Lead developer  : Charles du Jeu (cdujeu): [Github](https://github.com/cdujeu) | [Twitter](https://twitter.com/Pydio)


### Getting support

Please DO NOT send emails to Charles, but use the forum located on https://pydio.com/forum/ instead. Once your problem is qualified, if it's a bug, you will be asked to enter it in the GitHub issue tracker.

### How to contribute / Developer Resources

#### Setting up your dev environment

Pydio 7 requires **PHP5.5.9** and upper. 

The web root of the application is located in ***core/src/***. Create a virtual host to point to this folder, set up your webserver to use index.php as default page. This is generally done by default. 

Pydio uses Composer and NPM to manage dependencies respectively in PHP and JS. It uses Grunt to build javascript sources. In order to start Pydio locally after a fresh `git clone`, you will first have to run these tools in both the core and in many plugins. 

 - First install Composer (see https://getcomposer.org) and NPM (https://docs.npmjs.com/getting-started/installing-node)
 - Install Grunt globally by running `npm install -g grunt-cli``
 - Inside the core folder (under webroot, i.e. core/src/core/ from root of git repository), run `composer install`
 - For each plugin that contains a composer.json file, run `composer install` as well.
 - For each plugin tat contains a package.json file, run
   - `npm install`
   - `grunt`

On a unix-based machine, this can be achieved by the following command (from the webroot directory):  
```
find . -maxdepth 5 -name Gruntfile.js -execdir bash -c "npm install && grunt" \;  
find . -maxdepth 5 -name composer.json -execdir composer install \;
```

You should be good to go. When modifying JS files that require transpilation, there is generally a `grunt watch` task available to automatically run grunt on each file change.


#### Coding guidelines

To enforce some coding standards, please run scripts in
```
dist/scripts/tests/
```

#### Fixing the Core

If you think you have found a bug and a way to fix it neatly in the code, use a Pull Request to report this change back to us! 

#### Writing new plugins

Please read the Developer doc located at https://pydio.com/en/docs/developer-guide-v7, you will find here a bunch of useful information about the plugins architecture, how to create your own plugin, etc. The action.skeleton plugin is a perfect start for that. 

New in latest build, in the Settings panel, you can find a new "Developer" node where all actions contributed by all plugins are listed. These can be called either by http, or via the CLI API. At the same location, you will find all "hooks" registered and triggered in the server, that are VERY useful when you want your plugin to be really sandboxed.  

#### Signing the CLA

Please [sign the Contributor License Agreement](https://pydio.com/en/community/contribute/contributor-license-agreement-cla) before contributing.
