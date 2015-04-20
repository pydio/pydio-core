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
 */

/** 
 * A "Command" object, encapsulating its callbacks, display attributes, etc.
 */
Class.create("Action", {

	/**
	 * @var String Default "/images/actions/ICON_SIZE"
	 */
	__DEFAULT_ICON_PATH : "/images/actions/ICON_SIZE",
	
	/**
	 * Standard constructor
	 */
	initialize:function(){
		this.options = Object.extend({
			name:'',
			src:'',
            icon_class:'',
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
            activeCondition:null,
			formId:undefined, 
			formCode:undefined
			}, arguments[0] || { });
		this.context = Object.extend({
			selection:true,
			dir:false,
			allowedMimes:$A([]),
            evalMetadata:'',
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
            evalMetadata:'',
			unique:true,
			multipleOnly:false,
            enableRoot:false
			}, arguments[2] || { });
		this.rightsContext = Object.extend({			
			noUser:true,
			userLogged:true,
			guestLogged:false,
			read:false,
			write:false,
			adminOnly:false
			}, arguments[3] || { });
		this.subMenuItems = Object.extend({
			staticItems:null,
			dynamicItems:null,
			dynamicBuilderCode:null
		}, arguments[4] || {});
		this.elements = $A();
		this.contextHidden = false;
		this.deny = false;
		if(this.context.subMenu){
			if(!this.options.actionBar){
				alert('Warning, wrong action definition. Cannot use a subMenu if not displayed in the actionBar!');			
			}
		}
		
	}, 
	
	/**
	 * Sets the manager for this action
	 * @param manager ActionsManager
	 */
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
	
	/**
	 * Execute the action code
	 */
	apply: function(){
		if(this.deny) return;
        document.fire("ajaxplorer:beforeApply-"+this.options.name);
		if(this.options.prepareModal){
			modal.prepareHeader(
				this.options.title, 
				resolveImageSource(this.options.src,this.__DEFAULT_ICON_PATH, 16),
                this.options.icon_class
			);
		}
		window.actionArguments = $A([]);
		window.actionManager = this.manager;
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
			var okButtonOnly = (node.getAttribute("dialogOkButtonOnly") === "true") ;
			var skipButtons = (node.getAttribute("dialogSkipButtons") === "true") ;
			
			var onOpenFunc = null; var onCompleteFunc = null; var onCancelFunc = null;
			var onOpenNode = XPathSelectSingleNode(node, "dialogOnOpen");
			if(onOpenNode && onOpenNode.firstChild) onOpenFunc = new Function("oForm", onOpenNode.firstChild.nodeValue);
			var onCompleteNode = XPathSelectSingleNode(node, "dialogOnComplete");
			if(onCompleteNode && onCompleteNode.firstChild) {
				var completeCode = onCompleteNode.firstChild.nodeValue;
				if(onCompleteNode.getAttribute("hideDialog") === "true"){
					completeCode += "hideLightBox(true);";
				}
				onCompleteFunc = new Function("oForm", completeCode);
			}
			var onCancelNode = XPathSelectSingleNode(node, "dialogOnCancel");
			if(onCancelNode && onCancelNode.firstChild) onCancelFunc = new Function("oForm", onCancelNode.firstChild.nodeValue);
			
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
		window.actionManager = null;
        document.fire("ajaxplorer:afterApply-"+this.options.name);
	},
		
	/**
	 * Updates the action status on context change
	 * @returns void
	 */
	fireContextChange: function(){
		if(arguments.length < 3) return;
		var usersEnabled = arguments[0];
		var crtUser = arguments[1];

        var crtIsRecycle = false;
        var crtInZip = false;
        var crtIsRoot = false;
        var crtAjxpMime = '';
        var crtIsReadOnly = false;

        var crtNode = arguments[2];
        if(crtNode){
            crtIsRecycle = (crtNode.getAjxpMime() == "ajxp_recycle");
            crtInZip = crtNode.hasAjxpMimeInBranch("ajxp_browsable_archive");
            crtIsRoot = crtNode.isRoot();
            crtAjxpMime = crtNode.getAjxpMime();
            crtIsReadOnly = crtNode.hasMetadataInBranch("ajxp_readonly", "true");
        }

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
		if(rightsContext.read && crtUser != null && !crtUser.canRead() ){
			return this.hideForContext();
		}
		if(rightsContext.write && crtUser != null && !crtUser.canWrite()){
			return this.hideForContext();
		}
        if(rightsContext.write && crtIsReadOnly){
            return this.hideForContext();
        }
		if(this.context.allowedMimes.length){
			if( !this.context.allowedMimes.include("*") && !this.context.allowedMimes.include(crtAjxpMime)){
				return this.hideForContext();
			}
            if( this.context.allowedMimes.include("^"+crtAjxpMime)){
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
		
	/**
	 * Upates the action status on selection change
	 */
	fireSelectionChange: function(){
		if(this.options.listeners["selectionChange"]){
			window.listenerContext = this;
			this.options.listeners["selectionChange"].evalScripts();			
		}
        if(this.options.activeCondition){
            if(this.options.activeCondition() === false) return this.disable();
            else if(this.options.activeCondition() === true) this.enable();
        }
		if(this.contextHidden
			|| !this.context.selection) {	
			return;
		}
		var userSelection = arguments[0];		
		var hasRoot = false;
		if(userSelection != null) 
		{			
			hasRoot = userSelection.selectionHasRootNode();
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
        if(selectionContext.evalMetadata && userSelection && userSelection.isUnique()){
            var metadata = userSelection.getUniqueNode().getMetadata();
            var result = eval(selectionContext.evalMetadata);
            if(!result){
                if(selectionContext.behaviour == 'hidden') this.hide();
             	else this.disable();
                return;
            }
        }
        if(!selectionContext.enableRoot && hasRoot){
            return this.disable();
        }
		if(selectionContext.unique && !bUnique){
			return this.disable();
		}
		if(selectionContext.multipleOnly && bUnique){
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
        if(this.rightsContext.write && userSelection.hasReadOnly()){
            return this.disable();
        }
		if(selectionContext.allowedMimes.size() && userSelection  && !selectionContext.allowedMimes.include('*')
            &&  !userSelection.hasMime(selectionContext.allowedMimes)){
			if(selectionContext.behaviour == 'hidden') return this.hide();
			else return this.disable();
		}
        if(selectionContext.allowedMimes.size() && userSelection && Object.toJSON(selectionContext.allowedMimes).indexOf("^") !== -1){
            var forbiddenValueFound = false;
            selectionContext.allowedMimes.each(function(m){
                if(m.indexOf("^") == -1) return;
                if(userSelection.hasMime([m.replace("^", "")])){
                    forbiddenValueFound = true;
                    throw $break;
                }
            });
            if(forbiddenValueFound){
                if(selectionContext.behaviour == 'hidden') return this.hide();
       			else return this.disable();
            }
        }
		this.show();
		this.enable();

	},
		
	/**
	 * Parses an XML fragment to configure this action
	 * @param xmlNode Node XML Fragment describing the action
	 */
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
            var j;
			if(node.nodeName == "processing"){
                var clientFormData = {};
				for(j=0; j<node.childNodes.length; j++){
					var processNode = node.childNodes[j];
					if(processNode.nodeName == "clientForm"){
                        if(!processNode.getAttribute("theme") || window.ajxpBootstrap.parameters.get('theme') == processNode.getAttribute("theme") ){
                            clientFormData.formId = processNode.getAttribute("id");
                            clientFormData.formCode = processNode.firstChild.nodeValue;
                        }
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
					}else if(processNode.nodeName == "activeCondition" && processNode.firstChild){
						this.options.activeCondition = new Function(processNode.firstChild.nodeValue.strip());
					}
				}
                if(clientFormData.formId){
                    this.options.formId = clientFormData.formId;
                    this.options.formCode = clientFormData.formCode;
                    this.insertForm();
                }
			}else if(node.nodeName == "gui"){
				this.options.text_id = node.getAttribute('text');
				this.options.title_id = node.getAttribute('title');
				this.options.text = MessageHash[node.getAttribute('text')] || 'not_found';
				this.options.title = MessageHash[node.getAttribute('title')] || 'not_found';
				this.options.src = node.getAttribute('src');								
				this.options.icon_class = node.getAttribute('iconClass');
				if(node.getAttribute('hasAccessKey') && node.getAttribute('hasAccessKey') == "true"){
					this.options.accessKey = node.getAttribute('accessKey');
					this.options.hasAccessKey = true;
				}
				if(node.getAttribute('specialAccessKey')){
					this.options.specialAccessKey = node.getAttribute('specialAccessKey');
				}
				for(j=0; j<node.childNodes.length;j++){
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
				for(j=0;j<node.childNodes.length;j++){
					if(node.childNodes[j].nodeName == "staticItems" || node.childNodes[j].nodeName == "dynamicItems"){
						this.subMenuItems[node.childNodes[j].nodeName] = [];
						for(var k=0;k<node.childNodes[j].childNodes.length;k++){
							if(node.childNodes[j].childNodes[k].nodeName.startsWith("item")){
								var item = {};
								for(var z=0;z<node.childNodes[j].childNodes[k].attributes.length;z++){
									var attribute = node.childNodes[j].childNodes[k].attributes[z];
									item[attribute.nodeName] = attribute.value;
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
	
	/**
	 * Creates the submenu items
	 */
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
                    icon_class:item.icon_class,
					isDefault:(item.isDefault?true:false),
					callback:function(){this.apply([item]);}.bind(this)
				});
			}, this);
		}
		this.subMenuItems.staticOptions = menuItems;
	},
	
	/**
	 * Caches some data for dynamically built menus
	 */
	prepareSubmenuDynamicBuilder : function(){		
		this.subMenuItems.dynamicBuilder = function(protoMenu){
            var menuItems;
            setTimeout(function(){
				if(this.subMenuItems.dynamicBuilderCode){
					window.builderContext = this;
                    window.builderProtoMenu = protoMenu;
					this.subMenuItems.dynamicBuilderCode.evalScripts();
					menuItems = this.builderMenuItems || [];
				}else{
			  		menuItems = [];
			  		this.subMenuItems.dynamicItems.each(function(item){
                        if(item.separator){
                            menuItems.push(item);
                            return;
                        }
			  			var action = this.manager.actions.get(item['actionId']);
			  			if(action.deny) return;
						var itemData = {
							name:action.getKeyedText(),
							alt:action.options.title,
                            icon_class:action.options.icon_class,
							image:resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16),						
							callback:function(){this.apply();}.bind(action)
						};
                        if(action.options.subMenu){
                            itemData.subMenu = [];
                            if(action.subMenuItems.staticOptions){
                                itemData.subMenu = action.subMenuItems.staticOptions;
                            }
                            if(action.subMenuItems.dynamicBuilder){
                                itemData.subMenuBeforeShow = action.subMenuItems.dynamicBuilder;
                            }
                        }
                        menuItems.push(itemData);
			  		}, this);
				}
			  	protoMenu.options.menuItems = menuItems;
			  	protoMenu.refreshList();
			}.bind(this),0);
		}.bind(this);		
	},
	
	/**
	 * Refresh icon image source
	 * @param newSrc String The image source. Can reference an image library
     * @param iconClass String Optional class to replace image
	 */
	setIconSrc : function(newSrc, iconClass){
		this.options.src = newSrc;
        var previousIconClass;
        if(iconClass){
            previousIconClass = this.options.icon_class;
            this.options.icon_class = iconClass;
            if(iconClass && $(this.options.name +'_button')&& $(this.options.name +'_button').down('span.ajxp_icon_span')){
                $(this.options.name +'_button').down('span.ajxp_icon_span').removeClassName(previousIconClass);
                $(this.options.name +'_button').down('span.ajxp_icon_span').addClassName(iconClass);
            }
        }
		if($(this.options.name +'_button_icon')){
			$(this.options.name +'_button_icon').src = resolveImageSource(this.options.src,this.__DEFAULT_ICON_PATH, 22);
		}
	},
	
	/**
	 * Refresh the action label
	 * @param newLabel String the new label
	 * @param newTitle String the new tooltip
	 */
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

    refreshInstances : function(){
        $$('#action_instance_'+this.options.name).each(function(instance){
            // Check img
            var img;
            if(instance.firstChild.nodeType == Node.ELEMENT_NODE && instance.firstChild.nodeName.toLowerCase()=="img"){
                img = instance.firstChild.cloneNode(true);
            }
            instance.update(this.getKeyedText());
            if(img){
                instance.insert({top:img});
            }
        }.bind(this));
    },
	
	/**
	 * Grab its label from the i18n MessageHash
	 */
	refreshFromI18NHash : function(){
		this.setLabel(this.options.text_id, this.options.title_id);
	},
	
	/**
	 * Return data necessary to build InfoPanel
	 * @returns Hash
	 */
	toInfoPanel:function(){
		return this.options;
	},
	
	/**
	 * Return necessary data to build contextual menu
	 * @returns Hash
	 */
	toContextMenu:function(){
		return this.options;
	},
	
	/**
	 * Changes show/hide state
	 */
	hideForContext: function(){
		this.hide();
		this.contextHidden = true;
	},
	
	/**
	 * Changes show/hide state
	 */
	showForContext: function(){
        this.contextHidden = false;
        this.show();
        if(this.selectionContext){
            this.fireSelectionChange();
        }
	},
	
	/**
	 * Changes show/hide state
	 * Notifies "hide" Event
	 */
	hide: function(){		
		this.deny = true;
		this.notify('hide');
	},
	
	/**
	 * Changes show/hide state
	 * Notifies "show" Event 
	 */
	show: function(){
		this.deny = false;
		this.notify('show');
	},
	
	/**
	 * Changes enable/disable state
	 * Notifies "disable" Event 
	 */
	disable: function(){
		this.deny = true;
		this.notify('disable');
	},
	
	/**
	 * Changes enable/disable state
	 * Notifies "enable" Event 
	 */
	enable: function(){
		this.deny = false;
		this.notify('enable');
	},
	
	/**
	 * To be called when removing
	 */
	remove: function(){
		// Remove all elements and forms from html
		this.elements.each(function(el){
			$(el).remove();
		}.bind(this));		
		this.elements = $A();
		if(this.options.formId && $('all_forms').select('[id="'+this.options.formId+'"]').length){
			$('all_forms').select('[id="'+this.options.formId+'"]')[0].remove();
		}
	},
	
	/**
	 * Create a text label with access-key underlined.
	 * @param displayString String the label
	 * @param hasAccessKey Boolean whether there is an accessKey or not
	 * @param accessKey String The key to underline
	 * @returns String
	 */
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
		var returnString = displayString.substring(0,displayString.indexOf(accessKey));
		returnString += '<u>'+accessKey+'</u>';
		returnString += displayString.substring(displayString.indexOf(accessKey)+1, displayString.length);
		return returnString;
	},
	
	/**
	 * Inserts Html FORM declared by manifest in the all_forms div.
	 */
	insertForm: function(){
		if(!this.options.formCode || !this.options.formId) return;
		if($('all_forms').select('[id="'+this.options.formId+'"]').length) return;
		$('all_forms').insert(this.options.formCode);
	},
	
	/**
	 * Utilitary function to transform XML Node attributes into Object mapping keys.
	 * @param object Object The target object
	 * @param node Node The source node
	 */
	attributesToObject: function(object, node){
		Object.keys(object).each(function(key){
			if(node.getAttribute(key)){
				var value = node.getAttribute(key);
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