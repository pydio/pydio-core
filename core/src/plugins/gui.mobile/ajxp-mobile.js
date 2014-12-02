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
function getAjxpMobileActions(){
	var mobileActions = $('mobile_actions_copy');
	if(mobileActions){
		return mobileActions;
	}
	mobileActions = $('mobile_actions').clone(true);
	mobileActions.id = "mobile_actions_copy";
	var act = mobileActions.select('a');
	act[0].observe('click', function(e){
		Event.stop(e);
        if($('info_container').down('.info_panel_title_span')){
            $('info_container').down('.info_panel_title_span').update(ajaxplorer.getContextHolder().getUniqueNode().getLabel());
        }
		$('info_container').show();
		$('info_container').ajxpPaneObject.resize();
		$('info_container').select('.infoPanelActions a').each(function(action){
			action.observe("click", function(){$('info_container').hide();});
		});
	});
	act[1].observe('click', function(e){		
		ajaxplorer.actionBar.fireAction(act[1]._action);
		Event.stop(e);
	});
	return mobileActions;
}

function initAjxpMobileActions(){
	document.observe("ajaxplorer:selection_changed", function(e){
		var list = e.memo._selectionSource;
		if(!list) return;
		var mobileActions = getAjxpMobileActions();
		mobileActions.hide();
		var items = list.getSelectedItems();
		var node = ajaxplorer.getContextHolder().getUniqueNode();
		var a = mobileActions.select('a')[1];		
		
		if(node && node.isLeaf()){
			//mobileActions.select('a')[1].hide();
			var editors = ajaxplorer.findEditorsForMime(getAjxpMimeType(node));			
			if(editors.length){
				a.show();
				a._action = "open_with";
				a.update("Open");
			}else{
				a.hide();
			}			
		}else{
			a.show();
			a._action = "ls";			
			a.update("Explore");
		}
		if(items && items.length){
			var item = items[0];
			//itemPos = item.cumulativeOffset();
			var itemPos = item.positionedOffset();
			var itemDim = item.getDimensions();
			var itemScroll = item.cumulativeScrollOffset();
			var listDisp = list._displayMode;
			mobileActions.show();
			var left;
			var container;
			if(listDisp == "thumb"){
				left = itemPos[0] + 2;
				container = list.htmlElement.down(".selectable_div");
			}else{
				left = itemPos[0] + itemDim.width - 90 - 2;
				container = list.htmlElement.down(".table_rows_container");
			}
			container.insert(mobileActions);
			container.setStyle({position:'relative'});
			mobileActions.setStyle({
				zIndex:list.htmlElement.style.zIndex + 1,
				position:'absolute',
				left: left + 'px',
				top:(itemPos[1]) + 2 + 'px'
			});						
		}
	});				
}

document.observe("ajaxplorer:gui_loaded", function(){
	//initAjxpMobileActions();
	document.addEventListener("touchmove", function(event){
		event.preventDefault();
	});
});