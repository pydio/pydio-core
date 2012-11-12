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
		this.textareaContainer = new Element('div');
		this.contentMainContainer = this.textareaContainer;
		this.textareaContainer.setStyle({width:'100%', overflow:'auto'});	
		this.element.insert(this.textareaContainer);
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
		var user = ajaxplorer.user;
		if(user) var activeRepository = user.getActiveRepository();
		if(user && user.canCrossRepositoryCopy() && user.hasCrossRepositories()){
			var firstKey ;
			var reposList = new Hash();
			user.getCrossRepositories().each(function(pair){
				if(!firstKey) firstKey = pair.key;
				reposList.set(pair.key, pair.value.getLabel());								
			}.bind(this));
			if(!user.canWrite()){
				var nodeProvider = new RemoteNodeProvider();
				nodeProvider.initProvider({tmp_repository_id:firstKey});
				var rootNode = new AjxpNode("/", false, MessageHash[373], "folder.png", nodeProvider);								
				this.treeSelector.load(rootNode);
			}else{
				this.treeSelector.load();								
			}
			this.treeSelector.setFilterShow(true);							
			reposList.each(function(pair){
				this.treeSelector.appendFilterValue(pair.key, pair.value);
			}.bind(this)); 
			if(user.canWrite()) this.treeSelector.appendFilterValue(activeRepository, "&lt;"+MessageHash[372]+"&gt;", 'top');
			this.treeSelector.setFilterSelectedIndex(0);
			this.treeSelector.setFilterChangeCallback(function(e){
				externalRepo = this.filterSelector.getValue();
				var nodeProvider = new RemoteNodeProvider();
				nodeProvider.initProvider({tmp_repository_id:externalRepo});
				this.resetAjxpRootNode(new AjxpNode("/", false, MessageHash[373], "folder.png", nodeProvider));
			});
		}else{
			this.treeSelector.load();
		}				
		//this.treeSelector.load();
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
			var actionValue = "eml_cp_attachment";
			var crossCopy = false;
			var crtRepoType = ajaxplorer.user.repositories.get(ajaxplorer.user.activeRepository).accessType;
			if(activeRepository && this.treeSelector.getFilterActive(activeRepository)){
				crossCopy = true;
			}
			var connexion = new Connexion();
			if(crtRepoType == "imap"){
				connexion.setParameters({
					file: this.currentFile+"#attachments/"+attachmentId,
					get_action:crossCopy?"cross_copy":"copy",
					dest:selectedNode,
					dest_repository_id:crossCopy?this.treeSelector.filterSelector.getValue():""
				});				
			}else{
				connexion.setParameters({
					file: this.currentFile,
					get_action:"eml_cp_attachment",
					attachment_id:attachmentId,
					destination:selectedNode,
					dest_repository_id:this.treeSelector.filterSelector.getValue()
				});
                console.log(connexion._parameters);
			}
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
            this.textareaContainer.setStyle({overflowY:'hidden'});
			this.iFrameContent = html.firstChild.nodeValue;
			this.iFrame.contentWindow.document.write(this.iFrameContent);
			this.iFrame.setStyle({width: '100%', height: '100%', border: '0px'});
			if(Prototype.Browser.IE){
				this.element.observeOnce("editor:exitFSend", function(){
					// Fix IE disappearing elements in Quirks Mode
					$('emlHeaderContainer').setStyle({position:"absolute"});
					this.textareaContainer.setStyle({marginTop:$('emlHeaderContainer').getHeight()});					
				}.bind(this) );				
			}else{
				var reloader = function(){
					this.iFrame.contentWindow.document.write(this.iFrameContent);
				}.bind(this);
				this.element.observe("editor:enterFSend", reloader);
				this.element.observe("editor:exitFSend", reloader);
			}
		}else{
			var pre = new Element("pre");
			pre.insert(XPathSelectSingleNode(xmlDoc, 'email_body/mimepart[@type="plain"]').firstChild.nodeValue);
			this.textareaContainer.insert(pre);
            this.textareaContainer.setStyle({overflowY:'auto'});
			pre.setStyle({display:'block',height: '100%',margin:0});
		}
	},
	
	parseXmlStructure : function(transport){
		var xmlDoc = transport.responseXML;
		var hContainer = this.element.down("#emlHeaderContainer");
		// PARSE HEADERS
		var headers = XPathSelectNodes(xmlDoc, "email/header");
		var labels = {"From":"editor.eml.1", "To":"editor.eml.2", "Cc":"editor.eml.12", "Date":"editor.eml.4", "Subject":"editor.eml.3"};;
		var searchedHeaders = {"From":[], "To":[], "Cc":[], "Date":[], "Subject":[]};
		headers.each(function(el){
			var hName = XPathGetSingleNodeText(el, "headername");
			var hValue = XPathGetSingleNodeText(el, "headervalue");
			if(searchedHeaders[hName]){
				if(hValue.strip() != ''){ 
					searchedHeaders[hName].push(hValue.strip().escapeHTML());
				}
			}
		});
		//console.log(searchedHeaders);
		$H(searchedHeaders).each(function(pair){
			if(pair.value.length){
				var value = pair.value.join(", ");
				var label = MessageHash[labels[pair.key]];
				hContainer.insert('\
					<div class="emlHeader">\
						<div class="emlHeaderLabel">'+label+'</div>\
						<div class="emlHeaderContent">'+value+'</div>\
					</div>');
			}
		});
		
		// PARSE ATTACHEMENTS
		// Go throught headers and find Content-Disposition: attachment ones
		var allHeaders = XPathSelectNodes(xmlDoc, "//header");
		var attachments = {};
		var id = 0;
		allHeaders.each(function(el){
			var hName = XPathGetSingleNodeText(el, "headername");
			var hValue = XPathGetSingleNodeText(el, "headervalue");
			if(hName != "Content-Disposition" || hValue != "attachment") return;
			var mimepart = el.parentNode;
			var filename = "";
			// Find filename
			var params = XPathSelectNodes(el, "parameter");
			params.each(function(c){
				if(XPathGetSingleNodeText(c, "paramname") == "filename"){
					filename = XPathGetSingleNodeText(c, "paramvalue");
				}
			});
			// Find attachment ID - not always
			var foundId = false;
			allHeaders.each(function(h){
				if(h.parentNode != mimepart) return;
				var siblingName = XPathGetSingleNodeText(h, "headername");
				var siblingValue = XPathGetSingleNodeText(h, "headervalue");
				if(siblingName == "X-Attachment-Id"){
					id = XPathGetSingleNodeText(h, "headervalue");
					foundId = true;
				}
			});
			attachments[id] = filename;
			if(!foundId){
				id = id+1;
			}
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
								title:MessageHash["editor.eml.10"]+attachments[key]});
				dlBut.update(new Element("img", {
								src:window.ajxpResourcesFolder+'/images/actions/16/download_manager.png',
								height: 16,
								width: 16}));
				var cpBut = new Element("a", {className:"emlAttachmentAction", title:MessageHash["editor.eml.11"]});
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
				if(Prototype.Browser.IE){
					att.observe("mouseenter", function(e){
						e.target.addClassName("ieHover");
					});
					att.observe("mouseleave", function(e){
						e.target.removeClassName("ieHover");
					});
				}
			}		
		}
		
		fitHeightToBottom($(this.textareaContainer), $(modal.elementName));
		this.removeOnLoad(this.textareaContainer);
	},	
	
	attachmentCellRenderer : function(element, ajxpNode, type){
        if(!element) return;
		if(ajxpNode.getMetadata().get("eml_attachments") == "0") {
			if(type == "row") element.update('<span class="text_label"> </span>');
			return;
		}
		element.setStyle({
			backgroundImage:'url("plugins/editor.eml/attach.png")',
			backgroundRepeat: 'no-repeat', 
			backgroundPosition: (type=="thumb" ? '2px 2px': '5px 4px')
		});
		if(type == "row"){
			element.update('<span class="text_label"> </span>');
		}
		element.setAttribute("title", ajxpNode.getMetadata().get("eml_attachments")+" attachments");
	}
});