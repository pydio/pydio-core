Action = Class.create({

	initialize:function(){
		this.options = Object.extend({
			name:'',
			src:'',
			text:'',
			title:'',
			hasAccessKey:true,
			accessKey:'',
			callback:Prototype.emptyFunction
			}, arguments[0] || { });
		this.context = Object.extend({
			generic:true,
			dir:false,
			recycle:false,			
			actionBar:true,
			contextMenu:true,
			infoPanel:false			
			}, arguments[1] || { });
		this.selectionContext = Object.extend({			
			dir:false,
			file:true,
			recyle:false,
			unique:true,
			image:true,
			mp3:true,
			editable:true,			
			}, arguments[2] || { });
		this.rightsContext = Object.extend({			
			noUser:true,
			userLogged:true,			
			read:true,
			write:false,
			adminOnly:false
			}, arguments[3] || { });
	}, 
	
	createFromXML:function(transport){
		
	
	}, 
	
	toActionBar:function(){
	
	},
	
	toInfoPanel:function(){
	
	},
	
	toContextMenu:function(){
		return this.options;
	}

});