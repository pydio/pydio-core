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
 * Description : Encapsulation of the Autocompleter for AjaXplorer purposes.
 */
AjxpAutocompleter = Class.create(Autocompleter.Base, {
  initialize: function(element, update, url, options) {
    this.baseInitialize(element, update, options);
    this.options.asynchronous  = true;
    this.options.onComplete    = this.onComplete.bind(this);
    this.options.defaultParams = this.options.parameters || null;
    this.url                   = ajxpServerAccessPath+"?get_action=ls&mode=complete";
    this.options.paramName	   = "dir";
    this.options.minChars	   = 1;
    //this.options.callback	   = this.parseValueBeforeSending.bind(this);
  },

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

  onComplete: function(request) {
  	var oXmlDoc = request.responseXML;
  	var token = this.getToken();
  	var dirs = new Array();
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
			var text = cs[i].getAttribute("text");
			
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
  		value = token.substring(0, token.lastIndexOf("/")+1);
  		responseText += '<li>'+value+dir+'</li>';
  	});
  	responseText += '</ul>';
  	this.updateChoices(responseText);
  }
    
});