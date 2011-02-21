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
		// Move hidden download form in body, if not already there
		var original = $("emlDownloadAttachmentForm");
		if($("emlDownloadForm")){
			original.remove();
		}else{
			$$("body")[0].insert(original);
			original.id="emlDownloadForm";
			original.setAttribute("id", "emlDownloadForm");
			$("emlDownloadForm").insert(new Element("input", {"type":"hidden", "name":"get_action", "value":"eml_dl_attachment"}));
			$("emlDownloadForm").insert(new Element("input", {"type":"hidden", "name":"file", "value":""}));
			$("emlDownloadForm").insert(new Element("input", {"type":"hidden", "name":"secure_token", "value":Connexion.SECURE_TOKEN}));
			$("emlDownloadForm").insert(new Element("input", {"type":"hidden", "name":"attachment_id", "value":""}));
		}
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
		
	
	dlAttachment : function(event){
		//console.log(event.target.__ATTACHMENT_ID);
		var form = $("emlDownloadForm");
		form.elements["secure_token"].value = Connexion.SECURE_TOKEN;
		form.elements["file"].value = this.currentFile; 
		form.elements["attachment_id"].value = event.target.up("div").__ATTACHMENT_ID;
		form.submit();
	},
	
	cpAttachment : function(event){
		var container = this.element.down('#treeSelectorCpContainer');
		this.treeSelector = new TreeSelector(container);
		this.treeSelector.load();
		var currentAtt = event.target.up("div");
		var attachmentId = currentAtt.__ATTACHMENT_ID;
		if(this.fullScreenMode){
			container.setStyle({right: 4, top: ($('emlHeaderContainer').getHeight() + 128) + "px"});	
		}else{
			container.setStyle({right: 11, top: ($('emlHeaderContainer').getHeight() + 75) + "px"});
		}
		
		
		var hideSelector = function(){
			this.treeSelector.unload();
			currentAtt.removeClassName("active");
			currentAtt.select("a.emlAttachmentAction").each(Element.hide);
			container.hide();			
		}.bind(this);
		
		container.down("#eml_cp_ok").observeOnce("click", function(e){
			Event.stop(e);
			var selectedNode = this.treeSelector.getSelectedNode();
			var connexion = new Connexion();
			connexion.setParameters({
				file: this.currentFile,
				get_action:'eml_cp_attachment',
				attachment_id:attachmentId,
				destination:selectedNode
			});
			connexion.onComplete = function(transport){
				ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
			};
			connexion.sendAsync();
			hideSelector();
		}.bind(this));
		container.down("#eml_cp_can").observeOnce("click", function(e){
			Event.stop(e);
			hideSelector();
		}.bind(this));
		currentAtt.addClassName("active");
		container.show();
	},
	
	parseBodies : function (transport){
		var xmlDoc = transport.responseXML;
		var html = XPathSelectSingleNode(xmlDoc, 'email_body/mimepart[@type="html"]');
		if(html){
			this.iFrame = new Element('iframe');
			this.textareaContainer.insert(this.iFrame);
			this.iFrameContent = html.firstChild.nodeValue;
			this.iFrame.contentDocument.write(this.iFrameContent);
			this.iFrame.setStyle({width: '100%', height: '100%', border: '0px'});
			var reloader = function(){
				this.iFrame.contentDocument.write(this.iFrameContent);
			}.bind(this);
			this.element.observe("editor:enterFSend", reloader);
			this.element.observe("editor:exitFSend", reloader);
		}else{
			var pre = new Element("pre");
			pre.insert(XPathSelectSingleNode(xmlDoc, 'email_body/mimepart[@type="plain"]').firstChild.nodeValue);
			this.textareaContainer.insert(pre);
			pre.setStyle({display:'block',height: '100%'});
		}
	},
	
	parseXmlStructure : function(transport){
		var xmlDoc = transport.responseXML;
		var hContainer = this.element.down("#emlHeaderContainer");
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
					<div class="emlHeader">\
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
				if(siblingName == "X-Attachment-Id"){
					id = XPathGetSingleNodeText(h, "headervalue");
				}
			});			
			attachments[id] = filename;
		});
		if(Object.keys(attachments).length){
			var attachCont = new Element('div', {id:"attachments_container", className:"emlAttachCont", style:"height:"+($('emlHeaderContainer').getHeight()-14)+"px"});
			hContainer.insert({top:attachCont});
			for(var key in attachments){
				var att = new Element("div", {className:"emlAttachment"});
				att.__ATTACHMENT_ID = key;
				att.insert(attachments[key]);
				attachCont.insert(att);
				
				var dlBut = new Element("a", {
								className:"emlAttachmentAction", 
								title:"Download "+attachments[key]});
				dlBut.update(new Element("img", {
								src:window.ajxpResourcesFolder+'/images/actions/16/download_manager.png',
								height: 16,
								width: 16}));
				var cpBut = new Element("a", {className:"emlAttachmentAction", title:"Copy file on server"});
				cpBut.update(new Element("img", {
					src:window.ajxpResourcesFolder+'/images/actions/16/editcopy.png',
					height: 16,
					width: 16}));				
				att.insert({top: dlBut});
				att.insert({top: cpBut});
				
				dlBut.observe("click", this.dlAttachment.bind(this));
				cpBut.observe("click", this.cpAttachment.bind(this));
				dlBut.hide();cpBut.hide();
				
				att.observe("mouseenter", function(e){
					e.target.select("a.emlAttachmentAction").each(Element.show);
				});
				att.observe("mouseleave", function(e){
					if(e.target.hasClassName("active")) return;
					e.target.select("a.emlAttachmentAction").each(Element.hide);
				});
				
			}		
		}
		
		fitHeightToBottom($(this.textareaContainer), $(modal.elementName));
		this.removeOnLoad(this.textareaContainer);
	}	
});