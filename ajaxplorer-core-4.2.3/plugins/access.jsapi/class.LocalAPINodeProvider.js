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
						LABEL : '<i>Extends <span class="jsapi_member">' + superclass + '</span></i>',
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
						LABEL : '<i>Implements <span class="jsapi_member">' + el + '</span></i>',
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
					var label = '<span class="jsapi_member">'+key+'</span>' + "(" + args.join(", ") + ")";
					var child = {
							PATH : key,
							LABEL:label, 
							ICON:'jsapi_images/method.png', 
							LEAF:true, 
							METADATA : {memberType:'method', argumentNames: args}							
					};
					if(this.knownMixinMethods[key]){
						child.LABEL = "<span class='jsapi_jdoc_param'>["+this.knownMixinMethods[key]+"]</span> "+child.LABEL;
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
							PATH : key,
							LABEL:'<span class="jsapi_member">'+key+'</span>', 
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
		if( node.getMetadata().get("API_CLASS") || node.getMetadata().get("API_INTERFACE") ){ 
			if(!node.getMetadata().get("API_SOURCE")){
				if(node.getMetadata().get("api_source_loading")) return;
				node.getMetadata().set("api_source_loading", true);
				var conn = new Connexion();
				conn.setParameters({
					get_action : 'get_js_source',
					object_type : (node.getMetadata().get("API_CLASS")?'class':'interface'),
					object_name : getBaseName(node.getPath())
				});
				conn.onComplete = function(transport){
					node.getMetadata().set("API_SOURCE", transport.responseText);
					node.getMetadata().set("API_JAVADOCS", this.parseJavadocs(transport.responseText));
					node.notify("api_source_loaded");
					this.enrichChildrenWithJavadocs(node);
					node.getMetadata().set("api_source_loading", false);
					node.setLoaded(true);
				}.bind(this);
				conn.onError = function(){
					node.getMetadata().set("api_source_loading", false);
					node.setLoaded(true);
				};
				conn.sendAsync();
			}else{
				this.enrichChildrenWithJavadocs(node);
				node.setLoaded(true);
			}
		}else{
			node.setLoaded(true);
		}
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
	} ,
	
	/**
	 * Find javadocs associated with the various members
	 * @param node AjxpNode
	 */
	enrichChildrenWithJavadocs: function(node){
		var docs = node.getMetadata().get("API_JAVADOCS");
		var children = node.getChildren();
		//console.log(docs);
		var changes = false;
		children.each(function(childNode){
			var memberKey = getBaseName(childNode.getPath());
			var vDesc = null;
			if(docs[memberKey]){
				changes = true;
				var meta = childNode.getMetadata();
				var crtLabel = childNode._label;
				if(docs[memberKey].main){
					crtLabel = crtLabel.replace("<span", '<span title="'+docs[memberKey].main.replace(/"/g, '\'')+'"');
				}
				if(docs[memberKey].keywords){
					if(docs[memberKey].keywords["returns"]){
						crtLabel = '<span class="jsapi_jdoc_return">'+docs[memberKey].keywords["returns"]+'</span> ' + crtLabel; 
					}else if(crtLabel.indexOf("(")>0){
						// Its a function, no return : add void
						crtLabel = '<span class="jsapi_jdoc_return">void</span> ' + crtLabel;
					}
					if(docs[memberKey].keywords["var"]){
						var vDoc = docs[memberKey].keywords["var"];
						vType = vDoc.split(" ")[0];
						vDesc = vDoc.substring(vType.length + 1);
						crtLabel = '<span class="jsapi_jdoc_var">'+vType+'</span> ' + crtLabel.replace("<span", '<span title="'+vDesc.replace(/"/g, '\'')+'"'); 
					}
					if(docs[memberKey].keywords["param"]){
						//console.log(docs[memberKey].keywords["param"]);
						var newArgs = $A();
						$A(meta.get("argumentNames")).each(function(arg){							
							if(docs[memberKey].keywords["param"][arg]){
								pValue = docs[memberKey].keywords["param"][arg];
								pType = (pValue.split(" ").length?pValue.split(" ")[0].strip():'');
								pDesc = pValue.substring(pType.length+1).replace(/"/g, '\'');
								arg = '<span class="jsapi_jdoc_param">'+pType+'</span> <span title="'+pDesc+'">'+arg+'</span>';
							}
							newArgs.push(arg);
						});
						crtLabel = crtLabel.replace('('+meta.get("argumentNames").join(', ')+')', '('+newArgs.join(', ')+')');
					}
				}
				if(docs[memberKey].main || vDesc){
					vDesc = vDesc?vDesc:docs[memberKey].main; 
					crtLabel = crtLabel + '<span class="jsapi_commentfull">'+vDesc+'</span>';
				}
				
				childNode._label = crtLabel;
				meta.set("text", crtLabel);
			}
		});
		if(docs["Class"] || docs["Interface"]){
			var type = (docs["Class"]?'Class':'Interface');
			var comm = docs[type].main;
			if(comm.length > 200){
				comm = comm.substring(0, 200)+'...';
			}
			var label = "<span class='jsapi_member'>"+type+" "+getBaseName(node.getPath())+"</span> - <span class='jsapi_maindoc'>"+comm+"</span>";
			var icon = "jsapi_images/"+type.toLowerCase()+".png";
			var child = new AjxpNode(
					node.getPath(), // PATH 
					true, 			// IS LEAF OR NOT
					label,		// LABEL			
					icon, 	// ICON
					this			// Keep the same provider!		
			);
			var metadata = $H();
			metadata.set("text", label);
			metadata.set("icon", icon);
			metadata.set("API_OBJECT_NODE", true);
			child.setMetadata(metadata);
			// ADD MANUALLY AT THE TOP
			child.setParent(node);
			node._children.unshift(child);
			node.notify("child_added", child.getPath());

			changes = true;
		}
		if(changes){
			var fList = $A(ajaxplorer.guiCompRegistry).detect(function(object){
				return (object.__className == "FilesList");
			});
			if(fList) fList.reload();			
		}
	},
	
	parseJavadocs : function(content){
		var reg = new RegExp(/\/\*\*(([^�*]|\*(?!\/))*)\*\/([\n\r\s\w]*|[\n\r\s]Class|[\n\r\s]Interface)/gi);
		var keywords = $A(["param", "returns", "var"]);
		var res = reg.exec(content);
		var docs = {};
		while(res != null){
			var comment = res[1];
			var key = res[3].strip();
			var parsedDoc = {main : '', keywords:{}};
			$A(comment.split("@")).each(function(el){
				el = el.replace(/\*/g, "");
				el = el.strip(el);
				var isKW = false;
				keywords.each(function(kw){
					if(el.indexOf(kw+" ") === 0){
						if(kw == "param"){
							if(!parsedDoc.keywords[kw]) parsedDoc.keywords[kw] = {};
							var kwCont = el.substring(kw.length+1);
							var paramName = kwCont.split(" ")[0];
							parsedDoc.keywords[kw][paramName] = kwCont.substring(paramName.length+1);
						}else if(kw == "returns"){
							parsedDoc.keywords[kw] = el.substring(kw.length+1);
						}else if(kw == "var"){
							parsedDoc.keywords[kw] = el.substring(kw.length+1);
						}
						isKW = true;
					}
				});
				if(!isKW){
					parsedDoc.main += el;
				}
			});
			docs[key] = parsedDoc;
			res = reg.exec(content);
		}
		return docs;
	}	
	
	
});