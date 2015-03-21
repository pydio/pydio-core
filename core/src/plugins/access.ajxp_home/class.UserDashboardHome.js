/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 *
 */
Class.create("UserDashboardHome", AjxpPane, {

    _formManager: null,
    _repoInfos: null,
    _repoInfosLoading:null,

    initialize: function($super, oFormObject, editorOptions){

        $super(oFormObject, editorOptions);
        this._repoInfos = $H();
        this._repoInfosLoading = $H();
        var dashLogo = ajaxplorer.getPluginConfigs("gui.ajax").get("CUSTOM_DASH_LOGO");
        if(!dashLogo)
            dashLogo = ajaxplorer.getDefaultImageFromParameters("gui.ajax", "CUSTOM_DASH_LOGO");
        if(dashLogo){
            var url;
            if(dashLogo.indexOf('plugins/') === 0){
                url = dashLogo;
            }else{
                url = window.ajxpServerAccessPath + "&get_action=get_global_binary_param&binary_id=" + dashLogo;
            }
            oFormObject.down("#logo_div").down("img").src = url;
        }
        oFormObject.down("#welcome").update( MessageHash['user_home.40'].replace('%s', ajaxplorer.user.getPreference("USER_DISPLAY_NAME") || ajaxplorer.user.id));

        var wsElement = oFormObject.down('#workspaces_list');
        attachMobileScroll(oFormObject.down('#list_cont'), 'vertical');

        var simpleClickOpen = ajaxplorer.getPluginConfigs("access.ajxp_home").get("SIMPLE_CLICK_WS_OPEN");

        var switchToRepo = function(repoId){
            if(!repoId) return;
            if(oFormObject.down('#save_ws_choice').checked){
                // Save this preference now!
                var params = $H({
                    'PREFERENCES_DEFAULT_START_REPOSITORY':repoId,
                    'get_action':'custom_data_edit'
                });
                var conn = new Connexion();
                conn.setParameters(params);
                conn.setMethod("POST");
                conn.onComplete = function(transport){
                    ajaxplorer.user.setPreference('DEFAULT_START_REPOSITORY', repoId, false);
                };
                conn.sendAsync();
            }
            ajaxplorer.triggerRepositoryChange(repoId);
        };

        var updateRepoInfo = function(block, repoId){
            var data = this._repoInfos.get(repoId);
            var blocks = 0;
            if(data['core.users'] && data['core.users']['internal'] != undefined && data['core.users']['external'] != undefined){
                blocks++;
                block.insert('<div class="repoInfoBadge"><div class="repoInfoTitle">'+MessageHash[527]+'</div><span class="icon-group"></span>'+MessageHash[531]+' ' + data['core.users']['internal'] + ' <br>'+MessageHash[532]+' ' + data['core.users']['external']+"</div>");
            }
            if(data['meta.quota']){
                blocks++;
                block.insert('<div class="repoInfoBadge"><div class="repoInfoTitle">'+MessageHash['meta.quota.4']+'</div><span class="icon-dashboard"></span>' + parseInt(100*data['meta.quota']['usage']/data['meta.quota']['total']) + '% <br><small>' + roundSize(data['meta.quota']['total'], MessageHash["byte_unit_symbol"]) +"</small></div>");
            }
            if(data['core.notifications'] && data['core.notifications'][0]){
                var date = data['core.notifications'][0]['short_date'];
                blocks++;
                block.insert('<div class="repoInfoBadge"><div class="repoInfoTitle">'+MessageHash[4]+'</div><span class="icon-calendar"></span>' + date + "</div>");
            }
            if(!blocks){
                block.previous('small').addClassName('show_description');
            }
        }.bind(this);

        var updateWsLegend = function(repoObject){
            var legendBlock = this.htmlElement.down('#ws_legend');
            if(!repoObject && this.htmlElement.down('#go_to_ws').CURRENT_REPO_OBJECT){
                repoObject = this.htmlElement.down('#go_to_ws').CURRENT_REPO_OBJECT;
            }
            if(this.timer){
                window.clearTimeout(this.timer);
            }
            if(!repoObject) {
                //return;
                this.timer = window.setTimeout(function(){
                    if(! legendBlock.up('#home_center_panel') ) return;
                    legendBlock.update('');
                    legendBlock.writeAttribute("data-repoId", "");
                    legendBlock.up('#home_center_panel').removeClassName('legend_visible');
                }, 7000);
                return;
            }
            var repoId = repoObject.getId();
            legendBlock.writeAttribute("data-repoId", repoId);
            legendBlock.update(repoObject.getLabel() + '<small>' + repoObject.getDescription() + '</small><div class="repoInfo"></div>');
            legendBlock.insert('<div style="line-height: 0.5em;"><input type="checkbox" name="save_ws_choice" id="save_ws_choice"><label for="save_ws_choice">'+MessageHash['user_home.41']+'</label></div>');
            legendBlock.insert('<a>'+MessageHash['user_home.42']+'</a>');
            legendBlock.down('a').observe('click', function(){
                switchToRepo(repoId);
            });
            legendBlock.up('#home_center_panel').addClassName('legend_visible');
            if(!this._repoInfosLoading.get(repoId) && !this._repoInfos.get(repoId)){
                var conn = new Connexion();
                this._repoInfosLoading.set(repoId, 'loading');
                conn.setParameters({
                    get_action:'load_repository_info',
                    tmp_repository_id:repoObject.getId(),
                    collect:'true'
                });
                conn.onComplete = function(transport){
                    this._repoInfosLoading.unset(repoId);
                    if(transport.responseJSON){
                        var data = transport.responseJSON;
                        this._repoInfos.set(repoId, data);
                        if(legendBlock.readAttribute("data-repoId") == repoId){
                            updateRepoInfo(legendBlock.down(".repoInfo"), repoId);
                        }else{

                        }
                    }
                }.bind(this);
                conn.sendAsync();
            }else if(this._repoInfos.get(repoId)){
                updateRepoInfo(legendBlock.down(".repoInfo"), repoId);
            }
        }.bind(this);

        var renderElement = function(repoObject){

            var repoId = repoObject.getId();
            var repoEl = new Element('li').update(repoObject.getHtmlBadge() + "<h3>"+repoObject.getLabel() + "</h3><h4>" + repoObject.getDescription()+"</h4>");
            wsElement.insert(repoEl);
            var select = function(e){
                var target = Event.findElement(e, "li");
                target.nextSiblings().invoke('removeClassName', 'selected');
                target.previousSiblings().invoke('removeClassName', 'selected');
                target.addClassName('selected');
                oFormObject.down('#go_to_ws').removeClassName("disabled");
                oFormObject.down('#go_to_ws').CURRENT_REPO_ID = repoId;
                oFormObject.down('#go_to_ws').CURRENT_REPO_OBJECT = repoObject;
                if(window.ajxpMobile){
                    switchToRepo(repoId);
                }
            };
            attachMobilTouchForClick(repoEl, select);
            disableTextSelection(repoEl);
            if(simpleClickOpen){
                repoEl.observe("click", function(e){
                    repoEl.stopObserving("click");
                    select(e);
                    Event.findElement(e, "li").setOpacity(0.7);
                    switchToRepo(repoId);
                });
            }else{
                repoEl.observe("click", select);
                repoEl.observe("dblclick", function(e){
                    repoEl.stopObserving("dblclick");
                    select(e);
                    Event.findElement(e, "li").setOpacity(0.7);
                    switchToRepo(repoId);
                });
            }
            repoEl.observe("mouseover", function(){
                updateWsLegend(repoObject);
            });
            repoEl.observe("mouseout", function(){
                updateWsLegend(null);
            });

        };

        var myWS = ajaxplorer.user.repositories.filter(function(pair){
            return (pair.value.owner === '' && !pair.value.getAccessType().startsWith('ajxp_'));
        }).sortBy(function(pair){
            return (pair.value.getLabel());
        });
        var sharedWS = ajaxplorer.user.repositories.filter(function(pair){
            return (pair.value.owner !== '' && !pair.value.getAccessType().startsWith('ajxp_'));
        }).sortBy(function(pair){
            return (pair.value.getLabel());
        });

        if(myWS.size()){
            wsElement.insert(new Element('li', {className:'ws_selector_title'}).update("<h3>"+MessageHash[468]+"</h3>"));
            myWS.each(function(pair){renderElement(pair.value);});
        }

        if(sharedWS.size()){
            wsElement.insert(new Element('li', {className:'ws_selector_title'}).update("<h3>"+MessageHash[469]+"</h3>"));
            sharedWS.each(function(pair){renderElement(pair.value);});
        }

        if($('videos_pane')){
            $('videos_pane').select('div.tutorial_load_button').invoke("observe", "click", function(e){
                var t = Event.findElement(e, 'div.tutorial_load_button');
                try{
                    var main = t.up('div.tutorial_legend');
                    main.next('iframe').src = main.readAttribute('data-videoSrc');
                }catch(e){}
            });
        }

        oFormObject.down('#go_to_ws').observe("click", function(e){
            var target = e.target;
            switchToRepo(target.CURRENT_REPO_ID);
        });

        if(ajaxplorer.actionBar.getActionByName("logout") && ajaxplorer.user.id != "guest"){
            oFormObject.down("#welcome").insert('<small>'+MessageHash["user_home.67"].replace("%logout", "<span id='disconnect_link'></span>").replace('%s', ajaxplorer.user.getPreference("USER_DISPLAY_NAME") || ajaxplorer.user.id)+'</small>');
            oFormObject.down('#disconnect_link').update("<a>"+ajaxplorer.actionBar.getActionByName("logout").options.text.toLowerCase()+"</a>");
            oFormObject.down('#disconnect_link').observe("click", function(e){
                ajaxplorer.actionBar.fireAction("logout");
            });
        }else if(ajaxplorer.user.id == "guest" && ajaxplorer.actionBar.getActionByName("login")){
            oFormObject.down("#welcome").insert("<small>You can <a id='disconnect_link'>login</a> if you are not guest.</small>");
            oFormObject.down('#disconnect_link').observe("click", function(e){
                ajaxplorer.actionBar.fireAction("login");
            });
        }

        if(ajaxplorer.getPluginConfigs('access.ajxp_home').get("ENABLE_GETTING_STARTED")){
            var obj = oFormObject.down("#welcome");
            if(oFormObject.down("#welcome > small")) obj = oFormObject.down("#welcome > small");
            var span = new Element('span').update('<br>' + MessageHash["user_home.55"]);
            span.down('a').observe('click', function(){ ajaxplorer.getActionBar().fireAction("open_tutorial_pane"); });
            obj.insert(span);
        }


        try{
            window.setTimeout(function(){
                if($("orbit_content")) $("orbit_content").ajxpPaneObject.resize();
                else if($("browser")) $("browser").ajxpPaneObject.resize();
            }, 50);
        }catch(e){}

    },

    resize: function($super, size){

        $super(size);

        //fitHeightToBottom(this.htmlElement.down('#workspaces_center'), this.htmlElement, 0);
    }


});