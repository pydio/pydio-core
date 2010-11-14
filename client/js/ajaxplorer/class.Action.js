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
 * Description : A "Command" object, encapsulating its callbacks, display attributes, etc.
 */
Class.create("Action", {

	__DEFAULT_ICON_PATH : "/images/actions/ICON_SIZE",
	
	initialize:function(){
		this.options = Object.extend({
			name:'',
			src:'',
			text:'',
			title:'',
			text_id:'',
			title_id:'',
			hasAccessKey:false,
			accessKey:'',
			subMenu:false,
			subMenuUpdateImage:false,
			subMenuUpdateTitle:false,
			callbackCode:'',
			callbackDialogNode:null,
			callback:Prototype.emptyFunction,
			prepareModal:false, 
			listeners : [],
			formId:undefined, 
			formCode:undefined
			}, arguments[0] || { });
		this.context = Object.extend({
			selection:true,
			dir:false,
			allowedMimes:$A([]),
			root:true,
			inZip:true,
			recycle:false,
			behaviour:'hidden',
			actionBar:false,
			actionBarGroup:'default',
			contextMenu:false,
			ajxpWidgets:null,
			infoPanel:false			
			}, arguments[1] || { });
			
		this.selectionContext = Object.extend({			
			dir:false,
			file:true,
			recycle:false,
			behaviour:'disabled',
			allowedMimes:$A([]),			
			unique:true,
			multipleOnly:false
			}, arguments[2] || { });
		this.rightsContext = Object.extend({			
			noUser:true,
			userLogged:true,
			guestLogged:false,
			read:true,
			write:false,
			adminOnly:false
			}, arguments[3] || { });
		this.subMenuItems = Object.extend({
			staticItems:null,
			dynamicItems:null,
			dynamicBuilderCode:null
		}, arguments[4] || {});
		this.elements = new Array();
		this.contextHidden = false;
		this.deny = false;
		if(this.context.subMenu){
			if(!this.options.actionBar){
				alert('Warning, wrong action definition. Cannot use a subMenu if not displayed in the actionBar!');			
			}
		}
		
	}, 
	
	setManager : function(manager){
		this.manager = manager;
		if(this.options.subMenu){
			if(this.subMenuItems.staticItems){
				this.buildSubmenuStaticItems();
			}
			if(this.subMenuItems.dynamicItems || this.subMenuItems.dynamicBuilderCode){
				this.prepareSubmenuDynamicBuilder();
			}
		}
		if(this.options.listeners['init']){				
			try{
				window.listenerContext = this;
				if(Object.isString(this.options.listeners['init'])){
					this.options.listeners['init'].evalScripts();
				}else{
					this.options.listeners['init']();
				}
			}catch(e){
				alert(e);
			}
		}		
	},
	
	apply: function(){
		if(this.deny) return;
		if(this.options.prepareModal){
			modal.prepareHeader(
				this.options.title, 
				resolveImageSource(this.options.src,this.__DEFAULT_ICON_PATH, 16)
			);
		}
		window.actionArguments = $A([]);
		if(arguments[0]) window.actionArguments = $A(arguments[0]);
		if(this.options.callbackCode) {
			try{
				this.options.callbackCode.evalScripts();
			}catch(e){
				ajaxplorer.displayMessage('ERROR', e.message);
			}
		}else if(this.options.callbackDialogNode){
			var node = this.options.callbackDialogNode;
			var dialogFormId = node.getAttribute("dialogOpenForm");
			var okButtonOnly = (node.getAttribute("dialogOkButtonOnly") === "true")?true:false ;
			var skipButtons = (node.getAttribute("dialogSkipButtons") === "true")?true:false ;
			
			var onOpenFunc = null; var onCompleteFunc = null; var onCancelFunc = null;
			var onOpenNode = XPathSelectSingleNode(node, "dialogOnOpen");
			if(onOpenNode && onOpenNode.firstChild) var onOpenFunc = new Function("oForm", onOpenNode.firstChild.nodeValue);
			var onCompleteNode = XPathSelectSingleNode(node, "dialogOnComplete");
			if(onCompleteNode && onCompleteNode.firstChild) {
				var completeCode = onCompleteNode.firstChild.nodeValue;
				if(onCompleteNode.getAttribute("hideDialog") === "true"){
					completeCode += "hideLightBox(true);";
				}
				var onCompleteFunc = new Function("oForm", completeCode);
			}
			var onCancelNode = XPathSelectSingleNode(node, "dialogOnCancel");
			if(onCancelNode && onCancelNode.firstChild) var onCancelFunc = new Function("oForm", onCompleteNode.firstChild.nodeValue);
			
			this.options.callback = function(){
				modal.showDialogForm('Dialog', dialogFormId, onOpenFunc, onCompleteFunc, onCancelFunc, okButtonOnly, skipButtons);
			};
			this.options.callback();
			this.options.callbackDialogNode = null;
		}else if(this.options.callback){
			this.options.callback();
		}
		if(this.options.subMenu && arguments[0] && arguments[0][0]){
			this.notify("submenu_active", arguments[0][0]);
		}
		window.actionArguments = null;
	},
		
	fireContextChange: function(){
		if(arguments.length < 4) return;
		var usersEnabled = arguments[0];
		var crtUser = arguments[1];
		var crtIsRecycle = arguments[2];
		var crtInZip = arguments[3];
		var crtIsRoot = arguments[4];
		var crtAjxpMime = arguments[5] || '';
		if(this.options.listeners["contextChange"]){
			window.listenerContext = this;
			this.options.listeners["contextChange"].evalScripts();			
		}		
		var rightsContext = this.rightsContext;
		if(!rightsContext.noUser && !usersEnabled){
			return this.hideForContext();				
		}
		if((rightsContext.userLogged == 'only' && crtUser == null) ||
			(rightsContext.guestLogged && rightsContext.guestLogged=='hidden' & crtUser!=null && crtUser.id=='guest')){
			return this.hideForContext();
		}
		if(rightsContext.userLogged == 'hidden' && crtUser != null && !(crtUser.id=='guest' && rightsContext.guestLogged && rightsContext.guestLogged=='show') ){
			return this.hideForContext();
		}
		if(rightsContext.adminOnly && (crtUser == null || !crtUser.isAdmin)){
			return this.hideForContext();
		}
		if(rightsContext.read && crtUser != null && !crtUser.canRead()){
			return this.hideForContext();
		}
		if(rightsContext.write && crtUser != null && !crtUser.canWrite()){
			return this.hideForContext();
		}
		if(this.context.allowedMimes.length){
			if(!this.context.allowedMimes.indexOf(crtAjxpMime)==-1){
				return this.hideForContext();
			}
		}
		if(this.context.recycle){
			if(this.context.recycle == 'only' && !crtIsRecycle){
				return this.hideForContext();				
			}
			if(this.context.recycle == 'hidden' && crtIsRecycle){
				return this.hideForContext();
			}
		}
		if(!this.context.inZip && crtInZip){
			return this.hideForContext();
		}
		if(!this.context.root && crtIsRoot){
			return this.hideForContext();
		}
		this.showForContext();				
		
	},
		
	fireSelectionChange: function(){
		if(this.options.listeners["selectionChange"]){
			window.listenerContext = this;
			this.options.listeners["selectionChange"].evalScripts();			
		}
		if(arguments.length < 1 
			|| this.contextHidden 
			|| !this.context.selection) {	
			return;
		}
		var userSelection = arguments[0];		
		var bSelection = false;
		if(userSelection != null) 
		{			
			bSelection = !userSelection.isEmpty();
			var bUnique = userSelection.isUnique();
			var bFile = userSelection.hasFile();
			var bDir = userSelection.hasDir();
			var bRecycle = userSelection.isRecycle();
		}
		var selectionContext = this.selectionContext;
		if(selectionContext.allowedMimes.size()){
			if(selectionContext.behaviour == 'hidden') this.hide();
			else this.disable();
		}
		if(selectionContext.unique && !bUnique){
			return this.disable();
		}
		if((selectionContext.file || selectionContext.dir) && !bFile && !bDir){
			return this.disable();
		}
		if((selectionContext.dir && !selectionContext.file && bFile) 
			|| (!selectionContext.dir && selectionContext.file && bDir)){
			return this.disable();
		}
		if(!selectionContext.recycle && bRecycle){
			return this.disable();
		}
		if((selectionContext.allowedMimes.size() && userSelection && !userSelection.hasMime(selectionContext.allowedMimes)) 
			&& !(selectionContext.dir && bDir)){
			if(selectionContext.behaviour == 'hidden') return this.hide();
			else return this.disable();
		}
		this.show();
		this.enable();
		
	},
		
	createFromXML:function(xmlNode){
		this.options.name = xmlNode.getAttribute('name');
		for(var i=0; i<xmlNode.childNodes.length;i++){
			var node = xmlNode.childNodes[i];
			var defaultAttributes = $H({
				dir:"dirDefault", 
				file:"fileDefault", 
				dragndrop:"dragndropDefault",
				ctrldragndrop:"ctrlDragndropDefault",
				expire:"expireDefault"
			});
			defaultAttributes.each(function(att){
				if(xmlNode.getAttribute(att.value) && xmlNode.getAttribute(att.value) == "true"){
					if(!this.defaults) this.defaults = {};
					this.defaults[att.key] = true;
				}
			}.bind(this));
			if(node.nodeName == "processing"){
				for(var j=0; j<node.childNodes.length; j++){
					var processNode = node.childNodes[j];
					if(processNode.nodeName == "clientForm"){
						this.options.formId = processNode.getAttribute("id");
						this.options.formCode = processNode.firstChild.nodeValue;
						this.insertForm();
					}else if(processNode.nodeName == "clientCallback"){						
						if(processNode.getAttribute('prepareModal') && processNode.getAttribute('prepareModal') == "true"){
							this.options.prepareModal = true;						
						}
						if(processNode.getAttribute('dialogOpenForm')){
							this.options.callbackDialogNode = processNode;
						}else if(processNode.firstChild){
							this.options.callbackCode = '<script>'+processNode.firstChild.nodeValue+'</script>';
						}
					}else if(processNode.nodeName == "clientListener" && processNode.firstChild){						
						this.options.listeners[processNode.getAttribute('name')] = '<script>'+processNode.firstChild.nodeValue+'</script>';
					}
				}
			}else if(node.nodeName == "gui"){
				this.options.text_id = node.getAttribute('text');
				this.options.title_id = node.getAttribute('title');
				this.options.text = MessageHash[node.getAttribute('text')];
				this.options.title = MessageHash[node.getAttribute('title')];
				this.options.src = node.getAttribute('src');								
				if(node.getAttribute('hasAccessKey') && node.getAttribute('hasAccessKey') == "true"){
					this.options.accessKey = node.getAttribute('accessKey');
					this.options.hasAccessKey = true;
				}
				for(var j=0; j<node.childNodes.length;j++){
					if(node.childNodes[j].nodeName == "context"){
						this.attributesToObject(this.context, node.childNodes[j]);
						if(this.context.ajxpWidgets){
							this.context.ajxpWidgets = $A(this.context.ajxpWidgets.split(','));
						}else{
							this.context.ajxpWidgets = $A();
						}
						// Backward compatibility
						if(this.context.infoPanel) this.context.ajxpWidgets.push('InfoPanel');
						if(this.context.actionBar) this.context.ajxpWidgets.push('ActionsToolbar');
					}
					else if(node.childNodes[j].nodeName == "selectionContext"){
						this.attributesToObject(this.selectionContext, node.childNodes[j]);
					}
				}
							
			}else if(node.nodeName == "rightsContext"){
				this.attributesToObject(this.rightsContext, node);
			}else if(node.nodeName == "subMenu"){
				this.options.subMenu = true;
				if(node.getAttribute("updateImageOnSelect") && node.getAttribute("updateImageOnSelect") == "true"){
					this.options.subMenuUpdateImage = true;
				}
				if(node.getAttribute("updateTitleOnSelect") && node.getAttribute("updateTitleOnSelect") == "true"){
					this.options.subMenuUpdateTitle = true;
				}
				for(var j=0;j<node.childNodes.length;j++){
					if(node.childNodes[j].nodeName == "staticItems" || node.childNodes[j].nodeName == "dynamicItems"){
						this.subMenuItems[node.childNodes[j].nodeName] = [];
						for(var k=0;k<node.childNodes[j].childNodes.length;k++){
							if(node.childNodes[j].childNodes[k].nodeName == "item"){
								var item = {};
								for(var z=0;z<node.childNodes[j].childNodes[k].attributes.length;z++){
									var attribute = node.childNodes[j].childNodes[k].attributes[z];
									item[attribute.nodeName] = attribute.nodeValue;
								}
								this.subMenuItems[node.childNodes[j].nodeName].push(item);
							}
						}
					}else if(node.childNodes[j].nodeName == "dynamicBuilder"){
						this.subMenuItems.dynamicBuilderCode = '<script>'+node.childNodes[j].firstChild.nodeValue+'</script>';
					}
				}
			}
		}
		if(!this.options.hasAccessKey) return;
		if(this.options.accessKey == '' 
			|| !MessageHash[this.options.accessKey] 
			|| this.options.text.indexOf(MessageHash[this.options.accessKey]) == -1)
		{
			this.options.accessKey == this.options.text.charAt(0);
		}else{
			this.options.accessKey = MessageHash[this.options.accessKey];
		}		
	}, 
	
	buildSubmenuStaticItems : function(){
		var menuItems = [];
		if(this.subMenuItems.staticItems){
			this.subMenuItems.staticItems.each(function(item){
				var itemText = MessageHash[item.text];
				if(item.hasAccessKey && (item.hasAccessKey=='true' || item.hasAccessKey===true) && MessageHash[item.accessKey]){
					itemText = this.getKeyedText(MessageHash[item.text],true,MessageHash[item.accessKey]);
					if(!this.subMenuItems.accessKeys) this.subMenuItems.accessKeys = [];
					this.manager.registerKey(MessageHash[item.accessKey],this.options.name, item.command);					
				}
				menuItems.push({
					name:itemText,
					alt:MessageHash[item.title],
					image:resolveImageSource(item.src, '/images/actions/ICON_SIZE', 22),
					isDefault:(item.isDefault?true:false),
					callback:function(e){this.apply([item]);}.bind(this)
				});
			}, this);
		}
		this.subMenuItems.staticOptions = menuItems;
	},
	
	prepareSubmenuDynamicBuilder : function(){		
		this.subMenuItems.dynamicBuilder = function(protoMenu){
			setTimeout(function(){
				if(this.subMenuItems.dynamicBuilderCode){
					window.builderContext = this;
					this.subMenuItems.dynamicBuilderCode.evalScripts();
					var menuItems = this.builderMenuItems || [];					
				}else{
			  		var menuItems = [];
			  		this.subMenuItems.dynamicItems.each(function(item){
			  			var action = this.manager.actions.get(item['actionId']);
			  			if(action.deny) return;
						menuItems.push({
							name:action.getKeyedText(),
							alt:action.options.title,
							image:resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16),						
							callback:function(e){this.apply();}.bind(action)
						});
			  		}, this);
				}
			  	protoMenu.options.menuItems = menuItems;
			  	protoMenu.refreshList();
			}.bind(this),0);
		}.bind(this);		
	},
	
	setIconSrc : function(newSrc){
		this.options.src = newSrc;
		if($(this.options.name +'_button_icon')){
			$(this.options.name +'_button_icon').src = resolveImageSource(this.options.src,this.__DEFAULT_ICON_PATH, 22);
		}		
	},
	
	setLabel : function(newLabel, newTitle){
		this.options.text = MessageHash[newLabel];
		if($(this.options.name+'_button_label')){
			$(this.options.name+'_button_label').update(this.getKeyedText());
		}
		if(!newTitle) return;
		this.options.title = MessageHash[newTitle];
		if($(this.options.name+'_button_icon')){
			$(this.options.name+'_button_icon').title = this.options.title;
		}
	},
	
	refreshFromI18NHash : function(){
		var text; var title;
		this.setLabel(this.options.text_id, this.options.title_id);
	},
	
	toInfoPanel:function(){
		return this.options;
	},
	
	toContextMenu:function(){
		return this.options;
	},
	
	hideForContext: function(){
		this.hide();
		this.contextHidden = true;
	},
	
	showForContext: function(){
		this.show();
		this.contextHidden = false;
	},
	
	hide: function(){		
		this.deny = true;
		this.notify('hide');
	},
	
	show: function(){
		this.deny = false;
		this.notify('show');
	},
	
	disable: function(){
		this.deny = true;
		this.notify('disable');
	},
	
	enable: function(){
		this.deny = false;
		this.notify('enable');
	},
	
	remove: function(){
		// Remove all elements and forms from html
		this.elements.each(function(el){
			$(el).remove();
		}.bind(this));		
		this.elements = new Array();
		if(this.options.formId && $('all_forms').select('[id="'+this.options.formId+'"]').length){
			$('all_forms').select('[id="'+this.options.formId+'"]')[0].remove();
		}
	},
	
	getKeyedText: function(displayString, hasAccessKey, accessKey){
		if(!displayString){
			displayString = this.options.text;
		}
		if(!hasAccessKey){
			hasAccessKey = this.options.hasAccessKey;
		}
		if(!accessKey){
			accessKey = this.options.accessKey;
		}
		if(!hasAccessKey) return displayString;
		var keyPos = displayString.toLowerCase().indexOf(accessKey.toLowerCase());
		if(keyPos==-1){
			return displayString + ' (<u>' + accessKey + '</u>)';
		}
		if(displayString.charAt(keyPos) != accessKey){
			// case differ
			accessKey = displayString.charAt(keyPos);
		}
		returnString = displayString.substring(0,displayString.indexOf(accessKey));
		returnString += '<u>'+accessKey+'</u>';
		returnString += displayString.substring(displayString.indexOf(accessKey)+1, displayString.length);
		return returnString;
	},
	
	insertForm: function(){
		if(!this.options.formCode || !this.options.formId) return;
		if($('all_forms').select('[id="'+this.options.formId+'"]').length) return;
		$('all_forms').insert(this.options.formCode);
	},
	
	attributesToObject: function(object, node){
		Object.keys(object).each(function(key){
			if(node.getAttribute(key)){
				value = node.getAttribute(key);
				if(value == 'true') value = true;
				else if(value == 'false') value = false;
				if(key == 'allowedMimes'){
					if(value && value.split(',').length){
						value = $A(value.split(','));
					}else{
						value = $A([]);
					}					
				}
				this[key] = value;
			}
		}.bind(object));
	}

});