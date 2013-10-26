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
 */

/**
 * Container for location components, go to parent, refresh.
 */
Class.create("Breadcrumb", {
	__implements : ["IAjxpWidget"],
    currentPath : "",
	/**
	 * Constructor
	 * @param oElement HTMLElement
	 * @param options Object
	 */
	initialize : function(oElement, options){
		this.element = oElement;
		this.element.ajxpPaneObject = this;
        this.options = options || {};
        this.element.update('Files');
        this.observerFunc = function(event){
            var newNode = event.memo;
            var parts = $H();
            if(Object.isString(newNode)){
                newNode = new AjxpNode(newNode);
                var newPath = newNode.getPath();

                var crtPath = "";
                $A(newPath.split("/")).each(function(element){
                    if(!element) return;
                    crtPath += "/" + element;
                    parts.set(crtPath, element);
                });
                if(getBaseName(newPath) != newNode.getLabel()){
                    parts.set(newPath, newNode.getLabel());
                }
            }else{
                var parent = newNode.getParent();
                parts.set(newNode.getPath(), newNode.getLabel());
                while(parent != null){
                    var lastChild = parent;
                    parts.set(parent.getPath(), parent.getLabel());
                    parent = parent.getParent();
                }
                if(parts.size() > 1){
                    parts.unset(lastChild.getPath());
                }
                var keys = parts.keys().reverse();
                var tmpParts = $H();
                keys.each(function(k){ tmpParts.set(k, parts.get(k)); });
                parts = tmpParts;
            }

            var clickPath = "<span class='icon-home ajxp-goto' data-goTo='/' title='"+MessageHash[459]+"'></span>";
            var lastValue = parts.values().last();
            parts.each(function(pair){
                var refresh = '';
                if(pair.value == lastValue){
                    refresh = '<span class="icon-refresh ajxp-goto-refresh" title="'+MessageHash[149]+'"></span>';
                }
                clickPath += "<span class='icon-chevron-right'></span>" + "<span class='ajxp-goto' data-goTo='"+pair.key+"'>"+pair.value+refresh+"</span>";
            });
            this.element.update("<div class='inner_bread'>" + clickPath + "</div>");

            this.element.select("span.ajxp-goto").invoke("observe", "click", function(event){
                "use strict";
                var target = event.target.getAttribute("data-goTo");
                event.target.setAttribute("title", "Go to " + target);
                if(event.target.down('span.ajxp-goto-refresh')){
                    window.ajaxplorer.fireContextRefresh();
                }else{
                    window.ajaxplorer.goTo(target);
                }
            });

        }.bind(this);
        document.observe("ajaxplorer:context_changed",this.observerFunc);
    },

	/**
	 * Resize widget
	 */
	resize : function(){
        if(!this.element) return;
		if(this.options.flexTo){
			var parentWidth = $(this.options.flexTo).getWidth();
			var siblingWidth = 0;
			this.element.siblings().each(function(s){
				if(s.ajxpPaneObject && s.ajxpPaneObject.getActualWidth){
					siblingWidth+=s.ajxpPaneObject.getActualWidth();
				}else{
					siblingWidth+=s.getWidth();
				}
			});
            var buttonsWidth = 0;
            this.element.select("div.inlineBarButton,div.inlineBarButtonLeft,div.inlineBarButtonRight").each(function(el){
                buttonsWidth += el.getWidth();
            });
			var newWidth = (parentWidth-siblingWidth-30);
			if(newWidth < 5){
				this.element.hide();
			}else{
				this.element.show();
				this.element.setStyle({width:newWidth + 'px'});
			}
		}
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	getDomNode : function(){
		return this.element;
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	destroy : function(){
        document.stopObserving("ajaxplorer:context_changed",this.observerFunc);
		this.element = null;
	},

	/**
	 * Do nothing
	 * @param show Boolean
	 */
	showElement : function(show){

    }
});