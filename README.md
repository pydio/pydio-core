## AjaXplorer Core

[Homepage](http://ajaxplorer.info/) | 
[GitHub-Repository](https://github.com/ajaxplorer/ajaxplorer-core) | 
[Issue-Tracker](https://github.com/ajaxplorer/ajaxplorer-core)

This is the main source code repository of AjaXplorer, containing all the PHP server and HTML5 Web GUI. It was migrated from previous Sourceforge SVN repository, which is hence deprecated. 

* Latest Stable release : 4.2.3
* Latest Dev release : 4.3.3 (RC for AjaXplorer5)
* License: [AGPLv3](https://www.gnu.org/licenses/agpl.html)
* Lead developer  : Charles du Jeu (cdujeu): [Github](https://github.com/cdujeu) | [Twitter](https://twitter.com/AjaXplorer)

### Getting support

Please DO NOT send emails to Charles, but use the forum located on http://forum.ajaxplorer.info instead. Once your problem is qualified, if it's a bug, you will be asked to enter it in the GitHub issue tracker.

### How to contribute / Developer Resources

#### Fixing the Core

If you think you have found a bug and a way to fix it neatly in the code, use a Pull Request to report this change back to us! 

#### Writing new plugins

Please read the Developer doc located at https://ajaxplorer.info/documentation/developer, you will find here a bunch of useful information about the plugins architecture, how to create your own plugin, etc. The action.skeleton plugin is a perfect start for that. 

New in latest build, in the Settings panel, you can find a new "Developer" node where all actions contributed by all plugins are listed. These can be called either by http, or via the CLI API. At the same location, you will find all "hooks" registered and triggered in the server, that are VERY useful when you want your plugin to be really sandboxed.  

#### Signing the CLA

Please <a href="http://www.clahub.com/agreements/ajaxplorer/ajaxplorer-core">sign the Contributor License Agreement</a> before contributing.
