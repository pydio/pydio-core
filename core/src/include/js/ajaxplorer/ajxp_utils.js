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