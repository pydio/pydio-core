Editor = Class.create({

	initialize: function(oFormObject)
	{
		this.oForm = $(oFormObject);
		this.closeButton = oFormObject.select('a[id="closeButton"]')[0];
		this.saveButton = oFormObject.select('a[id="saveButton"]')[0];
		this.downloadButton = oFormObject.select('a[id="downloadFileButton"]')[0];
		this.ficInput = oFormObject.select('input[name="file"]')[0];
		this.repInput = oFormObject.select('input[name="dir"]')[0];	
		this.closeButton.observe('click', function(){
			if(this.modified && !window.confirm(MessageHash[201])){
					return false;
			}
			this.close();
			hideLightBox(true);
			return false;
		}.bind(this));
		this.saveButton.observe('click', function(){
			this.saveFile();
			return false;
		}.bind(this));
		this.downloadButton.observe('click', function(){
			if(!this.currentFile) return;		
			document.location.href = 'content.php?action=download&file='+this.currentFile;
			return false;
		}.bind(this));
		modal.setCloseAction(function(){this.close();}.bind(this));
	},
	
	
	createEditor : function(fileName){
	
		var cpStyle = editWithCodePress(getBaseName(fileName));
		var textarea;
		this.textareaContainer = document.createElement('div');
		this.textarea = $(document.createElement('textarea'));
		if(cpStyle != "")
		{
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = hidden.id = 'code';		
			this.oForm.appendChild(hidden);
			this.textarea.name = this.textarea.id = 'cpCode';
			$(this.textarea).addClassName('codepress');
			$(this.textarea).addClassName(cpStyle);
			$(this.textarea).addClassName('linenumbers-on');
			this.currentUseCp = true;
		}
		else
		{
			this.textarea.name =  this.textarea.id = 'code';
			this.textarea.addClassName('dialogFocus');
			this.textarea.addClassName('editor');
			this.currentUseCp = false;
		}
		this.textarea.setStyle({width:'100%'});	
		this.textarea.setAttribute('wrap', 'off');	
		this.oForm.appendChild(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		fitHeightToBottom($(this.textarea), $(modal.elementName), 5, true);
	},
	
	loadFile : function(fileName){
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'edit');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){this.parseTxt(transp);}.bind(this);
		this.changeModifiedStatus(false);
		this.setOnLoad();
		connexion.sendAsync();
	},
	
	saveFile : function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'edit');
		connexion.addParameter('save', '1');
		var value;
		if(this.currentUseCp) value = this.oForm.select('iframe')[0].getCode();
		else value = this.textarea.value;
		connexion.addParameter('code', value);
		connexion.addParameter('file', this.ficInput.value);
		connexion.addParameter('dir', this.repInput.value);	
		connexion.onComplete = function(transp){this.parseXml(transp);}.bind(this);
		this.setOnLoad();
		connexion.setMethod('put');
		connexion.sendAsync();
	},
	
	parseXml : function(transport){
		//alert(transport.responseText);
		this.changeModifiedStatus(false);
		this.removeOnLoad();
	},
	
	parseTxt : function(transport){	
		this.textarea.value = transport.responseText;
		var contentObserver = function(el, value){
			this.changeModifiedStatus(true);
		}.bind(this);
		if(this.currentUseCp) {
			this.textarea.id = 'cpCode_cp';
			code = new CodePress(this.textarea, contentObserver);
			this.cpCodeObject = code;
			this.textarea.parentNode.insertBefore(code, this.textarea);
		}
		else{
			new Form.Element.Observer(this.textarea, 0.2, contentObserver);
		}
		this.removeOnLoad();
		
	},
	
	changeModifiedStatus : function(bModified){
		this.modified = bModified;
		var crtTitle = modal.dialogTitle.select('span.titleString')[0];
		if(this.modified){
			this.saveButton.removeClassName('disabled');
			if(crtTitle.innerHTML.charAt(crtTitle.innerHTML.length - 1) != "*"){
				crtTitle.innerHTML  = crtTitle.innerHTML + '*';
			}
		}else{
			this.saveButton.addClassName('disabled');
			if(crtTitle.innerHTML.charAt(crtTitle.innerHTML.length - 1) == "*"){
				crtTitle.innerHTML  = crtTitle.innerHTML.substring(0, crtTitle.innerHTML.length - 1);
			}		
		}
		// ADD / REMOVE STAR AT THE END OF THE FILENAME
	},
	
	setOnLoad : function(){	
		addLightboxMarkupToElement(this.textareaContainer);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+"/images/loadingImage.gif";
		$(this.textareaContainer).select("#element_overlay")[0].appendChild(img);
		this.loading = true;
	},
	
	removeOnLoad : function(){
		removeLightboxFromElement(this.textareaContainer);
		this.loading = false;	
	},
	
	close : function(){
		if(this.currentUseCp){
			this.cpCodeObject.close();
			modal.clearContent(modal.dialogContent);		
		}
	}
});