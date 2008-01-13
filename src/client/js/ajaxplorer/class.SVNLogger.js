SVNLogger = Class.create({
	initialize:function(form){
		this.element = form.select('[id="svnlog_box"]')[0];
		this.template = new Template('<div style="border-bottom:1px solid #79f"><b>Revision :</b> #{revision}<br><b>Author :</b> #{author}<br><b>Date :</b> #{date}<br><b>Message :</b> #{message}</div>');
	},
	
	open: function(fileName){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'log');
		connexion.addParameter('file', fileName);
		connexion.onComplete = this.displayResponse.bind(this);
		connexion.sendAsync();		
	},
	
	addEntry:function(revision,author,date,message){
		this.element.insert(this.template.evaluate({
			revision:revision,
			author:author,
			date:date,
			message:message
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
			this.addEntry(revision,author,date,message);
		}
	}
});