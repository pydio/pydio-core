/**
 * @package info.ajaxplorer.plugins
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
 * Description : The "online edition" manager, encapsulate the CodePress highlighter for some extensions.
 */
Class.create("EmlViewer", AbstractEditor, {

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		this.actions.get("downloadFileButton").observe('click', function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=download&file='+this.currentFile);
			return false;
		}.bind(this));
	},
	
	
	open : function($super, userSelection){
		$super(userSelection);
		var fileName = userSelection.getUniqueFileName();
		this.textareaContainer = document.createElement('div');
		this.contentMainContainer = this.textareaContainer;
		this.textareaContainer.setStyle({width:'100%', overflow:'auto'});	
		this.element.appendChild(this.textareaContainer);
		fitHeightToBottom($(this.textareaContainer), $(modal.elementName));
		// LOAD FILE NOW
		this.loadFileContent(fileName);
		if(window.ajxpMobile){
			this.setFullScreen();
			attachMobileScroll(this.textareaContainer, "vertical");
		}		
	},
	
	loadFileContent : function(fileName){
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'eml_get_xml_structure');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){
			this.parseXmlStructure(transp);
			this.updateTitle(getBaseName(fileName));
		}.bind(this);
		connexion2 = new Connexion();
		connexion2.addParameter('get_action', 'eml_get_bodies');
		connexion2.addParameter('file', fileName);	
		connexion2.onComplete = function(transp){
			this.parseBodies(transp);
		}.bind(this);
		this.setModified(false);
		this.setOnLoad(this.textareaContainer);
		connexion.sendAsync();
		connexion2.sendAsync();
	},
		
	
	parseBodies : function (transport){
		var xmlDoc = transport.responseXML;
		var html = XPathSelectSingleNode(xmlDoc, 'email_body/mimepart[@type="html"]');
		if(html){
			var iFrame = new Element('iframe');
			this.textareaContainer.insert(iFrame);
			iFrame.contentDocument.write(html.firstChild.nodeValue);
			iFrame.setStyle({height: '100%;'});
		}else{
			var pre = new Element("pre");
			pre.insert(XPathSelectSingleNode(xmlDoc, 'email_body/mimepart[@type="plain"]').firstChild.nodeValue);
			this.textareaContainer.insert(pre);
			pre.setStyle({display:'block',height: '100%;'});
		}
	},
	
	parseXmlStructure : function(transport){
		var xmlDoc = transport.responseXML;
		var hContainer = this.element.down("#headerContainer");
		// PARSE HEADERS
		var headers = XPathSelectNodes(xmlDoc, "email/header");
		var searchedHeaders = {"From":[], "To":[], "Date":[], "Subject":[]};
		headers.each(function(el){
			var hName = XPathGetSingleNodeText(el, "headername");
			var hValue = XPathGetSingleNodeText(el, "headervalue");
			if(searchedHeaders[hName]){
				searchedHeaders[hName].push(hValue);
			}
		});
		$H(searchedHeaders).each(function(pair){
			if(pair.value.length){
				var value = pair.value.join(", ");
				hContainer.insert('\
					<div>\
						<div class="emlHeaderLabel">'+pair.key+'</div>\
						<div class="emlHeaderContent">'+value+'</div>\
					</div>');
			}
		});
		// PARSE ATTACHEMENTS
		// Go throught headers and find Content-Disposition: attachment ones
		var allHeaders = XPathSelectNodes(xmlDoc, "//header");
		var attachments = {};
		allHeaders.each(function(el){
			var hName = XPathGetSingleNodeText(el, "headername");
			var hValue = XPathGetSingleNodeText(el, "headervalue");
			if(hName != "Content-Disposition" || hValue != "attachment") return;
			var mimepart = el.parentNode;
			var id = 0;
			var filename = "";
			// Find filename
			var params = XPathSelectNodes(el, "parameter");
			params.each(function(c){
				if(XPathGetSingleNodeText(c, "paramname") == "filename"){
					filename = XPathGetSingleNodeText(c, "paramvalue");
				}
			});
			// Find attachment ID - not always
			allHeaders.each(function(h){
				if(h.parentNode != mimepart) return;
				var siblingName = XPathGetSingleNodeText(h, "headername");
				var siblingValue = XPathGetSingleNodeText(h, "headervalue");
				if(siblingName = "X-Attachment-Id"){
					id = XPathGetSingleNodeText(h, "headervalue");
				}
			});			
			attachments[id] = filename;
		});
		if(Object.keys(attachments).length){
			var attachCont = new Element('div', {id:"attachments_container", className:"emlAttachCont"});
			hContainer.insert(attachCont);
			for(var key in attachments){
				var att = new Element("div", {className:"emlAttachment"});
				att.insert(attachments[key]);
				attachCont.insert(att);
			}		
		}
		
		fitHeightToBottom($(this.textareaContainer), $(modal.elementName));
		this.removeOnLoad(this.textareaContainer);
	}	
});