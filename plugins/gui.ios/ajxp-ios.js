document.observe("ajaxplorer:gui_loaded", function(){
	document.addEventListener("touchmove", function(event){
		event.preventDefault();
	});
	var currentHref = document.location.href;
	$("ajxpserver-redir").href = currentHref.replace("http://", "ajxpserver://");
	$("skipios-redir").href = currentHref + (currentHref.indexOf("?")>-1?"&":"?") + "skipIOS=true";
});