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
 * Description : Simple display of SVN logs.
 */
Class.create("SVNLogger", {
initialize:function(form){
		this.element = form.select('[id="svnlog_box"]')[0];
        this.element.up('div.dialogContent').setStyle({padding:0});
        this.element.setStyle({
            height:'315px',
            position:'relative'
        });
        this.versionsDm = new AjxpDataModel(true);
        this.versionsRoot = new AjxpNode("/", false, "Versions", "folder.png");
        this.versionsDm.setRootNode(this.versionsRoot);

        this.container = this.element;
        /*
        this.container = new Element('div', {
			style:"height:310px;overflow:auto;"
		});
		this.element.insert(this.container);
		*/
		this.template = new Template('<div style="padding: 0px; border: 1px solid rgb(204, 204, 204); margin: 5px 8px 8px 4px;"><div style="padding: 3px; background-color: rgb(238, 238, 238);#{cssStyle}"><b>#{dateString} :</b> #{date} &nbsp;&nbsp;&nbsp;&nbsp;<b>#{revString} :</b> #{revision} &nbsp;&nbsp;&nbsp;&nbsp;<b>#{authString} :</b> #{author}<br></div><div style="padding: 3px;color:#333;word-wrap:break-word;">#{message}</div><div style="text-align: right; padding: 3px;">#{downloadLink}</div></div>');
		this.downloadTemplate = new Template('<a style="color:#79f;font-weight:bold;" data-ajxp_download="'+window.ajxpServerAccessPath+'&get_action=svndownload&file=#{fileName}&revision=#{revision}" href="#">#{downloadString}</a>');
		this.revertTemplate = new Template('<a style="color:#79f;font-weight:bold;" data-ajxp_url="'+window.ajxpServerAccessPath+'&get_action=revert_file&file=#{fileName}&revision=#{revision}" href="#" data-ajxp_confirm="#{confirmRevertString}">#{revertString}</a>');
		this.compareTemplate = new Template('<a style="color:#79f;font-weight:bold;" data-ajxp_url="'+window.ajxpServerAccessPath+'&get_action=revert_file&compare=true&file=#{fileName}&revision=#{revision}" href="#">#{revertString}</a>');
		this.switchTemplate = new Template('<a style="color:#79f;font-weight:bold;" data-ajxp_url="'+window.ajxpServerAccessPath+'&get_action=svnswitch&revision=#{revision}" href="#">#{switchString}</a>');
		this.revMessage = MessageHash['243'];
		this.authorMessage = MessageHash['244'];
		this.dateMessage = MessageHash['245'];
		this.messMessage = MessageHash['246'];
		this.downMessage = MessageHash['88'];
		this.revertMessage = MessageHash['meta.svn.32'];
		this.confirmRevertMessage = MessageHash['meta.svn.33'];
		this.compareMessage = MessageHash['meta.svn.31'];
		this.switchMessage = MessageHash['meta.svn.3'];
		if(!$('svndownload_iframe')){
			$('hidden_frames').insert('<iframe id="svndownload_iframe" name="svndownload_iframe" style="display:none"></iframe>');
		}
	},
	
	open: function(currentRep){
		var selection = ajaxplorer.getUserSelection();
        var ajxpNode;
		if(currentRep || selection.isEmpty()){
			ajxpNode = ajaxplorer.getContextNode();
		}else{
			ajxpNode = selection.getUniqueNode();
		}
		this.fileName = ajxpNode.getPath();
		this.isFile = ajxpNode.isLeaf();
        this.currentFileMetadata = ajxpNode.getMetadata();

        if(this.isFile){
            this.filesList = new FilesList(this.element, {
                dataModel:this.versionsDm,
                columnsDef:[{attributeName:"ajxp_label", messageId:1, sortType:'String'},
                            {attributeName:"revision", messageString:'#', sortType:'Number'},
                            {attributeName:"revision_log", messageId:246, sortType:'String'},
                            {attributeName:"revision_date", messageId:4, sortType:'String'},
                            {attributeName:"author", messageId:244, sortType:'String'},
                            {attributeName:"links", messageId:'meta.svn.61', sortType:'String'}
                ],
                defaultSortTypes:["String", "String", "String", "String", "String", "String"],
                columnsTemplate:"svnlog_file",
                selectable: false,
                draggable: false,
                replaceScroller:true,
                displayMode: "list"
            });
        }else{
            this.filesList = new FilesList(this.element, {
                dataModel:this.versionsDm,
                columnsDef:[{attributeName:"revision", messageString:'#', sortType:'Number'},
                            {attributeName:"revision_log", messageId:246, sortType:'String'},
                            {attributeName:"revision_date", messageId:4, sortType:'String'},
                            {attributeName:"author", messageId:244, sortType:'String'},
                            {attributeName:"links", messageId:'meta.svn.61', sortType:'String'}
                ],
                defaultSortTypes:["Number", "String", "String", "String", "String"],
                columnsTemplate:"svnlog_folder",
                selectable: false,
                draggable: false,
                replaceScroller:true,
                displayMode: "list"
            });
        }

		var connexion = new Connexion();
		connexion.addParameter('get_action', 'svnlog');
		connexion.addParameter('file', this.fileName);
		connexion.onComplete = this.displayResponse.bind(this);
		this.setOnLoad();
		connexion.sendAsync();
    },

addEntry:function(index, revision,author,date,message){
        var separator = '||';
        if(message.indexOf(separator)>-1){
            var split = message.split(separator);
            author = split[1];
            if( this.isFile && split[2].toLowerCase().startsWith("rename") && split[3]){
                var pfn = getBaseName($A(split[3].split("item:")).last());
                if(pfn.indexOf(".") !== -1) var previousFileName = pfn;
            }
            message = "<b>"+split[2].toUpperCase()+"</b>";
            if(split[3]){
                message += " : "+split[3];
            }
        }

        var dateParts = date.split('\.');

		date = dateParts[0].replace("T", " ");
        var skipSwitch = false;
        var cssStyle = '';
        if(this.currentRev){
            if(this.currentRev.firstChild.nodeValue == revision){
                skipSwitch = true;
                cssStyle = "background-color:#efe;";
            }
        }else if(this.revisionRange){
            var start = parseInt(this.revisionRange.getAttribute('start'));
            var end = parseInt(this.revisionRange.getAttribute('end'));
            var rev = parseInt(revision);
            if(start <= rev && rev <= end){
                cssStyle = "background-color:#fee;";
            }
        }

        var dLink = (this.isFile?this.downloadTemplate.evaluate({
            fileName:this.fileName,
            revision:revision,
            downloadString:this.downMessage
        }):(!skipSwitch?this.switchTemplate.evaluate({
            revision:revision,
            switchString:this.switchMessage
        }):''));
        if(this.isFile){
            var dLink2 = this.revertTemplate.evaluate({
                fileName:this.fileName,
                revision:revision,
                revertString:this.revertMessage,
                confirmRevertString:this.confirmRevertMessage
            });
            var dLink3 = this.compareTemplate.evaluate({
                fileName:this.fileName,
                revision:revision,
                revertString:this.compareMessage
            });
            dLink = dLink + " | " + dLink2 + " | " + dLink3;
        }


        if(this.filesList){

            var node = new AjxpNode("/"+revision, true, "Revision "+revision, "mime_empty.png");
            if(this.previousFileName){
                node.getMetadata().set('text', this.previousFileName);
            }else{
                node.getMetadata().set('text', getBaseName(this.fileName));
            }
            node.getMetadata().set('icon', this.currentFileMetadata.get('icon'));
            node.getMetadata().set('revision_log', message);
            node.getMetadata().set('revision_date', date.split(' ')[0]);
            node.getMetadata().set('revision', revision);
            node.getMetadata().set('author', author);
            if(index > 0){
                node.getMetadata().set('links', dLink);
            }else{
                node.getMetadata().set('links', "");
            }
            this.versionsRoot.addChild(node);

        }else{

            this.container.insert(this.template.evaluate({
                revision:revision,
                author:author,
                date:date,
                message:message,
                downloadLink:dLink,
                cssStyle:cssStyle,
                revString:this.revMessage,
                authString:this.authorMessage,
                dateString:this.dateMessage,
                messString:this.messMessage
            }));
        }

        if(previousFileName) this.previousFileName = previousFileName;

	},
	
	displayResponse: function(transport){
		var oXmlDoc = transport.responseXML;
		if(oXmlDoc == null || oXmlDoc.documentElement == null) return;
		var root = oXmlDoc.documentElement;
		if(!root.childNodes.length) return;
		var logEntries = XPathSelectNodes(root, "log/logentry");
		this.currentRev = XPathSelectSingleNode(root, "current_revision");
		this.revisionRange = XPathSelectSingleNode(root, "revision_range");
        var i =0;
		try{
			for(i=0; i<logEntries.length;i++){
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
				this.addEntry(i, revision,author,date,message);
			}
		}catch(e){
			ajaxplorer.displayMessage("ERROR", e.description + "(current index : "+i+")");
		}finally{
			this.removeOnLoad();
		}
        if(this.filesList){
            this.filesList.reload();
        }
		this.container.select("a").invoke("observe", "click", function(e){
			var a = e.findElement();
            var confirm = a.getAttribute("data-ajxp_confirm");
            if(confirm){
                var res = window.confirm(confirm);
                if(!res) return;
            }
			if(a.getAttribute("data-ajxp_url")){
				var conn = new Connexion(a.getAttribute("data-ajxp_url"));
				conn.onComplete = function(){
					ajaxplorer.fireContextRefresh()
					hideLightBox();
				};
				conn.sendAsync();
			}else if(a.getAttribute("data-ajxp_download")){
				$('svndownload_iframe').src = a.getAttribute("data-ajxp_download");
			}
			Event.stop(e);
		});
	},
	close:function(){
		
	},
	setOnLoad:function(){
		addLightboxMarkupToElement(this.container);
		var img = new Element("img", {
			src:ajxpResourcesFolder+'/images/loadingImage.gif',
			style:'margin-top:80px;'
		});
		this.container.down("#element_overlay").insert(img);		
	},
	removeOnLoad:function(){
		removeLightboxFromElement(this.container);
	}
});
