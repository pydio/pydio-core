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
 * Description : Various functions used statically very often.
 */
function getBaseName(fileName){
    return PathUtils.getBasename(fileName);
}

function getRepName(fileName){
    return PathUtils.getDirname(fileName);
}

function getAjxpMimeType(item){
    return PathUtils.getAjxpMimeType(item);
}

function getFileExtension(fileName){
    return PathUtils.getFileExtension(fileName);
}

function roundSize(filesize, size_unit){
    return PathUtils.roundFileSize(filesize, size_unit);
}

function formatDate(dateObject, format){
    return PathUtils.formatModifDate(dateObject, format);
}

function parseUrl(data) {
    return LangUtils.parseUrl(data);
}

function XPathSelectSingleNode(element, query){
    return XMLUtils.XPathSelectSingleNode(element, query);
}

function XPathSelectNodes(element, query){
    return XMLUtils.XPathSelectNodes(element, query);
}

function XPathGetSingleNodeText(element, query){
    return XMLUtils.XPathGetSingleNodeText(element, query);
}

function getDomNodeText(node){
    return XMLUtils.getDomNodeText(node);
}

function parseXml(xmlStr){
    return XMLUtils.parseXml(xmlStr);
}

function base64_encode( data ) {
    return HasherUtils.base64_encode(data);
}

function slugString(value){
    return LangUtils.computeStringSlug(value);
}

function bufferCallback(name, time, callback){
    if(window[name]){
        window.clearTimeout(window[name]);
    }
    window[name] = window.setTimeout(callback, time);
}

function getUrlFromBase(){
    return $$('base')[0].href;
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
		src = src.replace(radic, window.AjxpImageLibraries[radic]);
        if(ajxpBootstrap.parameters.get("SERVER_PREFIX_URI")){
            src = ajxpBootstrap.parameters.get("SERVER_PREFIX_URI") + src;
        }
		return (size?src.replace("ICON_SIZE", size):src);
	}else{
		return ajxpResourcesFolder + (defaultPath?(size?defaultPath.replace("ICON_SIZE", size):defaultPath):'')+ '/' +  src;
	}
}

function simpleButton(id, cssClass, messageId, messageTitle, iconSrc, iconSize, hoverClass, callback, skipIconResolution, addArrow){
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
    if(addArrow){
        button.setStyle({position:'relative'});
        var arrowImg = new Element('img', {
            src: resolveImageSource('arrow_down.png', '/images'),
            width:10,
            height:6,
            className:'simple_button_arrow'
        });
        button.insert(arrowImg);
    }
	return button;
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
		expires: 3600*24*10,
		path: '/',
		secure: true
	});
	cookieJar.put('ajxp_'+name, value);	
}

function getAjxpCookie(name){
	var cookieJar = new CookieJar({path: '/',secure:true});
	return cookieJar.get('ajxp_'+name);	
}

function deleteAjxpCookie(name){
	var cookieJar = new CookieJar({path: '/',secure:true});
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
		target.onselectstart=function(){return false;};
	}
	else if (target.style && typeof target.style.MozUserSelect!="undefined")
	{ //Firefox route
		var defaultValue = target.style.MozUserSelect;
		target.style.MozUserSelect="none";
		$(target).addClassName("no_select_bg");
	}
	$(target).addClassName("no_select_bg");
    $(target).select('input[type="text"]').each(function(element)
    {
        if (typeof element.onselectstart!="undefined")
        { //IE route
            element.onselectstart=function(){return true;};
        }
        else if (typeof element.style.MozUserSelect!="undefined")
        { //Firefox route
            element.style.MozUserSelect=defaultValue;
        }
    });
    $(target).select(">div").each(function(d){disableTextSelection(d)});
}

function enableTextSelection(element){
    if (typeof element.onselectstart!="undefined")
    { //IE route
        element.onselectstart=function(){return true;};
    }
    else if (typeof element.style.MozUserSelect!="undefined")
    { //Firefox route
        element.style.MozUserSelect="text";
    }
}

function moveCaretToEnd(el) {
    if (typeof el.selectionStart == "number") {
        el.selectionStart = el.selectionEnd = el.value.length;
    } else if (typeof el.createTextRange != "undefined") {
        el.focus();
        var range = el.createTextRange();
        range.collapse(false);
        range.select();
    }
}

function testStringWidth(text){
    var e = new Element('div',{id:'string_tester'});
    $$('body')[0].insert(e);
    e.setStyle({fontSize:'11px',position:'absolute',visibility:'hidden',height:'auto',width:'auto',whiteSpace:'nowrap'});
	e.update(text);
    var result = parseInt(e.getWidth()) + (Prototype.Browser.IE?20:0);
    e.remove();
	return result;
}

function fitRectangleToDimension(rectDim, targetDim){
    var defaultMarginTop = (targetDim.marginTop?targetDim.marginTop:(targetDim.margin?targetDim.margin:0));
    var defaultMarginBottom = (targetDim.marginBottom?targetDim.marginBottom:(targetDim.margin?targetDim.margin:0));
	//var defaultMargin = targetDim.margin || 0;
    var tW, tH, mT, mB;
	if(rectDim.width >= rectDim.height)
	{				
		tW = targetDim.width;
		tH = parseInt(rectDim.height / rectDim.width * tW);
		if(targetDim.maxHeight && tH > targetDim.maxHeight){
			tH = targetDim.maxHeight;
			tW = parseInt(rectDim.width / rectDim.height * tH);
			mT = defaultMarginTop;
            mB = defaultMarginBottom;
		}else{
			mT = parseInt((tW - tH)/2) + defaultMarginTop;
			mB = tW+(defaultMarginTop + defaultMarginBottom)-tH-mT-1;
		}
	}
	else
	{
		tH = targetDim.height;
		if(targetDim.maxHeight) tH = Math.min(targetDim.maxHeight, tH);
		tW = parseInt(rectDim.width / rectDim.height * tH);
        mT = defaultMarginTop;
        mB = defaultMarginBottom;
	}
	return {width:tW+'px', height:tH+'px', marginTop:mT+'px', marginBottom:mB+'px'};
}

/**
 *
 * @param element
 * @param parentElement
 * @param addMarginBottom
 * @param listen
 * @param minOffsetTop
 * @returns Object|null
 */
function fitHeightToBottom(element, parentElement, addMarginBottom, listen, minOffsetTop)
{
	element = $(element);
	if(!element) return;
    if(Modernizr.flexbox && element.parentNode && !element.hasClassName('forceComputeFit')
        && !element.parentNode.hasClassName('horizontal_layout')
        && !element.hasClassName('dialogContent') && !element.up(".dialogContent")
){
        if(!element.hasClassName('vertical_fit')){
            element.parentNode.addClassName('vertical_layout');
            element.addClassName('vertical_fit');
        }
        if(listen){
            Event.observe(window, 'resize', function(){
                if(element.ajxpPaneObject){
                    element.ajxpPaneObject.resize();
                }
                element.fire('resize', null, null, false);
            });
        }
        return;
    }
	if(typeof(parentElement) == "undefined" || parentElement == null){
		parentElement = Position.offsetParent($(element));
	}else if(parentElement == "window") {
        parentElement = window;
    }else if(typeof parentElement == "string"){
        parentElement = element.up('#' + parentElement);
    }else{
		parentElement = $(parentElement);
	}
    if(!parentElement){
        if(console) console.log('Warning, trying to fitHeightToBottom on null parent!', element.id);
        return null;
    }
	if(typeof(addMarginBottom) == "undefined" || addMarginBottom == null){
		addMarginBottom = 0;
	}

	var observer = function(){	
		if(!element) return;	
		var top = 0;
		if(parentElement == window){
			var offset = element.cumulativeOffset();
			top = offset.top;
		}else{
			var offset1 = parentElement.cumulativeOffset();
			var offset2 = element.cumulativeOffset();
			top = offset2.top - offset1.top;
		}
        if(minOffsetTop) {
            top = Math.max(top, minOffsetTop);
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
            margin += parseInt(element.getStyle('paddingBottom') || 0) + parseInt(element.getStyle('paddingTop') || 0);
		}
		if(!margin) margin = 0;
		element.setStyle({height:(Math.max(0,wh-top-mrg-brd-pad-margin-addMarginBottom))+'px'});
		if(element.ajxpPaneObject && listen){
			element.ajxpPaneObject.resize();
		}
		element.fire("resize", null, null, false);
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


function scrollByTouch(event, direction, targetId){
	var touchData = event.changedTouches[0];
	var type = event.type;
	if(!$(touchData.target) || ! $(touchData.target).up ) return;
	var target = $(touchData.target).up('#'+targetId);
	if(!target) return;
    var eventPropName, targetPropName, delta;
	if(direction != "both"){
		if(direction == "vertical"){
			eventPropName = "clientY";
			targetPropName = "scrollTop";
		}else{
			eventPropName = "clientX";
			targetPropName = "scrollLeft";
		}
		
		if(type == "touchstart"){
			target.originalTouchPos = touchData[eventPropName];
			target.originalScroll = target[targetPropName];
		}else if(type == "touchend"){
			if(target.originalTouchPos){
				event.preventDefault();
			}
			target.originalTouchPos = null;
			target.originalScroll = null;
		}else if(type == "touchmove"){
			event.preventDefault();
			if(!target.originalTouchPos) return;
			delta = touchData[eventPropName] - target.originalTouchPos;
			target[targetPropName] = target.originalScroll - delta;
		}
	}else{
		if(type == "touchstart"){
			target.originalTouchPosY = touchData["clientY"];
			target.originalScrollTop = target["scrollTop"];
			target.originalTouchPosX = touchData["clientX"];
			target.originalScrollLeft = target["scrollLeft"];
		}else if(type == "touchend"){
			if(target.originalTouchPosY){
				event.preventDefault();
			}
			target.originalTouchPosY = null;
			target.originalScrollTop = null;
			target.originalTouchPosX = null;
			target.originalScrollLeft = null;
		}else if(type == "touchmove"){
			event.preventDefault();
			if(!target.originalTouchPosY) return;
			delta = touchData["clientY"] - target.originalTouchPosY;
			target["scrollTop"] = target.originalScrollTop - delta;
			delta = touchData["clientX"] - target.originalTouchPosX;
			target["scrollLeft"] = target.originalScrollLeft - delta;
		}
	}
}

function attachMobileScroll(targetId, direction){
	if(!window.ajxpMobile || !$(targetId)) return;
    var overflow = {};
    if(direction == 'vertical' || direction == 'both') overflow['overflowY'] = 'auto';
    if(direction == 'horizontal' || direction == 'both') overflow['overflowX'] = 'auto';
    $(targetId).setStyle(overflow);
}

/**
 * Utilitary to get FlashVersion
 * @returns String
 */
function getFlashVersion(){
    if (!window.PYDIO_DetectedFlashVersion) {
        var x;
        if(navigator.plugins && navigator.mimeTypes.length){
            x = navigator.plugins["Shockwave Flash"];
            if(x && x.description) x = x.description;
        } else if (Prototype.Browser.IE){
            try {
                x = new ActiveXObject("ShockwaveFlash.ShockwaveFlash");
                x = x.GetVariable("$version");
            } catch(e){}
        }
        window.PYDIO_DetectedFlashVersion = (typeof(x) == 'string') ? parseInt(x.match(/\d+/)[0]) : 0;
    }
    return window.PYDIO_DetectedFlashVersion;
}