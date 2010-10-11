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
 * Description : Simple display of SVN logs.
 */
Class.create("SVNLogger", {
	initialize:function(form){
		this.element = form.select('[id="svnlog_box"]')[0];
		this.container = new Element('div', {
			style:"height:310px;overflow:auto;"
		});
		this.element.up('div.dialogContent').setStyle({padding:0});
		this.element.insert(this.container);	
		this.template = new Template('<div style="padding: 0px; border: 1px solid rgb(204, 204, 204); margin: 5px 8px 8px 4px;box-shadow:2px 2px 3px #999999;-moz-box-shadow:2px 2px 3px #999999;-webkit-box-shadow:2px 2px 3px #999999;"><div style="padding: 3px; background-color: rgb(238, 238, 238);#{cssStyle}"><b>#{dateString} :</b> #{date} &nbsp;&nbsp;&nbsp;&nbsp;<b>#{revString} :</b> #{revision} &nbsp;&nbsp;&nbsp;&nbsp;<b>#{authString} :</b> #{author}<br></div><div style="padding: 3px;color:#aaa;">#{message}</div><div style="text-align: right; padding: 3px;">#{downloadLink}</div></div>');
		this.downloadTemplate = new Template('<a style="color:#79f;font-weight:bold;" href="content.php?get_action=svndownload&file=#{fileName}&revision=#{revision}">#{downloadString}</a>');
		this.switchTemplate = new Template('<a style="color:#79f;font-weight:bold;" ajxp_url="content.php?get_action=svnswitch&revision=#{revision}" href="#">#{switchString}</a>');
		this.revMessage = MessageHash[243];
		this.authorMessage = MessageHash[244];
		this.dateMessage = MessageHash[245];
		this.messMessage = MessageHash[246];
		this.downMessage = MessageHash[88];
		this.switchMessage = MessageHash['meta.svn.3'];
	},
	
	open: function(currentRep){
		var selection = ajaxplorer.getUserSelection();
		if(currentRep || selection.isEmpty()){
			var ajxpNode = ajaxplorer.getContextNode();
		}else{
			var ajxpNode = selection.getUniqueNode();
		}
		this.fileName = ajxpNode.getPath();
		this.isFile = ajxpNode.isLeaf();
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'svnlog');
		connexion.addParameter('file', this.fileName);
		connexion.onComplete = this.displayResponse.bind(this);
		this.setOnLoad();
		connexion.sendAsync();
	},
	
	addEntry:function(revision,author,date,message, isCurrentRev){
		var separator = '||';
		if(message.indexOf(separator)>-1){
			var split = message.split(separator);			
			author = split[1];
			message = "<b>"+split[2].toUpperCase()+"</b>";
			if(split[3]){
				message += " : "+split[3];
			}
		}
		
		var dateParts = date.split('\.');
		date = dateParts[0].replace("T", " ");
		
		var dLink = (this.isFile?this.downloadTemplate.evaluate({
			fileName:this.fileName, 
			revision:revision,
			downloadString:this.downMessage
		}):(!isCurrentRev?this.switchTemplate.evaluate({
			revision:revision,
			switchString:this.switchMessage
		}):''));
		this.container.insert(this.template.evaluate({
			revision:revision,
			author:author,
			date:date,
			message:message,
			downloadLink:dLink,			
			cssStyle:(isCurrentRev?"background-color:#fee;":""),
			revString:this.revMessage,
			authString:this.authorMessage,
			dateString:this.dateMessage,
			messString:this.messMessage
		}));
	},
	
	displayResponse: function(transport){
		//alert('received XML!');
		var oXmlDoc = transport.responseXML;
		if(oXmlDoc == null || oXmlDoc.documentElement == null) return;
		if(!oXmlDoc.childNodes.length)return;
		var root = oXmlDoc.childNodes[0];
		if(!root.childNodes.length) return;
		var logEntries = XPathSelectNodes(root, "log/logentry");
		var currentRev = XPathSelectSingleNode(root, "current_revision");
		for(var i=0; i<logEntries.length;i++){
			var entry = logEntries[i];
			var revision = entry.getAttribute("revision");
			var author;var date;var message;
			for(var j=0;j<entry.childNodes.length;j++){
				if(entry.childNodes[j].nodeName == 'author'){
					author = entry.childNodes[j].firstChild.nodeValue;
				}else if(entry.childNodes[j].nodeName == 'date'){
					date = entry.childNodes[j].firstChild.nodeValue;
				}else if(entry.childNodes[j].nodeName == 'msg'){
					message = entry.childNodes[j].firstChild.nodeValue;
				}
			}			
			this.addEntry(revision,author,date,message, (currentRev?(revision==currentRev.firstChild.nodeValue):false));
		}
		this.container.select("a[@ajxp_url]").invoke("observe", "click", function(e){
				var conn = new Connexion(e.findElement().getAttribute("ajxp_url"));
				conn.onComplete = function(){
					ajaxplorer.fireContextRefresh()
					hideLightBox();
				};
				conn.sendAsync();
			});		
		this.removeOnLoad();
	},
	close:function(){
		
	},
	setOnLoad:function(){
		addLightboxMarkupToElement(this.container);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+'/images/loadingImage.gif';
		this.container.getElementsBySelector("#element_overlay")[0].appendChild(img);		
	},
	removeOnLoad:function(){
		removeLightboxFromElement(this.container);
	}
});