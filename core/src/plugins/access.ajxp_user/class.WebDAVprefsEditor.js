Class.create("WebDAVprefsEditor", AjxpPane, {

    initialize: function($super, element, options){
        $super(element, options);

        if(!ajaxplorer.user) return;
        var cont = element.down('#webdav_repo_list');
        cont.update('');
        var activator = element.down("#webdav_activator");

        var even = false;
        var conn = new Connexion();
        conn.setParameters(new Hash({get_action:'webdav_preferences'}));
        conn.onComplete = function(transport){
            ajaxplorer.webdavCurrentPreferences = transport.responseJSON;
            activator.checked = ajaxplorer.webdavCurrentPreferences.webdav_active;
            if(activator.checked && !ajaxplorer.webdavCurrentPreferences.digest_set
                && !ajaxplorer.webdavCurrentPreferences.webdav_force_basic) {
                element.down('#webdav_password_form').show();
            }
            ajaxplorer.user.getRepositoriesList().each(function(pair){
                if(ajaxplorer.webdavCurrentPreferences.webdav_repositories[pair.key]){
                    var div = new Element('div', {className:(even?'even':'')});
                    div.update('<span>'+pair.value.label+'</span><input readonly type="text" value="'+ ajaxplorer.webdavCurrentPreferences.webdav_repositories[pair.key] +'">' );
                    cont.insert(div);
                    even = !even;
                }
            });
            element.down('#webdav_main_access').setValue(ajaxplorer.webdavCurrentPreferences.webdav_base_url);
            element.down('#webdav_main_access').observe("click", function(){
                element.down('#webdav_main_access').select();
            });
            element.down('#perworkspace-urls-toggle').observe("click", function(event){
                element.down('#webdav_repo_list').toggle();
                var span = element.down('#perworkspace-urls-toggle').down("span");
                var open = span.hasClassName("icon-caret-right");
                span.removeClassName(open ? "icon-caret-right" : "icon-caret-down");
                span.addClassName(!open ? "icon-caret-right" : "icon-caret-down");
            });

            //element.down('input[name="ok"]').observe("click", hideLightBox);
            if(!activator.hasObserver){
                activator.observe("change", function(e){
                    var checked = activator.checked;
                    var conn = new Connexion();
                    conn.setParameters(new Hash({
                        get_action:'webdav_preferences',
                        activate:(checked?'true':'false')
                    }));
                    conn.onComplete = function(transport){
                        ajaxplorer.webdavCurrentPreferences = transport.responseJSON;
                        if(ajaxplorer.webdavCurrentPreferences.webdav_active){
                            if(!ajaxplorer.webdavCurrentPreferences.digest_set
                                || ajaxplorer.webdavCurrentPreferences.webdav_force_basic) {
                                element.down('#webdav_password_form').show();
                            }
                            ajaxplorer.displayMessage("SUCCESS", MessageHash[408]);
                        }else {
                            element.down('#webdav_password_form').hide();
                            ajaxplorer.displayMessage("SUCCESS", MessageHash[409]);
                        }
                    };
                    conn.sendAsync();
                });
                if(!ajaxplorer.webdavCurrentPreferences.digest_set){
                    element.down('#webdav_pass_saver').observe("click", function(){
                        var conn = new Connexion();
                        conn.setMethod('POST');
                        conn.setParameters(new Hash({
                            get_action:'webdav_preferences',
                            webdav_pass: element.down('#webdav_password').value
                        }));
                        conn.onComplete = function(transport){
                            ajaxplorer.displayMessage("SUCCESS", MessageHash[410]);
                        };
                        conn.sendAsync();
                    });
                }
                activator.hasObserver = true;
            }
        };
        conn.sendAsync();


    }


});