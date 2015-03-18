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
Class.create("Breadcrumb", AjxpPane, {
	__implements : ["IAjxpWidget"],
    currentPath : "",
	/**
	 * Constructor
	 * @param oElement HTMLElement
	 * @param options Object
	 */
	initialize : function($super, oElement, options){
        $super(oElement, options);
		this.element = oElement;
		this.element.ajxpPaneObject = this;
        this.options = options || {};
        this.element.update('Files');
        this.observerFunc = function(event){
            if(!this.element) return;
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
                if(parts.size() > 1 && !this.options['always_show_root']){
                    parts.unset(lastChild.getPath());
                }
                var keys = parts.keys().reverse();
                var tmpParts = $H();
                keys.each(function(k){ tmpParts.set(k, parts.get(k)); });
                parts = tmpParts;
            }

            var chevron = "<span class='icon-chevron-right'></span>";
            var clickPath = '';
            if(!this.options['hide_home_icon']){
                clickPath = "<span class='icon-home ajxp-goto' data-goTo='/' title='"+MessageHash[459]+"'></span>";
                if(this.options["use_ul"]){
                    clickPath = "<li>"+clickPath+"</li>";
                }
            }
            var pos = 0;
            var length = parts.keys().length;
            parts.each(function(pair){
                var refresh = '';
                if(this.options["use_ul"]){
                    if(pos == length-1){
                        refresh = '<i class="icon-refresh ajxp-goto-refresh" title="'+MessageHash[149]+'"></i>';
                    }
                    var first = pos == 0 ? ' first-bread':'';
                    clickPath += "<li><span class='ajxp-goto "+first+"' data-goTo='"+pair.key+"'><em>"+pair.value+"</em></span></li>";
                    if(refresh){
                        clickPath += "<li><i class='ajxp-goto' data-goTo='"+pair.key+"'>"+refresh+"</i></li>";
                    }
                }else{
                    if(pos == length-1){
                        refresh = '<span class="icon-refresh ajxp-goto-refresh" title="'+MessageHash[149]+'"></span>';
                    }
                    clickPath += (pair.value != pos == 0 || !this.options['hide_home_icon'] ? chevron : "") + "<span class='ajxp-goto' data-goTo='"+pair.key+"'>"+pair.value+refresh+"</span>";
                }
                pos ++;
            }.bind(this));
            if(this.options['use_ul']){
                this.element.update("<div class='inner_bread'><ul>" + clickPath + "</ul></div>");
                this.resizeUls();
            }else{
                this.element.update("<div class='inner_bread'>" + clickPath + "</div>");
            }

            this.element.select("span.ajxp-goto").invoke("observe", "click", function(event){
                "use strict";
                var target = Event.findElement(event, "span[data-goTo]").getAttribute("data-goTo");// event.target.getAttribute("data-goTo");
                event.target.setAttribute("title", "Go to " + target);
                if(event.target.hasClassName('ajxp-goto-refresh') || event.target.down('span.ajxp-goto-refresh')){
                    window.ajaxplorer.fireContextRefresh();
                }else{
                    window.ajaxplorer.goTo(target);
                }
            });
            this.element.select("i.ajxp-goto").invoke("observe", "click", function(event){
                window.ajaxplorer.fireContextRefresh();
            });

        }.bind(this);
        document.observe("ajaxplorer:context_changed",this.observerFunc);
    },

	/**
	 * Resize widget
	 */
	resize : function($super){
        if(!this.element) return;
        $super();
        if(this.options["use_ul"]){
            this.resizeUls();
        }
        document.fire("ajaxplorer:resize-Breadcrumb-" + this.element.id, this.element.getDimensions());
	},

    resizeUls: function(){
        var available = parseInt(this.element.getWidth());
        var lastOverlaps = function(){
            var last = this.element.down('li:last');
            return (last && last.positionedOffset()['left'] + last.getWidth() ) > available
        }.bind(this);
        var i=0;
        var spans = this.element.select('li > span');
        spans.invoke("removeClassName", "reduced");
        while ( lastOverlaps() && i < spans.length - 2){
            i++;
            spans[i].addClassName("reduced");
        }
        if(lastOverlaps() && spans.length){
            spans[0].addClassName("reduced");
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