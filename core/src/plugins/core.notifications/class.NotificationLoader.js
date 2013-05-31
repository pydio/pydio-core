/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

Class.create("NotificationLoader", {

    ajxpNode : null,
    pFactory : null,
    timer   : 60,
    pe  : null,
    menuItems : null,
    hasAlerts : false,

    initialize: function(){

        if(window.ajxpMinisite) return;

        var rP = new RemoteNodeProvider();
        rP.initProvider({get_action:'get_my_feed', format:'xml', connexion_discrete:true});
        this.ajxpNode = new AjxpNode("/");
        this.ajxpNode._iNodeProvider = rP;
        this.pFactory = new PreviewFactory();
        this.pFactory.sequencialLoading = false;
        this.pFactory.setThumbSize(22);
        this.ajxpNode.observe('loaded', function(){
            this.menuItems = this.childrenToMenuItems();
        }.bind(this));
        this.pe = new PeriodicalExecuter(function(){
            if(window.ajaxplorer.user){
                this.ajxpNode.reload();
            }
        }.bind(this), this.timer);
        ajaxplorer.observe("server_message", function(message){
            if(XPathSelectSingleNode(message, '//reload_user_feed') && ajaxplorer.user){
                this.ajxpNode.reload();
            }
        }.bind(this));
        document.observe("ajaxplorer:repository_list_refreshed", function(){
            window.setTimeout(function(){
                this.ajxpNode.reload();
            }.bind(this), 100);
        }.bind(this));
        this.ajxpNode.reload();
    },

    /*
    childrenToMenu: function(menuContainer){
        this.ajxpNode.getChildren().each(function(el){
           var div = new Element('a');
           var imgSpan = new Element('span', {className:'event_image'});
           var labelSpan = new Element('span', {className:'event_label'});
           var img = this.pFactory.generateBasePreview(el);
           div.IMAGE_ELEMENT = img;
           imgSpan.insert(img);
           labelSpan.insert(el.getMetadata().get("event_description"));
           div.insert(imgSpan); div.insert(labelSpan);
           menuContainer.insert(div);
           this.pFactory.enrichBasePreview(el, div);
        }.bind(this) );

    },
    */

    childrenToMenuItems : function(){
        var menuItems = $A([]);
        var eventIndex = 0;
        var alerts = false;
        var parentAjxpNode = this.ajxpNode;
        var alertsCounts = 0;
        this.hasAlerts = false;

        this.ajxpNode.getChildren().each(function(el){

            // REPLACE REAL PATH NOW
            el._path = el.getMetadata().get("real_path");
            el.getMetadata().set("filename", el._path);
            var isAlert = el.getMetadata().get("event_is_alert") ? true : false;
            if(alerts && !isAlert){
                alerts = false;
                this.hasAlerts = true;
                menuItems.push({separator:true, menuTitle:MessageHash['notification_center.5']});
            }
            if(isAlert){
                if(parseInt(el.getMetadata().get("event_occurence")) > 0){
                    alertsCounts += parseInt(el.getMetadata().get("event_occurence"));
                }else{
                    alertsCounts ++;
                }
                alerts = true;
            }
            var block = '<div class="notif_event_label">'+el.getLabel()+'</div>';
            if(el.getMetadata().get('event_repository_label')){
                block += '<div class="notif_event_repository">'+ el.getMetadata().get('event_repository_label') + '</div>';
            }
            block += '<div class="notif_event_description">'+ el.getMetadata().get('event_description') + '</div>';
            block += '<div class="notif_event_date">'+ el.getMetadata().get('event_date') + '</div>';
            block = '<div class="notif_event_container">'+block+'</div><br style="clear:left;"/>';
            var moreActions = $A([{
                name:MessageHash["notification_center.6"],
                icon_class:"icon-circle-arrow-right",
                callback:function(e){
                    window.ajaxplorer.goTo(el);
                }
            }]);
            if(isAlert){
                moreActions.push({
                    name:MessageHash["notification_center.7"],
                    icon_class:"icon-remove-sign",
                    callback:function(e){
                        Event.stop(e);
                        Effect.Fade(e.target.up('li'));
                        var conn = new Connexion();
                        conn.onComplete = function(){
                            parentAjxpNode.reload();
                        };
                        var params = {
                            get_action:'dismiss_user_alert',
                            alert_id:el.getMetadata().get("alert_id")
                        };
                        if(el.getMetadata().get("event_occurence")){
                            params.occurrences = el.getMetadata().get("event_occurence");
                        }
                        conn.setParameters(params);
                        conn.sendAsync();
                    }
                });
            }
            menuItems.push({
                id: "event_" + eventIndex,
                name:block,
                alt: el.getMetadata().get("event_description_long").stripTags(),
                pFactory : this.pFactory,
                ajxpNode:el,
                callback:function(e){
                    Event.stop(e);
                },
                moreActions: moreActions
            });
            eventIndex ++;
        }.bind(this) );
        var button = $('get_my_feed_button');
        if(button){
            var badge = button.down('.badge');
            if(!badge){
                badge = new Element('span', {className:'badge'});
                button.down('.icon-caret-down,img').insert({before: badge});
            }
            if(alertsCounts){
                badge.update(alertsCounts);
                badge.show();
            }else{
                badge.hide();
            }
        }
        return menuItems;
    },

    dynamicBuilderLoader : function(action, protoMenu){

        action.builderMenuItems = $A([]);
        action.builderMenuItems.push({
            id:'event_loading',
            name:MessageHash[466],
            alt:'',
            className:'loading_input',
            image:resolveImageSource('images/actions/22/hdd_external_unmount.png', '',  22),
            icon_class:'icon-spinner event_loading',
            callback:function(e){ this.apply(); }.bind(action)
        } );
        var loaderFunc = function(){
            try{
                var menuContainer = protoMenu.container;
            }catch(e){}
            if(!menuContainer) {
                return;
            }
            if(!this.ajxpNode.isLoaded()){
                this.ajxpNode.observe("loaded", function(){
                    protoMenu.options.menuItems = this.menuItems;
                    protoMenu.options.menuTitle = this.hasAlerts ? MessageHash['notification_center.3'] : MessageHash['notification_center.5'];
                    protoMenu.refreshList();
                    this.refreshProtoMenuContainerPosition(protoMenu);
                }.bind(this));
                this.ajxpNode.load();
            }else{
                protoMenu.options.menuItems = this.menuItems;
                protoMenu.refreshList();
                this.refreshProtoMenuContainerPosition(protoMenu);
            }
        }.bind(this);
        protoMenu.options = Object.extend(protoMenu.options, {
            position: "bottom middle",
            menuMaxHeight: 350,
            topOffset: 14,
            menuTitle: this.hasAlerts ? MessageHash['notification_center.3'] : MessageHash['notification_center.5'],
            beforeShow: function(){
                protoMenu.container.removeClassName('panelHeaderMenu');
                protoMenu.container.removeClassName('toolbarmenu');
                protoMenu.container.removeClassName('desktop');
                protoMenu.container.addClassName('rootDirChooser');
                protoMenu.container.addClassName('events_menu');
                protoMenu.container.id = "feed_content";
                if(!this.ajxpNode.isLoaded()){
                    protoMenu.options.menuItems = $A([]);
                    protoMenu.options.menuItems.push({
                        id:'event_loading',
                        name:MessageHash[466],
                        alt:'',
                        image:resolveImageSource('images/actions/22/hdd_external_unmount.png', '',  22),
                        icon_class:'icon-spinner event_loading',
                        callback:function(e){ this.apply(); }.bind(action)
                    } );
                    protoMenu.refreshList();
                }
                window.ajxp_feed_timer = window.setTimeout(loaderFunc, 500);
            }.bind(this),
            beforeHide: function(){
                if(window.ajxp_feed_timer) window.clearTimeout(window.ajxp_feed_timer);
            }
        });
        if(protoMenu.container){
            window.setTimeout(function(){
                protoMenu.container.removeClassName('panelHeaderMenu');
                protoMenu.container.removeClassName('toolbarmenu');
                protoMenu.container.removeClassName('desktop');
                protoMenu.container.addClassName('rootDirChooser');
                protoMenu.container.addClassName('events_menu');
                protoMenu.container.id = "feed_content";
                this.refreshProtoMenuContainerPosition(protoMenu);
                loaderFunc();
            }.bind(this), 50);
        }
    },

    refreshProtoMenuContainerPosition: function(protoMenu){
        var dim = protoMenu.container.getDimensions();
        var offset = protoMenu.computeAnchorOffset();
        protoMenu.container.setStyle(offset);
        protoMenu.correctWindowClipping(protoMenu.container, offset, dim);
    }

});

NotificationLoader.getInstance = function(){
    if(!window.ajaxplorer.NotificationLoaderInstance){
        window.ajaxplorer.NotificationLoaderInstance = new NotificationLoader();
    }
    return window.ajaxplorer.NotificationLoaderInstance;
};