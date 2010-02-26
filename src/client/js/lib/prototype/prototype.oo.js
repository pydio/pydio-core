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

	function klass() {
		this.initialize.apply(this, arguments);
	}

	Object.extend(klass, Class.Methods);
	klass.superclass = parent;
	klass.subclasses = [];

	if (parent) {
		var subclass = function() {};
		subclass.prototype = parent.prototype;
		klass.prototype = new subclass;
		parent.subclasses.push(klass);
	}
	for (var i = 0; i < properties.length; i++)
	klass.addMethods(properties[i]);

	if(toImplement.length){
		klass.__implements = toImplement;
		for(var j=0;j<toImplement.length;j++){
			var interf = $$OO_ObjectsRegistry.interfaces.get(toImplement[j]);
			if(!interf){
				var message = "Reference to an unknown interface '" + toImplement[j] + "' in class '" + $$className + "'";
				alert(message);
				throw new Error(message);
			}
			for(var methodKey in interf.prototype){
				if(!klass.prototype[methodKey]){
					var message = "Warning, the class '"+$$className+"' must implement all method of interface '" + toImplement[j] + "'. The method '"+methodKey+"' is missing!";
					alert(message);
					throw new Error(message);
				}
			}
		}
	}

	if (!klass.prototype.initialize)
	klass.prototype.initialize = Prototype.emptyFunction;

	klass.prototype.constructor = klass;
	if($$className){
		$$OO_ObjectsRegistry.classes.set($$className, klass);
		window[$$className] = klass;
	}
	return klass;
};


Class.getByName = function(className){
	return $$OO_ObjectsRegistry.classes.get(className);
};
Class.getByInterface = function(interfaceName){
	var found = [];
	var obj = $$OO_ObjectsRegistry.classes.toObject();
	for(var key in obj){
		if(obj[key].__implements && $A(obj[key].__implements).find(function(value){return (value == interfaceName);})){
			found.push(key);
		}
	}	
	return found;
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
		
		function interfaK() {
			throw new Error("Cannot instantiate an interface ("+$$className+")!");
		}

		Object.extend(interfaK, Class.Methods);
		interfaK.superclass = parent;
		interfaK.subclasses = [];

		if (parent) {
			var subclass = function() {};
			subclass.prototype = parent.prototype;
			interfaK.prototype = new subclass;
			parent.subclasses.push(interfaK);
		}
		for (var i = 0; i < properties.length; i++)
		interfaK.addMethods(properties[i]);


		interfaK.prototype.constructor = interfaK;
		$$OO_ObjectsRegistry.interfaces.set($$className, interfaK);
		window[$$className] = interfaK;
		return interfaK;
	}


	return {
		create: create,
		Methods: {
			addMethods: Class.Methods.addMethods
		}
	};
})();