/**
 * A local implementation that explore currently defined
 * classes
 */
Class.create("LocalAPINodeProvider", {
	__implements : "IAjxpNodeProvider",
	knownMixinMethods : {observe:'livepipe', stopObserving:'livepipe', observeOnce:'livepipe', notify:'livepipe'},
	initialize : function(){
		
	},
	
	initProvider : function(properties){
		this.properties = properties;
	},
	
	/**
	 * 
	 * @param node AjxpNode
	 * @param nodeCallback Function
	 * @param childCallback Function
	 */
	loadNode : function(node, nodeCallback, childCallback){
		var path = node.getPath();
		var children = [];
		var levelIcon = "folder.png"; 
		if(path == "/"){
			var levelIcon = "jsapi_images/package.png";
			children = ["Classes", "Interfaces"];			
		}else if(path == "/Classes" || path == "/Interfaces"){
			var levelIcon = (path=="/Classes"?"jsapi_images/class.png":"jsapi_images/interface.png");
			$$OO_ObjectsRegistry[(path=="/Classes"?'classes':'interfaces')].each(function(pair){
				children.push(pair.key);
			});
			children.sort();
		}else if(node.getMetadata().get("API_CLASS") || node.getMetadata().get("API_INTERFACE")){
			var api_class = node.getMetadata().get("API_CLASS");
			var api_interface = node.getMetadata().get("API_INTERFACE");
			var levelIcon = "jsapi_images/method.png";
			var ooObject = $$OO_ObjectsRegistry[(api_class?'classes':'interfaces')].get((api_class?api_class:api_interface));
			var proto = ooObject.prototype;
			var properties = $A();
			var methods = $A();
			var mixedMethods = $A();
			var inheritedMethods = {};
			var interfacesMethods = {};
			var interfacesChildren = {};
			var parentClasses = {};
			var parentMethods = {};
			var parentChildren = $A();
			var superclass = ooObject.superclass;
			if(superclass){
				$$OO_ObjectsRegistry.classes.each(function(pair){				
					if(pair.value.subclasses.length && pair.value == superclass){
						superclass = pair.key;
						for(var key in pair.value.prototype){
							if(this.knownMixinMethods[key]) continue;
							if(key.indexOf("_") == 0) continue;
							parentMethods[key] = superclass;
						} 
					}
				}.bind(this));
				if(typeof(superclass) == "string"){
					parentChildren.push({
						PATH : superclass,
						LABEL : 'Extends ' + superclass,
						ICON : 'jsapi_images/class.png',
						LEAF : true,
						METADATA : {memberType:'parent'}					
					});
				}else{
					superclass = null;
					parentMethods = {};
				}
			}
			if(ooObject.__implements){
				$A(ooObject.__implements).each(function(el){
					var child = {
						PATH : el,
						LABEL : 'Implements ' + el,
						ICON : 'jsapi_images/interface.png',
						LEAF : true,
						METADATA : {memberType:'interface'}
					};
					var iProto = $$OO_ObjectsRegistry.interfaces.get(el).prototype;
					for(var key in iProto){
						interfacesMethods[key] = el;
					}
					interfacesChildren[el] = $A([child]);
					//children.push(child);
				});
			}
			
			for(var key in proto){
				if(key.indexOf("_") === 0) continue;
				if(key == "constructor") continue;
				if(typeof proto[key] == 'function'){
					var args = proto[key].argumentNames();					
					var label = key + "(" + args.join(", ") + ")";
					var child = {
							PATH : key,
							LABEL:label, 
							ICON:'jsapi_images/method.png', 
							LEAF:true, 
							METADATA : {memberType:'method'}
					};
					if(this.knownMixinMethods[key]){
						child.LABEL = "[Mixin "+this.knownMixinMethods[key]+"] "+child.LABEL;
						child.ICON = 'jsapi_images/mixedin_method.png';
						child.METADATA.memberType = 'mixedin_method';
						mixedMethods.push(child);
					}else if(interfacesMethods[key]){
						var ifName = interfacesMethods[key];
						child.ICON = 'jsapi_images/inherited_method.png';
						interfacesChildren[ifName].push(child);
					}else if(parentMethods[key] && (!args.length || args[0] != '$super')){
						child.ICON = 'jsapi_images/inherited_method.png';
						parentChildren.push(child);
						child.METADATA.memberType = 'parent_method';
						child.METADATA.parentClass = parentMethods[key];
					}else{
						methods.push(child);
					}
				}else{
					var child = {
							LABEL:key, 
							ICON:'jsapi_images/property.png', 
							LEAF:true, 
							METADATA:{memberType:'property'}
					};
					properties.push(child);
				}
				
			}
			if(parentChildren.length){				
				parentChildren.each(function(el){children.push(el);});
			}
			for(key in interfacesChildren){
				interfacesChildren[key].each(function(el){children.push(el);});
			}				
			if(properties.length){
				properties.sortBy(function(el){return el.LABEL;});
				properties.each(function(el){children.push(el);});
			}
			if(methods.length){
				methods.sort();
				methods.each(function(el){children.push(el);});
			}
			if(mixedMethods.length){
				mixedMethods.sort();
				mixedMethods.each(function(el){children.push(el);});
			}
		}
		this.createAjxpNodes(node, $A(children), nodeCallback, childCallback, levelIcon);
	},
	
	createAjxpNodes : function(node, children, nodeCallback, childCallback, levelIcon){
		var path = node.getPath();
		if(path == "/") path = "";
		children.each(function(childNode){
			var label, icon, isFile, childPath;
			if(typeof(childNode) == "string"){
				label = childNode;
				icon = levelIcon;
				isFile = false;
				childPath = label;
			}else if(typeof(childNode) == "object"){
				label = childNode.LABEL;
				icon = (childNode.ICON?childNode.ICON:levelIcon);
				childPath = (childNode.PATH?childNode.PATH:label);
				isFile = childNode.LEAF;
				if(childNode.METADATA){
					var addMeta = childNode.METADATA; 
				}
			}
			var child = new AjxpNode(
					path+"/"+childPath, // PATH 
					isFile, 		// IS LEAF OR NOT
					label,		// LABEL			
					icon, 		// ICON
					this			// Keep the same provider!		
					);		
			node.addChild(child);
			var metadata = $H();
			metadata.set("text", label);
			metadata.set("icon", icon);
			if(path == "/Classes"){
				metadata.set("API_CLASS", label);
			}else if(path == "/Interfaces"){
				metadata.set("API_INTERFACE", label);
			}
			if(addMeta){
				metadata = metadata.merge(addMeta);
			}
			child.setMetadata(metadata);
			if(childCallback){
				childCallback(child);
			}
		}.bind(this) );

		if(nodeCallback){
			nodeCallback(node);
		}
	} 
	
	
});