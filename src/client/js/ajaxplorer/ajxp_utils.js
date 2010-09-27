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
 * Description : Various functions used statically very often.
 */
function getBaseName(fileName)
{
	if(fileName == null) return null;
	var separator = "/";
	if(fileName.indexOf("\\") != -1) separator = "\\";
	baseName = fileName.substr(fileName.lastIndexOf(separator)+1, fileName.length);	
	return baseName;
}

function getRepName(fileName)
{
	repName = fileName.substr(0, fileName.lastIndexOf("/"));
	return repName;	
}

function getAjxpMimeType(item){
	if(!item) return "";
	if(Object.isHash(item)){
		return (item.get('ajxp_mime') || getFileExtension(item.get('filename')));
	}else if(Object.isFunction(item.getMetadata)){
		return (item.getMetadata().get('ajxp_mime') || getFileExtension(item.getPath()));
	}else{
		return (item.getAttribute('ajxp_mime') || getFileExtension(item.getAttribute('filename')));
	}	
}

function getFileExtension(fileName)
{
	if(!fileName || fileName == "") return "";
	var split = getBaseName(fileName).split('.');
	if(split.length > 1) return split[split.length-1].toLowerCase();
	return '';
}

function addImageLibrary(aliasName, aliasPath){		
	if(!window.AjxpImageLibraries) window.AjxpImageLibraries = {};
	window.AjxpImageLibraries[aliasName] = aliasPath;
}

function resolveImageSource(src, defaultPath, size){
	if(!src) return "";
	if(!window.AjxpImageLibraries || src.indexOf("/")==-1){
		return ajxpResourcesFolder + (defaultPath?(size?defaultPath.replace("ICON_SIZE", size):defaultPath):'')+ '/' +  src;
	}
	var radic = src.substring(0,src.indexOf("/"));
	if(window.AjxpImageLibraries[radic]){
		var src = src.replace(radic, window.AjxpImageLibraries[radic]);
		return (size?src.replace("ICON_SIZE", size):src);
	}else{
		return ajxpResourcesFolder + (defaultPath?(size?defaultPath.replace("ICON_SIZE", size):defaultPath):'')+ '/' +  src;
	}
}

function simpleButton(id, cssClass, messageId, messageTitle, iconSrc, iconSize, hoverClass, callback, skipIconResolution){
	var button = new Element("div", {id:id, className:cssClass});
	var img = new Element("img", {
		src:(skipIconResolution?iconSrc:resolveImageSource(iconSrc, '/images/actions/ICON_SIZE', iconSize)), 
		width:iconSize,
		height:iconSize,
		title:MessageHash[messageTitle],
		ajxp_message_title:MessageHash[messageTitle]
	});
	button.update(img);
	if(hoverClass){
		button.observe("mouseover", function(){button.addClassName(hoverClass);});
		button.observe("mouseout", function(){button.removeClassName(hoverClass);});
	}
	if(callback){
		button.observe("click", callback);
	}
	button.setSrc = function(src){img.src=src;};
	return button;
}

function roundSize(filesize, size_unit){
	if (filesize >= 1073741824) {filesize = Math.round(filesize / 1073741824 * 100) / 100 + " G"+size_unit;}
	else if (filesize >= 1048576) {filesize = Math.round(filesize / 1048576 * 100) / 100 + " M"+size_unit;}
	else if (filesize >= 1024) {filesize = Math.round(filesize / 1024 * 100) / 100 + " K"+size_unit;}
	else {filesize = filesize + " "+size_unit;}
	return filesize;
}

function formatDate(dateObject, format){
	if(!format) format = MessageHash["date_format"];
	format = format.replace("d", (dateObject.getDate()<10?'0'+dateObject.getDate():dateObject.getDate()));
	format = format.replace("D", dateObject.getDay());
	format = format.replace("Y", dateObject.getFullYear());
	format = format.replace("y", dateObject.getYear());
	var month = dateObject.getMonth() + 1;
	format = format.replace("m", (month<10?'0'+month:month));
	format = format.replace("H", (dateObject.getHours()<10?'0':'')+dateObject.getHours());
	// Support 12 hour format compatibility
	format = format.replace("h", (dateObject.getHours() % 12 || 12));
	format = format.replace("p", (dateObject.getHours() < 12 ? "am" : "pm"));
	format = format.replace("P", (dateObject.getHours() < 12 ? "AM" : "PM"));	
	format = format.replace("i", (dateObject.getMinutes()<10?'0':'')+dateObject.getMinutes());
	format = format.replace("s", (dateObject.getSeconds()<10?'0':'')+dateObject.getSeconds());
	return format;
}

function parseUrl(data) {
	var matches = $A();
    //var e=/((http|ftp):\/)?\/?([^:\/\s]+)((\/\w+)*\/)([\w\-\.]+\.[^#?\s]+)(#[\w\-]+)?/;
    var detect=/(((ajxp\.)(\w+)):\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?/g;
    var results = data.match(detect);
    if(results && results.length){
    	var e=/^((ajxp\.(\w+)):\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?$/;
    	for(var i=0;i<results.length;i++){
    		if(results[i].match(e)){
		        matches.push({url: RegExp['$&'],
		                protocol: RegExp.$2,
		                host:RegExp.$4,
		                path:RegExp.$5,
		                file:RegExp.$7,
		                hash:RegExp.$8});		
    		}
    	}
    }
    return  matches;
}


function storeRememberData(user, pass){
	setAjxpCookie('remember', {user:user,pass:pass});
}

function retrieveRememberData(){
	return getAjxpCookie('remember');
}

function clearRememberData(){
	deleteAjxpCookie('remember');
}

function setAjxpCookie(name, value){
	var cookieJar = new CookieJar({
		expire: 3600*24*10, 
		path: '',
		secure: true
	});
	cookieJar.put('ajxp_'+name, value);	
}

function getAjxpCookie(name){
	var cookieJar = new CookieJar({});
	return cookieJar.get('ajxp_'+name);	
}

function deleteAjxpCookie(name){
	var cookieJar = new CookieJar({});
	cookieJar.remove('ajxp_'+name);	
}

function refreshPNGImages(element){
	if(element.getAttribute('is_image') && element.getAttribute('is_image')=='1'){
		return element;
	}
	var imgs = $(element).getElementsBySelector('img');
	if(imgs.length) imgs.each(function(img){
		if(img.original_src) img.src = img.original_src;
	});
	return element;
}

var messageDivOpen = false;
function closeMessageDiv()
{
	if(messageDivOpen)
	{
		new Effect.BlindUp('message_div');
		messageDivOpen = false;
	}
}

function tempoMessageDivClosing()
{
	messageDivOpen = true;
	setTimeout('closeMessageDiv()', 10000);
}

function disableTextSelection(target)
{
	if(!target) return;
	if (typeof target.onselectstart!="undefined")
	{ //IE route
		target.onselectstart=function(){return false;}
	}
	else if (target.style && typeof target.style.MozUserSelect!="undefined")
	{ //Firefox route
		var defaultValue = target.style.MozUserSelect;
		target.style.MozUserSelect="none";
		$(target).addClassName("no_select_bg");
	}
	$(target).addClassName("no_select_bg");
	if($(target).getElementsBySelector('input[type="text"]').length)
	{
		$(target).getElementsBySelector('input[type="text"]').each(function(element)
		{
			if (typeof element.onselectstart!="undefined")
			{ //IE route				
				element.onselectstart=function(){return true;}
			}
			else if (typeof element.style.MozUserSelect!="undefined")
			{ //Firefox route
				element.style.MozUserSelect=defaultValue;
			}
		});
	}
}

function testStringWidth(text){
	if(!$('string_tester')){
		$$('body')[0].insert(new Element('div',{id:'string_tester'}));
		$('string_tester').setStyle({fontFamily:'Trebuchet MS',fontSize:'11px',position:'absolute',visibility:'hidden',height:'auto',width:'auto',whiteSpace:'nowrap'});
	}
	$('string_tester').update(text);
	return $('string_tester').getWidth() + (Prototype.Browser.IE?20:0);
}

function fitRectangleToDimension(rectDim, targetDim){
	var defaultMargin = targetDim.margin || 0;	
	if(rectDim.width >= rectDim.height)
	{				
		tW = targetDim.width;
		tH = parseInt(rectDim.height / rectDim.width * tW);
		if(targetDim.maxHeight && tH > targetDim.maxHeight){
			tH = targetDim.maxHeight;
			tW = parseInt(rectDim.width / rectDim.height * tH);
			mT = mB = defaultMargin;
		}else{
			mT = parseInt((tW - tH)/2) + defaultMargin;
			mB = tW+(defaultMargin*2)-tH-mT-1;				
		}
	}
	else
	{
		tH = targetDim.height;
		if(targetDim.maxHeight) tH = Math.min(targetDim.maxHeight, tH);
		tW = parseInt(rectDim.width / rectDim.height * tH);
		mT = mB = defaultMargin;
	}
	return styleObj = {width:tW+'px', height:tH+'px', marginTop:mT+'px', marginBottom:mB+'px'};	
}

function fitHeightToBottom(element, parentElement, addMarginBottom, listen)
{	
	element = $(element);
	if(!element) return;
	if(typeof(parentElement) == "undefined" || parentElement == null){
		parentElement = Position.offsetParent($(element));
	}else{
		parentElement = $(parentElement);
	}
	if(typeof(addMarginBottom) == "undefined" || addMarginBottom == null){
		addMarginBottom = 0;
	}
		
	var observer = function(){	
		if(!element) return;	
		var top =0;
		if(parentElement == window){
			offset = element.cumulativeOffset();
			top = offset.top;
		}else{
			offset1 = parentElement.cumulativeOffset();
			offset2 = element.cumulativeOffset();
			top = offset2.top - offset1.top;
		}
		var wh;
		if(parentElement == window){
			wh = getViewPortHeight();
		}else{
			wh = parentElement.getHeight();
			if(Prototype.Browser.IE && parentElement.getStyle('height')){				
				wh = parseInt(parentElement.getStyle('height'));
			}
		}
		var mrg = parseInt(element.getStyle('marginBottom')) ||0;		
		var brd = parseInt(element.getStyle('borderWidth'))||0;
		var pad = (parseInt((parentElement!=window?parentElement.getStyle('paddingBottom'):0))||0);		
		var margin=0;
		if(parentElement!=window){
			margin = parseInt(parentElement.getStyle('borderBottomWidth')||0) + parseInt(parentElement.getStyle('borderTopWidth')||0);
		}
		if(!Prototype.Browser.IE){
			var childPadding = parseInt(element.getStyle('paddingBottom')||0) + parseInt(element.getStyle('paddingTop')||0);
			margin += childPadding;
		}
		margin = margin || 0; // avoid NaN in IE
		element.setStyle({height:(Math.max(0,wh-top-mrg-brd-pad-margin-addMarginBottom))+'px'});
		if(element.ajxpPaneObject && listen){
			element.ajxpPaneObject.resize();
		}
		element.fire("resize");
	};
	
	observer();
	if(listen){
		Event.observe(window, 'resize', observer);
	}
	return observer;
}

function getViewPortHeight(){
	var wh;
	if( typeof( window.innerHeight ) == 'number' ) {
		//Non-IE
		wh = window.innerHeight;
	} else if( document.documentElement && ( document.documentElement.clientWidth || document.documentElement.clientHeight ) ) {
		//IE 6+ in 'standards compliant mode'
		wh = document.documentElement.clientHeight;
	} else if( document.body && ( document.body.clientWidth || document.body.clientHeight ) ) {
		//IE 4 compatible
		wh = document.body.clientHeight;
	}
	return wh;
}

/**
 * Track event in Google Analytics
 */
function gaTrackEvent(eventCateg, eventName, eventData, eventValue){
	if(window._gaq && window._gaTrackEvents){
		_gaq.push(['_trackEvent', eventCateg, eventName, eventData, eventValue]);
	}
}

function loadXPathReplacer(){
	if(document.createExpression) return;
	var conn = new Connexion();
	conn._libUrl = false;
	if(ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')){
		conn._libUrl = ajxpBootstrap.parameters.get('SERVER_PREFIX_URI');
	}
	conn.loadLibrary('client/js/lib/xpath/javascript-xpath-cmp.js');	
}

/**
 * Selects the first XmlNode that matches the XPath expression.
 *
 * @param element {Element | Document} root element for the search
 * @param query {String} XPath query
 * @return {Element} first matching element
 * @signature function(element, query)
 */
function XPathSelectSingleNode(element, query){
	if(Prototype.Browser.IE){
		return element.selectSingleNode(query);
	}

	if(!window.__xpe) {
		try{
		  window.__xpe = new XPathEvaluator();
		}catch(e){}
	}
	
	if(!window.__xpe){
		if(!document.createExpression) loadXPathReplacer();
		query = document.createExpression(query, null);
		var result = query.evaluate(element, 7, null);
		return (result.snapshotLength?result.snapshotItem(0):null);
	}
	
	var xpe = window.__xpe;
	
	try {
	  	return xpe.evaluate(query, element, xpe.createNSResolver(element), XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
	} catch(err) {
	  	throw new Error("selectSingleNode: query: " + query + ", element: " + element + ", error: " + err);
	}
}


/**
 * Selects a list of nodes matching the XPath expression.
 *
 * @param element {Element | Document} root element for the search
 * @param query {String} XPath query
 * @return {Element[]} List of matching elements
 * @signature function(element, query)
 */
function XPathSelectNodes(element, query){
	if(Prototype.Browser.IE){
		return $A(element.selectNodes(query));
	}

    var xpe = window.__xpe;

    if(!xpe) {
    	try {
	      window.__xpe = xpe = new XPathEvaluator();
    	}catch(e){}
    }
    
	if(!window.__xpe){	
		if(!document.createExpression) loadXPathReplacer();	
		query = document.createExpression(query, null);
		var result = query.evaluate(element, 7, null);
	    var nodes = [];
	    for (var i=0; i<result.snapshotLength; i++) {
	      nodes[i] = Element.extend(result.snapshotItem(i));
	    }
	    return nodes;
	}

    try {
      var result = xpe.evaluate(query, element, xpe.createNSResolver(element), XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
    } catch(err) {
      throw new Error("selectNodes: query: " + query + ", element: " + element + ", error: " + err);
    }

    var nodes = [];
    for (var i=0; i<result.snapshotLength; i++) {
      nodes[i] = result.snapshotItem(i);
    }

    return $A(nodes);
}


/**
 * Selects the first XmlNode that matches the XPath expression and returns the text content of the element
 *
 * @param element {Element|Document} root element for the search
 * @param query {String}  XPath query
 * @return {String} the joined text content of the found element or null if not appropriate.
 * @signature function(element, query)
 */
function XPathGetSingleNodeText(element, query){
  var node = XPathSelectSingleNode(element, query);  
  return getDomNodeText(node);
}

function getDomNodeText(node){
	if(!node || !node.nodeType) {
		return null;
	}

	switch(node.nodeType)
	{
		case 1: // NODE_ELEMENT
		var i, a=[], nodes = node.childNodes, length = nodes.length;
		for (i=0; i<length; i++) {
			a[i] = getDomNodeText(nodes[i]);
		};

		return a.join("");

		case 2: // NODE_ATTRIBUTE
		return node.nodeValue;
		break;

		case 3: // NODE_TEXT
		return node.nodeValue;
		break;
	}

	return null;
}

function ajxpCorners(oElement, cornersString)
{
	var tr, tl, bl, br;
	if(cornersString == null)
	{
		tr = tl = bl = br;
	}
	else
	{
		tr = (cornersString=='top'||cornersString=='tr');
		tl = (cornersString=='top'||cornersString=='tl');
		bl = (cornersString=='bottom'||cornersString=='bl');
		br = (cornersString=='bottom'||cornersString=='br');
	}
	if(br || bl)
	{
		var botDiv = new Element('div');
		botDiv.setStyle({marginTop:'-5px', zoom:1, width:'100%'});
		botDiv.innerHTML = (bl?'<div style="overflow: hidden; width: 5px; background-color: rgb(255, 255, 255); height: 5px; float: left;background-image:url('+ajxpResourcesFolder+'/images/corners/5px_bl.gif);"></div>':'')+(br?'<div style="border-style: none; overflow: hidden; float: right; background-color: rgb(255, 255, 255); height: 5px; width: 5px;background-image:url('+ajxpResourcesFolder+'/images/corners/5px_br.gif);"></div>':'');
		oElement.appendChild(botDiv);
	}
	if(tr || tl)
	{
		var topDiv = new Element('div');
		topDiv.setStyle({marginBottom:'-5px', zoom:1, width:'100%'});
		topDiv.innerHTML = (tl?'<div style="overflow: hidden; width: 5px; background-color: rgb(255, 255, 255); height: 5px; float: left;background-image:url('+ajxpResourcesFolder+'/images/corners/5px_tl.gif);"></div>':'')+(tr?'<div style="border-style: none; overflow: hidden; float: right; background-color: rgb(255, 255, 255); height: 5px; width: 5px;background-image:url('+ajxpResourcesFolder+'/images/corners/5px_tr.gif);"></div>':'');
		if(oElement.firstChild)
		{
			oElement.insertBefore(topDiv, oElement.firstChild);
		}
		else
		{
			oElement.appendChild(topDiv);
		}
	}
}

function base64_encode( data ) {
    // http://kevin.vanzonneveld.net
    // +   original by: Tyler Akins (http://rumkin.com)
    // +   improved by: Bayron Guevara
    // +   improved by: Thunder.m
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Pellentesque Malesuada
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // -    depends on: utf8_encode
    // *     example 1: base64_encode('Kevin van Zonneveld');
    // *     returns 1: 'S2V2aW4gdmFuIFpvbm5ldmVsZA=='
 
    // mozilla has this native
    // - but breaks in 2.0.0.12!
    //if (typeof window['atob'] == 'function') {
    //    return atob(data);
    //}
        
    var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    var o1, o2, o3, h1, h2, h3, h4, bits, i = 0, ac = 0, enc="", tmp_arr = [];
 
    if (!data) {
        return data;
    }
 
    data = utf8_encode(data+'');
    
    do { // pack three octets into four hexets
        o1 = data.charCodeAt(i++);
        o2 = data.charCodeAt(i++);
        o3 = data.charCodeAt(i++);
 
        bits = o1<<16 | o2<<8 | o3;
 
        h1 = bits>>18 & 0x3f;
        h2 = bits>>12 & 0x3f;
        h3 = bits>>6 & 0x3f;
        h4 = bits & 0x3f;
 
        // use hexets to index into b64, and append result to encoded string
        tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
    } while (i < data.length);
    
    enc = tmp_arr.join('');
    
    switch( data.length % 3 ){
        case 1:
            enc = enc.slice(0, -2) + '==';
        break;
        case 2:
            enc = enc.slice(0, -1) + '=';
        break;
    }
 
    return enc;
}

function utf8_encode ( string ) {
    // http://kevin.vanzonneveld.net
    // +   original by: Webtoolkit.info (http://www.webtoolkit.info/)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: sowberry
    // +    tweaked by: Jack
    // +   bugfixed by: Onno Marsman
    // +   improved by: Yves Sucaet
    // +   bugfixed by: Onno Marsman
    // *     example 1: utf8_encode('Kevin van Zonneveld');
    // *     returns 1: 'Kevin van Zonneveld'
 
    string = (string+'').replace(/\r\n/g, "\n").replace(/\r/g, "\n");
 
    var utftext = "";
    var start, end;
    var stringl = 0;
 
    start = end = 0;
    stringl = string.length;
    for (var n = 0; n < stringl; n++) {
        var c1 = string.charCodeAt(n);
        var enc = null;
 
        if (c1 < 128) {
            end++;
        } else if((c1 > 127) && (c1 < 2048)) {
            enc = String.fromCharCode((c1 >> 6) | 192) + String.fromCharCode((c1 & 63) | 128);
        } else {
            enc = String.fromCharCode((c1 >> 12) | 224) + String.fromCharCode(((c1 >> 6) & 63) | 128) + String.fromCharCode((c1 & 63) | 128);
        }
        if (enc != null) {
            if (end > start) {
                utftext += string.substring(start, end);
            }
            utftext += enc;
            start = end = n+1;
        }
    }
 
    if (end > start) {
        utftext += string.substring(start, string.length);
    }
 
    return utftext;
}