/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
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

/**
 * Manage background tasks and display their state.
 */
Class.create("BackgroundManager", {
	/**
	 * @var $A() The queue of tasks to process
	 */
	queue: $A([]),
	/**
	 * Constructor
	 * @param actionManager ActionManager
	 */
	initialize:function(actionManager){
		this.actionManager = actionManager;
		this.panel = new Element('div').addClassName('backgroundPanel');
		if(Prototype.Browser.IE){
			this.panel.setStyle({width:'35%'});
		}
		this.panel.hide();
		this.working = false;
		document.body.insert(this.panel);
	},
	/**
	 * Add an action to the queue
	 * @param actionName String Name of the action
	 * @param parameters Object Parameters of the action
	 * @param messageId String An i18n id of the message to be displayed during the action
	 */
	queueAction:function(actionName, parameters,messageId){
		var actionDef = new Hash();
		actionDef.set('name', actionName);
		actionDef.set('messageId', messageId);
		actionDef.set('parameters', parameters);
		this.queue.push(actionDef);
	},
	
	/**
	 * Processes the next action in the queue
	 */
	next:function(){
		if(!this.queue.size()){
			 this.finished();
			 return;
		}
		if(this.working) return;
		var actionDef = this.queue[0];
        if(actionDef.get('name') == "javascript_instruction" && actionDef.get('parameters').get('callback')){
            var cb = actionDef.get('parameters').get('callback');
            this.updatePanelMessage(actionDef.get('messageId'));
    		this.queue.shift();
            cb();
            this.working = false;
            this.next();
        }else{
            var connexion = new Connexion();
            connexion.setParameters(actionDef.get('parameters'));
            connexion.addParameter('get_action', actionDef.get('name'));
            connexion.onComplete = function(transport){
                var xmlResponse = transport.responseXML;
                if(xmlResponse == null || xmlResponse.documentElement == null) {
                    //alert(transport.responseText);
                    this.working = false;
                    this.next();
                    return;
                }
                this.parseAnswer(transport.responseXML);
                this.working = false;
            }.bind(this);
            connexion.sendAsync();
            this.updatePanelMessage(actionDef.get('messageId'));
    		this.queue.shift();
    		this.working = true;
        }
	},

    updatePanelMessage : function(message){
        var imgString = '<img src="'+ajxpResourcesFolder+'/images/loadingImage.gif" width="16" align="absmiddle">';
        this.panel.update(imgString+' '+message);
        this.panel.show();
    },

	/**
	 * Parses the response. Should probably use the actionBar parser instead.
	 * @param xmlResponse XMLDocument
	 */
	parseAnswer:function(xmlResponse){
		var childs = xmlResponse.documentElement.childNodes;	
		var delay = 0;
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].tagName == "message")
			{
				var type = childs[i].getAttribute('type');
				if(type != 'SUCCESS'){
					return this.interruptOnError(childs[i].firstChild.nodeValue);
				}
			}
			else if(childs[i].nodeName == "trigger_bg_action"){				
				var name = childs[i].getAttribute("name");
				var messageId = childs[i].getAttribute("messageId");
                delay = parseInt(childs[i].getAttribute("delay"));
				var parameters = new Hash();
				for(var j=0;j<childs[i].childNodes.length;j++){
					var paramChild = childs[i].childNodes[j];
					if(paramChild.tagName == 'param'){
						parameters.set(paramChild.getAttribute("name"), paramChild.getAttribute("value"));
					}else if(paramChild.tagName == 'clientCallback'){
                        var callbackCode = paramChild.firstChild.nodeValue;
                        var callback = new Function(callbackCode);
                    }
				}
				if(name == "reload_node"){
                    if(delay){
                        window.setTimeout(function(){
                            ajaxplorer.fireContextRefresh();
                            this.next();
                        }.bind(this), delay*1000);
                        return;
                    }
					 var dm = ajaxplorer.fireContextRefresh();
                }else if(name == "info_message"){
                    this.updatePanelMessage(messageId);
                }else if(name == "javascript_instruction" && callback){
                    parameters.set("callback", callback);
                    this.queueAction('javascript_instruction', parameters, messageId);
				}else{
					this.queueAction(name, parameters, messageId);
				}
			}
		}
		this.working = false;
        if(delay){
            window.setTimeout(this.next.bind(this), delay*1000);
        }else{
            this.next();
        }
	},
	/**
	 * Interrupt the task on error
	 * @param errorMessage String
	 */
	interruptOnError:function(errorMessage){
		if(this.queue.size()) this.queue = $A([]);
		
		this.panel.update(errorMessage);
		this.panel.insert(this.makeCloseLink());
		this.working = false;
	},
	/**
	 * All tasks are processed
	 */
	finished:function(){		
		this.working = false;
		this.panel.hide();
	},
	
	/**
	 * Create a "Close" link
	 */
	makeCloseLink:function(){
		var link = new Element('a', {href:'#'}).update('Close').observe('click', function(e){
			Event.stop(e);
			this.panel.hide();
		}.bind(this));
		return link;
	},
	/**
	 * Create a stub action with not parameter.
	 */
	addStub: function(){		
		this.queueAction('local_to_remote', new Hash(), 'Stubing a 10s bg action');
	}
});