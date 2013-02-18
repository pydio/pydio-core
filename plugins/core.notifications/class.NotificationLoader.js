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

    initialize: function(){
        var rP = new RemoteNodeProvider();
        rP.initProvider({get_action:'get_my_feed', format:'xml', connexion_discrete:true});
        this.ajxpNode = new AjxpNode("/");
        this.ajxpNode._iNodeProvider = rP;
        this.pFactory = new PreviewFactory();
        this.pFactory.sequencialLoading = false;
        this.pFactory.setThumbSize(16);
        var pe = new PeriodicalExecuter(function(){
            this.ajxpNode.reload();
        }.bind(this), 20);
    },

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

    childrenToMenuItems : function(){
        var menuItems = $A([]);
        var eventIndex = 0;
        this.ajxpNode.getChildren().each(function(el){
            menuItems.push({
                id: "event_" + eventIndex,
                name:el.getMetadata().get("event_description"),
                alt:el.getMetadata().get("event_description"),
                pFactory : this.pFactory,
                ajxpNode:el,
                callback:function(e){}
            });
            eventIndex ++;
        }.bind(this) );
        return menuItems;
    },

    dynamicBuilderLoader : function(action, protoMenu){

        action.builderMenuItems = $A([]);
        action.builderMenuItems.push({
            id:'event_loading',
            name:'Loading ...',
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
            this.ajxpNode.observe("loaded", function(){
                protoMenu.options.menuItems = this.childrenToMenuItems();
                protoMenu.refreshList();
                protoMenu.container.setStyle(protoMenu.computeAnchorOffset());
            }.bind(this));
            if(!this.ajxpNode.isLoaded()){
                this.ajxpNode.load();
            }
        }.bind(this);
        protoMenu.options = Object.extend(protoMenu.options, {
            position: "bottom middle",
            menuMaxHeight: 350,
            topOffset: 14,
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
                        name:'Loading ...',
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
                protoMenu.container.setStyle(protoMenu.computeAnchorOffset());
                loaderFunc();
            }, 50);
        }
    }

});

NotificationLoader.getInstance = function(){
    if(!window.ajaxplorer.NotificationLoaderInstance){
        window.ajaxplorer.NotificationLoaderInstance = new NotificationLoader();
    }
    return window.ajaxplorer.NotificationLoaderInstance;
};