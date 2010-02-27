/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : base for nodes provider
 */
Class.create("RemoteNodeProvider", {
	__implements : "IAjxpNodeProvider",
	initialize : function(){
		
	},
	initProvider : function(properties){
		
	},
	loadNode : function(nodePath, nodeCallback, childCallback, options){
		var conn = new Connexion();
		conn.addParameter("get_action", "ls");
		conn.addParameter("dir", nodePath);
		if(options){
			$H(options).each(function(pair){
				conn.addParameter(pair.key, pair.value);
			});
		}
		conn.onComplete = function (transport){
			try{				
				this.parseNodes(transport, nodeCallback, childCallback);
			}catch(e){
				if(ajaxplorer) ajaxplorer.displayMessage('Loading error :'+e.message);
				else alert('Loading error :'+ e.message);				
			}finally{
				document.fire("ajaxplorer:context_loaded");
			}
		}.bind(this);	
		document.fire("ajaxplorer:context_loading");
		conn.sendAsync();
	},
	parseNodes : function(transport, nodeCallback, childCallback){
		if(!transport.responseXML || !transport.responseXML.documentElement) return;
		var rootNode = transport.responseXML.documentElement;
		var children = rootNode.childNodes;
		var contextNode = this.parseAjxpNode(rootNode);
		for (var i = 0; i < children.length; i++) 
		{
			if (children[i].nodeName == "tree") 
			{
				var child = this.parseAjxpNode(children[i]);
				contextNode.addChild(child);
				if(childCallback){
					childCallback(child);
				}
			}
		}			
		if(nodeCallback){
			nodeCallback(contextNode);
		}
	},
	parseAjxpNode : function(xmlNode){
		var node = new AjxpNode(
			xmlNode.getAttribute('filename'), 
			(xmlNode.getAttribute('is_file') == "1" || xmlNode.getAttribute('is_file') == "true"), 
			xmlNode.getAttribute('text'),
			xmlNode.getAttribute('icon'));
		var reserved = ['filename', 'is_file', 'text', 'icon'];
		var metadata = new Hash();
		for(var i=0;i<xmlNode.attributes.length;i++)
		{
			metadata.set(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
			if(Prototype.Browser.IE && xmlNode.attributes[i].nodeName == "ID"){
				metadata.set("ajxp_sql_"+xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
			}
		}
		// BACKWARD COMPATIBILIY
		metadata.set("XML_NODE", xmlNode);
		node.setMetadata(metadata);
		return node;
	}
});