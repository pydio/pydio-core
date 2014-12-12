/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, Pawel Wolniewicz http://innodevel.net/
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
Class.create("ZohoEditor", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject, options)
	{
		this.element =  $(oFormObject);
        this.editorOptions = options;
		this.defaultActions = new Hash();		
		this.createTitleSpans();
        if(this.editorOptions.context.__className == "Modal"){
            modal.setCloseAction(function(){this.close();}.bind(this));
        }
		this.container = $(oFormObject).select('div[id="zohoContainer"]')[0];
		fitHeightToBottom($(this.container), $(this.editorOptions.context.elementName));
		this.contentMainContainer = new Element("iframe", {			
			style:"border:none;width:"+this.container.getWidth()+"px;"
		});						
		this.container.update(this.contentMainContainer);
        this.element.observe("editor:close", function(){
            var conn = new Connexion();
            conn.addParameter("get_action", "retrieve_from_zohoagent");
            conn.onComplete = function(transport){
                if(transport.responseText == "MODIFIED"){
                    ajaxplorer.fireNodeRefresh(this.currentNode);// fireContextRefresh();
                }
            }.bind(this);
            conn.sendAsync();
        }.bind(this));
	},

	
	open : function($super, node)
	{
		$super(node);
		this.setOnLoad(true);
		this.currentNode = node;
		var fName = this.currentNode.getPath();
        this.contentMainContainer.src = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=post_to_zohoserver&file=" + base64_encode(fName) + "&parent_url=" + base64_encode(getUrlFromBase());
		var pe = new PeriodicalExecuter(function(){
			var href;
			try{
				href = this.contentMainContainer.contentDocument.location.href;
			}catch(e){
				if(this.loading){
					this.resize();
					// Force here for WebKit
					this.contentMainContainer.setStyle({height:this.container.getHeight() + 'px'});
					this.removeOnLoad();	
					pe.stop();		
				}
			}
		}.bind(this) , 0.5);
	},

    resize: function($super, s){
        $super(s);
        fitHeightToBottom(this.element);
        fitHeightToBottom(this.container);
        fitHeightToBottom(this.contentMainContainer);
    },

	setOnLoad: function(openMessage){
		if(this.loading) return;
		addLightboxMarkupToElement(this.container);
		var waiter = new Element("div", {align:"center", style:"font-family:Arial, Helvetica, Sans-serif;font-size:25px;color:#AAA;font-weight:bold;"});
		if(openMessage){
			waiter.update('<br><br><br>Please wait while opening Zoho editor...<br>');
		}
		waiter.insert(new Element("img", {src:ajxpResourcesFolder+'/images/loadingImage.gif'}));
		$(this.container).select("#element_overlay")[0].insert(waiter);
		this.loading = true;
	},
	
	removeOnLoad: function(){
		removeLightboxFromElement(this.container);
		this.loading = false;
	}
	
});
