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
SVNLogger = Class.create({
	initialize:function(form){
		this.element = form.select('[id="svnlog_box"]')[0];
		this.container = new Element('div', {
			style:"height:250px;overflow:auto;border:1px solid #ddd;"
		});
		this.element.insert(this.container);
		this.template = new Template('<div style="#{cssStyle}"><b>#{revString} :</b> #{revision} #{downloadLink}<br><b>#{authString} :</b> #{author}<br><b>#{dateString} :</b> #{date}<br><b>#{messString} :</b> #{message}</div>');
		this.downloadTemplate = new Template('(<a href="content.php?get_action=svndownload&file=#{fileName}&rev=#{revision}">#{downloadString}</a>)');
		this.revMessage = MessageHash[243];
		this.authorMessage = MessageHash[244];
		this.dateMessage = MessageHash[245];
		this.messMessage = MessageHash[246];
		this.downMessage = MessageHash[88];
	},
	
	open: function(fileName){
		this.fileName = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'svnlog');
		connexion.addParameter('file', fileName);
		connexion.onComplete = this.displayResponse.bind(this);
		this.setOnLoad();
		connexion.sendAsync();
	},
	
	addEntry:function(revision,author,date,message, isFile, isLast){
		var dLink = (isFile?this.downloadTemplate.evaluate({
			fileName:this.fileName, 
			revision:revision,
			downloadString:this.downMessage
		}):"");		
		this.container.insert(this.template.evaluate({
			revision:revision,
			author:author,
			date:date,
			message:message,
			downloadLink:dLink,			
			cssStyle:(isLast?"padding:5px":"padding:5px;border-bottom:1px solid #79f"),
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
		var log = root.childNodes[0];
		for(var i=0; i<log.childNodes.length;i++){
			var entry = log.childNodes[i];
			var revision = entry.getAttribute("revision");
			var isFile = entry.getAttribute("is_file");
			isFile = (isFile=='1'?true:false);
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
			this.addEntry(revision,author,date,message, isFile, (i==log.childNodes.length-1));
		}
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