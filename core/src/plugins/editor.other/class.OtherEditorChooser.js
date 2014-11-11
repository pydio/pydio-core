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
Class.create("OtherEditorChooser", AbstractEditor, {

	initialize: function($super, oFormObject, options)
	{
		this.element = oFormObject;
        this.editorOptions = options;
	},

    /*
    Not used, reported in activeCondition
    Dynamic patching of ajaxplorer findEditorsForMime, nice!
     */
    patchEditorLoader : function(){
        var original = ajaxplorer.__proto__.findEditorsForMime;
        ajaxplorer.__proto__.findEditorsForMime = function(mime, restrictToPreviewProviders){
            if(this.user && this.user.getPreference("gui_preferences", true) && this.user.getPreference("gui_preferences", true)["other_editor_extensions"]){
                $H(this.user.getPreference("gui_preferences", true)["other_editor_extensions"]).each(function(pair){
                    var editor = this.getActiveExtensionByType("editor").detect(function(ed){
                        return ed.editorClass == pair.value;
                    });
                    if(editor && !$A(editor.mimes).include(pair.key)){
                        editor.mimes.push(pair.key);
                    }
                }.bind(this));
            }
            return original.apply(this, [mime, restrictToPreviewProviders]);
        };
    },
	
	open : function($super, node){
		$super(node);
        this.currentNode = node;
		var allEditors = this.findActiveEditors(node.getAjxpMime());
		var selector = this.element.down('#editor_selector');
        var clearAssocLink = this.element.down('#clear_assoc_link');
        clearAssocLink.observe("click", function(){
            this.clearAssociations(node.getAjxpMime());
        }.bind(this) );
        if(window.ajxpMobile){
            attachMobileScroll(selector, "vertical");
        }
		var even = false;
		allEditors.each(function(el){
            var elDiv;
			if(el.editorClass == "OtherEditorChooser") return;
            if(ajaxplorer.currentThemeUsesIconFonts && el.icon_class){
                elDiv = new Element('a', {
                    href:'#',
                    className:'iconic '+(even?'even':'')
                }).update('<span class="'+ el.icon_class+'"></span>' + el.text + '<span class="chooser_editor_legend">'+el.title+'</span>');
            }else{
                elDiv = new Element('a', {
                    href:'#',
                    className:(even?'even':''),
                    style:"background-image:url('"+resolveImageSource(el.icon, '/images/actions/ICON_SIZE', 22)+"');background-size:22px;"
                }).update(el.text + '<span class="chooser_editor_legend">'+el.title+'</span>');
            }
			even = !even;
            elDiv.currentMime = node.getAjxpMime();
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
        if(!ajaxplorer._editorOpener || event.target.editorData.modalOnly){
            modal.openEditorDialog(event.target.editorData);
        }else{
            ajaxplorer._editorOpener.openEditorForNode(this.currentNode, event.target.editorData);
        }
        this.createAssociation(event.target.currentMime, event.target.editorData.editorClass);
	},

    createAssociation : function(mime, editorClassName){
        var editor = ajaxplorer.getActiveExtensionByType("editor").detect(function(ed){
            return ed.editorClass == editorClassName;
        });
        if(editor && !$A(editor.mimes).include(mime)){
            editor.mimes.push(mime);
            if(ajaxplorer && ajaxplorer.user){
                var guiPrefs = ajaxplorer.user.getPreference("gui_preferences", true) || {};
                var exts = guiPrefs["other_editor_extensions"] || {};
                exts[mime] = editorClassName;
                guiPrefs["other_editor_extensions"] = exts;
                ajaxplorer.user.setPreference("gui_preferences", guiPrefs, true);
                ajaxplorer.user.savePreference("gui_preferences");
            }
        }
    },

    clearAssociations : function(mime){
        try{
            var guiPrefs = ajaxplorer.user.getPreference("gui_preferences", true);
            var assoc = guiPrefs["other_editor_extensions"];
        }catch(e){}
        if(assoc && assoc[mime]){
            var editorClassName = assoc[mime];
            var editor = ajaxplorer.getActiveExtensionByType("editor").detect(function(ed){
                return ed.editorClass == editorClassName;
            });
            if(editor){
                editor.mimes = $A(editor.mimes).without(mime);
            }
            delete assoc[mime];
            guiPrefs["other_editor_extensions"] = assoc;
            ajaxplorer.user.setPreference("gui_preferences", guiPrefs, true);
            ajaxplorer.user.savePreference("gui_preferences");
        }
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