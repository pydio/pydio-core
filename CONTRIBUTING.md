PLEASE READ THE FOLLOWING BEFORE POSTING A BUG. 

*We are very glad to welcome contributors on Pydio Core project. FYI, we use Github only for "qualified bugs" : bugs that are easily reproduced, validated by a Pydio Team member. Our preferred communication channel is our Forum. Please do not ask question in github issues, nor in Twitter or other social feed.*

**INSTALL / UPGRADE ISSUE ?**

> Search the [F.A.Q](https://pydio.com/en/docs/faq)  or [READ THE DOCS](https://pydio.com/en/docs)  

**NO ANSWER YET ?**

> Search the [FORUM](https://pydio.com/forum/)

**STILL STUCK ? ASK THE COMMUNITY**

> Time to [POST IN THE FORUM](https://pydio.com/forum/)

*And only if you're invited to*

**POST A GITHUB ISSUE / SUBMIT PR**
> Make sure to put as many details as possible. If you are referring to a discussion on the Forum, add the link. The more info you give, the more easily we can reproduce the bug, the quicker we can fix it.  
> If you are submitting a Pull Request, please sign the [Contributor License Agreement](https://pydio.com/en/community/contribute/contributor-license-agreement-cla).


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

