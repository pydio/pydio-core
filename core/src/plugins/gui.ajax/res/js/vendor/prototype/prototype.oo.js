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


$$OO_ObjectsRegistry = {classes: new Hash(), interfaces : new Hash()};

Class.create = function () {
	var parent = null, properties = $A(arguments), $$className=null, toImplement = [];
	for(var i=0;i<properties.length;i++){
		if(Object.isString(properties[i])){
			$$className = properties[i];
			properties = properties.without(properties[i]);
		}
		if(Object.isFunction(properties[i])){
			parent = properties[i];
			properties = properties.without(properties[i]);
		}
		if(typeof(properties[i]) == "object" && properties[i].__implements){
			if(Object.isString(properties[i].__implements)) {
				properties[i].__implements = [properties[i].__implements];
			}			
			toImplement = toImplement.concat(properties[i].__implements);
		}
	}

	if(parent && parent.__implements){
		toImplement = toImplement.concat(parent.__implements);
	}	
	
	function Klass() {
		this.__className = $$className;
		this.initialize.apply(this, arguments);
		if(toImplement.length){
			this.__implements = toImplement;
		}
	}

	Object.extend(Klass, Class.Methods);
	Object.Event.extend(Klass);
	Klass.superclass = parent;
	Klass.subclasses = [];

	if (parent) {
		var subclass = function() {};
		subclass.prototype = parent.prototype;
		Klass.prototype = new subclass;
		parent.subclasses.push(Klass);
	}
	for (i = 0; i < properties.length; i++)
	Klass.addMethods(properties[i]);

	if(toImplement.length){
		Klass.__implements = toImplement;
		for(var j=0;j<toImplement.length;j++){
			var interf = $$OO_ObjectsRegistry.interfaces.get(toImplement[j]);
            var message;
			if(!interf){
				message = "Reference to an unknown interface '" + toImplement[j] + "' in class '" + $$className + "'";
				alert(message);
				throw new Error(message);
			}
			for(var methodKey in interf.prototype){
				if(!Klass.prototype[methodKey]){
					message = "Warning, the class '"+$$className+"' must implement all method of interface '" + toImplement[j] + "'. The method '"+methodKey+"' is missing!";
					alert(message);
					throw new Error(message);
				}
			}
		}
	}

	if (!Klass.prototype.initialize)
	Klass.prototype.initialize = Prototype.emptyFunction;

	Klass.prototype.constructor = Klass;
	if($$className){
		$$OO_ObjectsRegistry.classes.set($$className, Klass);
		window[$$className] = Klass;
	}
	return Klass;
};


Class.getByName = function(className){
	return $$OO_ObjectsRegistry.classes.get(className);
};
Class.getByInterface = function(interfaceName){
	var found = [];
	var obj = $$OO_ObjectsRegistry.classes.toObject();
	for(var key in obj){
		if(obj.hasOwnProperty(key) && obj[key].__implements && $A(obj[key].__implements).find(function(value){return (value == interfaceName);})){
			found.push(key);
		}
	}	
	return found;
};
Class.objectImplements = function(objectOrClass, interfaceName){
	return objectOrClass.__implements && $A(objectOrClass.__implements).find(function (value) {
        return (value == interfaceName);
    });

};

var Interface = (function() {

	function create() {
		var parent = null, properties = $A(arguments), $$className=null;
		for(var i=0;i<properties.length;i++){
			if(Object.isString(properties[i])){
				$$className = properties[i];
				properties = properties.without(properties[i]);
			}else if(Object.isFunction(properties[i])){
				parent = properties[i];
				properties = properties.without(properties[i]);
			}
		}

		if(!$$className){
			throw new Error("You must set an interface name!");
		}
		
		function InterfaK() {
			throw new Error("Cannot instantiate an interface ("+$$className+")!");
		}

		Object.extend(InterfaK, Class.Methods);
		InterfaK.superclass = parent;
		InterfaK.subclasses = [];

		if (parent) {
			var subclass = function() {};
			subclass.prototype = parent.prototype;
			InterfaK.prototype = new subclass;
			parent.subclasses.push(InterfaK);
		}
		for (i = 0; i < properties.length; i++)
		InterfaK.addMethods(properties[i]);


		InterfaK.prototype.constructor = InterfaK;
		$$OO_ObjectsRegistry.interfaces.set($$className, InterfaK);
		window[$$className] = InterfaK;
		return InterfaK;
	}


	return {
		create: create,
		Methods: {
			addMethods: Class.Methods.addMethods
		}
	};
})();