## Pydio Core

[Homepage](https://pydio.com/) |
[GitHub-Repository](https://github.com/pydio/pydio-core) |
[Issue-Tracker](https://github.com/pydio/pydio-core/issues) |
 [![Codacy Badge](https://api.codacy.com/project/badge/3b5cafea44e949e789d1928687e04032)](https://www.codacy.com/app/charles_3085/pydio-core) |
 [![Build Status](https://travis-ci.org/pydio/pydio-core.svg)](https://travis-ci.org/pydio/pydio-core)

This is the main source code repository of Pydio (formerly AjaXplorer), containing all the PHP server and HTML5 Web GUI.

* Latest Stable release : 6.2.0
* Latest Dev release : 5.3.4 (was RC4 for Pydio 6.0.0)
* License: [AGPLv3](https://www.gnu.org/licenses/agpl.html)
* Lead developer  : Charles du Jeu (cdujeu): [Github](https://github.com/cdujeu) | [Twitter](https://twitter.com/Pydio)


### Getting support

Please DO NOT send emails to Charles, but use the forum located on https://pydio.com/forum/ instead. Once your problem is qualified, if it's a bug, you will be asked to enter it in the GitHub issue tracker.

### How to contribute / Developer Resources

#### Coding guidelines

To enforce some coding standards, please run scripts in
```
dist/scripts/tests/
```

#### Fixing the Core

If you think you have found a bug and a way to fix it neatly in the code, use a Pull Request to report this change back to us! 

#### Writing new plugins

Please read the Developer doc located at https://pyd.io/documentation/developer, you will find here a bunch of useful information about the plugins architecture, how to create your own plugin, etc. The action.skeleton plugin is a perfect start for that. 

New in latest build, in the Settings panel, you can find a new "Developer" node where all actions contributed by all plugins are listed. These can be called either by http, or via the CLI API. At the same location, you will find all "hooks" registered and triggered in the server, that are VERY useful when you want your plugin to be really sandboxed.  

#### Signing the CLA

Please <a href="https://pydio.com/en/community/contribute/contributor-license-agreement-cla">sign the Contributor License Agreement</a> before contributing.
