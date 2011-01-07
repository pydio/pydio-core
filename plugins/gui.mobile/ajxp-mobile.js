function scrollByTouch(event, direction, targetId){
	var touchData = event.changedTouches[0];
	var type = event.type;
	if(!$(touchData.target) || ! $(touchData.target).up ) return;
	var target = $(touchData.target).up('#'+targetId);
	if(!target) return;
	if(direction == "vertical"){
		var eventPropName = "clientY";
		var targetPropName = "scrollTop";
	}else{
		var eventPropName = "clientX";
		var targetPropName = "scrollLeft";					
	}
	if(type == "touchstart"){
		target.originalTouchPos = touchData[eventPropName];
		target.originalScroll = target[targetPropName];
		if(target.notify){
			target.notify('ajaxplorer:touch_start');
		}
	}else if(type == "touchend"){
		if(target.originalTouchPos){
			event.preventDefault();
		}
		if(target.notify){
			target.notify('ajaxplorer:touch_end');
		}
		target.originalTouchPos = null;
		target.originalScroll = null;
	}else if(type == "touchmove"){
		event.preventDefault();
		if(!target.originalTouchPos == null) return;
		var delta = touchData[eventPropName] - target.originalTouchPos;
		target[targetPropName] = target.originalScroll - delta;
	}
}

function attachSimpleScroll(targetId, direction){
	var target = $(targetId);
	if(!target) return;
	target.addEventListener("touchmove", function(event){ scrollByTouch(event, direction, targetId); });
	target.addEventListener("touchstart", function(event){ scrollByTouch(event, direction, targetId); });
	target.addEventListener("touchend", function(event){ scrollByTouch(event, direction, targetId); });
}

function initMobileActions(){
	var mobileActions = $('mobile_actions');
	var act = mobileActions.select('a');
	act[0].observe('click', function(e){
		Event.stop(e);
		$('info_container').down('.info_panel_title_span').update(ajaxplorer.getContextHolder().getUniqueNode().getLabel());
		$('info_container').show();
		$('info_container').ajxpPaneObject.resize();
		$('info_panel').select('.infoPanelActions a').each(function(action){
			action.observe("click", function(){$('info_container').hide();});
		});
	});
	act[1].observe('click', function(e){
		ajaxplorer.actionBar.fireAction('ls');
		Event.stop(e);
	});
	document.observe("ajaxplorer:selection_changed", function(){
		var mobileActions = $('mobile_actions');
		mobileActions.hide();
		var reg = ajaxplorer.guiCompRegistry;
		var list = ajaxplorer.guiCompRegistry.detect(function(el){return (el.__className == "FilesList");});
		if(!list) return;
		var items = list.getSelectedItems();
		var node = ajaxplorer.getContextHolder().getUniqueNode();
		if(node && node.isLeaf()){
			mobileActions.select('a')[1].hide();
		}else{
			mobileActions.select('a')[1].show();
		}
		if(items && items.length){
			var item = items[0];
			itemPos = item.cumulativeOffset();
			itemDim = item.getDimensions();
			itemScroll = item.cumulativeScrollOffset();
			var listDisp = list._displayMode;
			mobileActions.show();
			var left;
			if(listDisp == "thumb"){
				left = itemPos[0] + 2;
			}else{
				left = itemPos[0] + itemDim.width - 90 - 2;
			}
			mobileActions.setStyle({
				zIndex:list.htmlElement.style.zIndex + 1,
				position:'absolute',
				left: left + 'px',
				top:(itemPos[1] - itemScroll[1]) + 1 + 'px'
			});						
		}
	});				
}

document.observe("ajaxplorer:gui_loaded", function(){
	initMobileActions();
	document.addEventListener("touchmove", function(event){
		event.preventDefault();
	});
	window.setTimeout(function(){
		attachSimpleScroll('table_rows_container', "vertical");
		attachSimpleScroll('selectable_div', "vertical");
		attachSimpleScroll('info_panel', "vertical");
		attachSimpleScroll('buttons_bar', "horizontal");
		attachSimpleScroll('div.rootDirChooser', "vertical");
	}, 5000);				
});