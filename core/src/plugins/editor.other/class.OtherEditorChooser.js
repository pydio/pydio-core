/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
Class.create("OtherEditorChooser", AbstractEditor, {

	initialize: function($super, oFormObject)
	{
		this.element = oFormObject;
	},
	
	
	open : function($super, userSelection){
		$super(userSelection);
		var node = userSelection.getUniqueNode();
		var allEditors = this.findActiveEditors(node.getAjxpMime());
		var selector = this.element.down('#editor_selector');
		var even = false;
		allEditors.each(function(el){
			if(el.editorClass == "OtherEditorChooser") return;
			var elDiv = new Element('a', {
				href:'#', 
				className:(even?'even':''),
				style:"background-image:url('"+resolveImageSource(el.icon, '/images/actions/ICON_SIZE', 22)+"')"
				}).update(el.text + '<span>'+el.title+'</span>');
			even = !even;
			elDiv.editorData = el;
			elDiv.observe('click', this.selectEditor.bind(this));
			selector.insert(elDiv);
		}.bind(this) );
	},
	
	selectEditor : function(event){
        Event.stop(event);
		if(!event.target.editorData) return;
		ajaxplorer.loadEditorResources(event.target.editorData.resourcesManager);
		hideLightBox();
		modal.openEditorDialog(event.target.editorData);		
	},
	
	/**
	 * Find Editors that can handle a given mime type
	 * @param mime String
	 * @returns AbstractEditor[]
	 */
	findActiveEditors : function(mime){
		var editors = $A([]);
		var checkWrite = false;
		if(this.user != null && !this.user.canWrite()){
			checkWrite = true;
		}
		ajaxplorer.getActiveExtensionByType('editor').each(function(el){
			if(checkWrite && el.write) return;
			if(!el.openable) return;
			if(el.mimes.include(mime) || el.mimes.include('*')) return;
			editors.push(el);
		});
		if(editors.length && editors.length > 1){
			editors = editors.sortBy(function(ed){
				return ed.order||0;
			});
		}
		return editors;
	}
});