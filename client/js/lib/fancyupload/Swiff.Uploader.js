/*
Script: Swiff.Uploader.js
	Contains <Swiff.Uploader>

Author:
	Valerio Proietti, <http://mad4milk.net>,
	Harald Kirschner, <http://digitarald.de>

License:
	MIT-style license.
*/

/*
Class: Swiff.Uploader
	creates an uploader instance. Requires an existing Swiff.Uploader.swf instance.

Arguments:
	callBacks - an object, containing key/value pairs, representing the possible callbacks. See below.
	onLoaded - Callback when the swf is initialized
	options - types, multiple, queued, swf, url, container

callBacks:
	onOpen - a function to fire when the user opens a file.
	onProgress - a function to fire when the file is uploading. passes the name, the current uploaded size and the full size.
	onSelect - a function to fire when the user selects a file.
	onComplete - a function to fire when the file finishes uploading
	onError - a function to fire when there is an error.
	onCancel - a function to fire when the user cancels the file uploading.
*/

Swiff.Uploader = new Class({

	options: {
		types: false,
		multiple: true,
		queued: true,
		swf: null,
		url: null,
		container: null
	},

	callBacks: {
		onOpen: Class.empty,
		onProgress: Class.empty,
		onSelect: Class.empty,
		onComplete: Class.empty,
		onError: Class.empty,
		onCancel: Class.empty
	},

	initialize: function(callBacks, onLoaded, options){
		if (Swiff.getVersion() < 8) return false;
		this.setOptions(options);
		this.onLoaded = onLoaded;
		var calls = $extend($merge(this.callBacks), callBacks || {});
		for (p in calls) calls[p] = calls[p].bind(this);
		this.instance = Swiff.nextInstance();
		Swiff.callBacks[this.instance] = calls;
		this.object = Swiff.Uploader.register(this.loaded.bind(this), this.options.swf, this.options.container);
		return this;
	},

	loaded: function(){
		Swiff.remote(this.object, 'create', this.instance, this.options.types, this.options.multiple, this.options.queued, this.options.url);
		this.onLoaded.delay(10);
	},

	browse: function(){
		Swiff.remote(this.object, 'browse', this.instance);
	},

	send: function(url){
		Swiff.remote(this.object, 'upload', this.instance, url);
	},

	remove: function(name, size){
		Swiff.remote(this.object, 'remove', this.instance, name, size);
	},

	fileIndex: function(name, size){
		return Swiff.remote(this.object, 'fileIndex', this.instance, name, size);
	},

	fileList: function(){
		return Swiff.remote(this.object, 'filelist', this.instance);
	}

});

Swiff.Uploader.implement(new Options);

Swiff.Uploader.extend = $extend;

Swiff.Uploader.extend({

	swf: 'Swiff.Uploader.swf',

	callBacks: [],

	register: function(callBack, url, container){
		if (!Swiff.Uploader.object || !Swiff.Uploader.loaded) {
			Swiff.Uploader.callBacks.push(callBack);
			if (!Swiff.Uploader.object) {
				Swiff.Uploader.object = new Swiff(url || Swiff.Uploader.swf, {callBacks: {'onLoad': Swiff.Uploader.onLoad}});
				(container || document.body).appendChild(Swiff.Uploader.object);
			}
		}
		else callBack.delay(10);
		return Swiff.Uploader.object;
	},

	onLoad: function(){
		Swiff.Uploader.loaded = true;
		Swiff.Uploader.callBacks.each(function(fn){
			fn.delay(10);
		});
		Swiff.Uploader.callBacks.length = 0;
	}

});