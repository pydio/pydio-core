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

function getFileExtension(fileName)
{
	if(!fileName || fileName == "") return "";
	var split = getBaseName(fileName).split('.');
	if(split.length > 1) return split[split.length-1].toLowerCase();
	return '';
}

function editWithCodePress(fileName)
{	
	if(Prototype.Browser.WebKit) return "";
	if(fileName.search('\.php$|\.php3$|\.php5$|\.phtml$') > -1) return "php";
	else if (fileName.search("\.js$") > -1) return "javascript";
	else if (fileName.search("\.java$") > -1) return "java";
	else if (fileName.search("\.pl$") > -1) return "perl";
	else if (fileName.search("\.sql$") > -1) return "sql";
	else if (fileName.search("\.htm$|\.html$|\.xml$") > -1) return "html";
	else if (fileName.search("\.css$") > -1) return "css";
	else return "";	
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
	format = format.replace("i", (dateObject.getMinutes()<10?'0':'')+dateObject.getMinutes());
	format = format.replace("s", (dateObject.getSeconds()<10?'0':'')+dateObject.getSeconds());
	return format;
}

function storeRememberData(user, pass){
	var cookieJar = new CookieJar({
		expire: 3600*24*10, 
		path: '',
		secure: true
	});
	cookieJar.put('ajxp_remember', {user:user, pass:pass});
}

function retrieveRememberData(){
	var cookieJar = new CookieJar({});
	return cookieJar.get('ajxp_remember');
}

function clearRememberData(){
	var cookieJar = new CookieJar({});
	cookieJar.remove('ajxp_remember');
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
	if (typeof target.onselectstart!="undefined")
	{ //IE route
		target.onselectstart=function(){return false;}
	}
	else if (typeof target.style.MozUserSelect!="undefined")
	{ //Firefox route
		var defaultValue = target.style.MozUserSelect;
		target.style.MozUserSelect="none";
	}
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

function fitHeightToBottom(element, parentElement, addMarginBottom, skipListener)
{
	element = $(element);
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
			wh = document.viewport.getHeight();
		}else{
			wh = parentElement.getHeight();
			if(Prototype.Browser.IE && parentElement.getStyle('height')){				
				wh = parseInt(parentElement.getStyle('height'));
			}
		}
		var mrg = parseInt(element.getStyle('marginBottom')) ||0;		
		var brd = parseInt(element.getStyle('borderWidth'))||0;
		var pad = parseInt((parentElement!=window?parentElement.getStyle('paddingBottom'):0))||0;			
		element.setStyle({height:(Math.max(0,wh-top-mrg-brd-addMarginBottom))+'px'});
		element.fire("resize");
	};
	
	observer();
	if(!skipListener){
		Event.observe(window, 'resize', observer);
	}
	return observer;
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