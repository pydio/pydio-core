function cleanURL(url){
	split = url.split("?");
	url = split[0];
	if(url.charAt(url.length-1) == "/") {
		url = url.substring(0,url.length-1);
	}
	return url;
}

document.observe("ajaxplorer:gui_loaded", function(){
	document.addEventListener("touchmove", function(event){
		event.preventDefault();
	});
	var currentHref = document.location.href;
	
	$("ajxpserver-redir").href = cleanURL(currentHref).replace("http://", "ajxpserver://");
	$("skipios-redir").href = currentHref + (currentHref.indexOf("?")>-1?"&":"?") + "skipIOS=true";
});