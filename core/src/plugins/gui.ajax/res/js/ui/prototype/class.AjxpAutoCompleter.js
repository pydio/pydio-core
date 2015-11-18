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
 * Encapsulation of the Prototype Autocompleter for Pydio purposes.
 * Should be ported for local provides
 */
Class.create("AjxpAutocompleter", Autocompleter.Base, {
  /**
   * Constructor
   * @param element HTMLElement
   * @param update String
   * @param url String
   * @param options Object
   */
  initialize: function(element, update, url, options) {
  	if(Object.isString(update) && !$(update)){
  		document.getElementsByTagName('body')[0].appendChild(new Element('div', {
  			id:update,
  			className:"autocomplete",
  			style:"position:absolute; display:none;"
  		}));
  	}
    this.baseInitialize(element, update, options);
    this.options.asynchronous  = true;
    this.options.onComplete    = this.onComplete.bind(this);
    this.options.defaultParams = this.options.parameters || null;
    this.url                   = ajxpServerAccessPath+"&get_action=ls&options=dz";
    this.options.paramName	   = "dir";
    this.options.minChars	   = 1;
    //this.options.callback	   = this.parseValueBeforeSending.bind(this);
  },

  /**
   * Gets the choices
   */
  getUpdatedChoices: function() {
    this.startIndicator();
    var value = this.getToken();
    var entry = encodeURIComponent(this.options.paramName) + '=' + 
      encodeURIComponent(value.substring(0, value.lastIndexOf("/")+1));

    this.options.parameters = this.options.callback ?
      this.options.callback(this.element, entry) : entry;

    if(this.options.defaultParams) 
      this.options.parameters += '&' + this.options.defaultParams;
    
    new Ajax.Request(this.url, this.options);
  },

  /**
   * On AjaX request completion callback
   * @param request Ajax.Transport
   */
  onComplete: function(request) {
  	var oXmlDoc = request.responseXML;
  	var token = this.getToken();
  	var dirs = $A();
	if( oXmlDoc == null || oXmlDoc.documentElement == null) 
	{
		this.updateChoices('');
		return;
	}
	
	var root = oXmlDoc.documentElement;
	// loop through all tree children
	var cs = root.childNodes;
	var l = cs.length;
	for (var i = 0; i < l; i++) 
	{
		if (cs[i].tagName == "tree") 
		{
			var text = getBaseName(cs[i].getAttribute("filename"));
			
			var hasCharAfterSlash = (token.lastIndexOf("/")<token.length-1);
			if(!hasCharAfterSlash){
				dirs[dirs.length] = text;
			}else{
				var afterSlash = token.substring(token.lastIndexOf("/")+1, token.length);
				//console.log(text+'vs'+afterSlash);
				if(text.indexOf(afterSlash) ==0){
					dirs[dirs.length] = text;
				}
			}
		}
	}
  	if(!dirs.length)
  	{
  		 this.updateChoices('');
  		 return;
  	}
  	var responseText = '<ul>';
  	dirs.each(function(dir){
  		var value = token.substring(0, token.lastIndexOf("/")+1);
  		responseText += '<li>'+value+dir+'</li>';
  	});
  	responseText += '</ul>';
  	this.updateChoices(responseText);
  }
    
});