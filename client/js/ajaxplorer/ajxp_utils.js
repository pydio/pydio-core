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

function editWithCodePress(fileName)
{	
	if(Prototype.Browser.WebKit) return "";
	if(fileName.search('\.php$|\.php3$|\.php5$|\.phtml$') > -1) return "php";
	else if (fileName.search("\.js$") > -1) return "javascript";
	else if (fileName.search("\.java$") > -1) return "java";
	else if (fileName.search("\.pl$") > -1) return "perl";
	else if (fileName.search("\.sql$") > -1) return "sql";
	else if (fileName.search("\.htm$|\.html$") > -1) return "html";
	else if (fileName.search("\.css$") > -1) return "css";
	else return "";	
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
	if(typeof(parentElement) == "undefined" || parentElement == null){
		parentElement = Position.offsetParent($(element));
	}
	if(typeof(addMarginBottom) == "undefined" || addMarginBottom == null){
		addMarginBottom = 0;
	}
	if(!skipListener){
		jQuery(parentElement).bind("resize", function(){
			try{
			var $ms = jQuery(element);			
			var top = parseInt($ms.offset().top); // from dimensions.js			
			if(parentElement != window){				
				var parentTop = jQuery(parentElement).offset().top;
				top = top - parentTop;				
			}
			//if(jQuery(parentElement).height) return;
			var wTmp = jQuery(parentElement).height();			
			var wh = parseInt(jQuery(parentElement).height());
			// Account for margin or border on the splitter container
			var mrg = parseInt($ms.css("marginBottom")) || 0;
			var brd = parseInt($ms.css("borderBottomWidth")) || 0;
			$ms.css("height", (wh-top-mrg-brd-addMarginBottom)+"px");		
			//if ( !jQuery.browser.msie )
			$ms.trigger("resize");
			}catch(e){}
		}).trigger("resize");
	}
	else{
		jQuery(parentElement).trigger("resize");
	}
	jQuery(element).trigger("resize");
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