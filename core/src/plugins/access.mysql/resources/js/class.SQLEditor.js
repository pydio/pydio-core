/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
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
Class.create("SQLEditor", {

	initialize: function(oFormObject)
	{
		this.oForm = $(oFormObject);
		modal.setCloseAction(function(){this.close();}.bind(this));
	},
	
	
	createEditor : function(){
		var userSelection = ajaxplorer.getUserSelection();
		if(userSelection.hasFile()){
			this.createRecordEditor(userSelection);
		}else{
			this.createTableEditor(userSelection);
		}
	},
	
	createRecordEditor: function(userSelection){
		var tmpSelection = ajaxplorer.getUserSelection();
		if(tmpSelection.getSelectionSource()){
			this.currentColumns = tmpSelection.getSelectionSource().getColumnsDef();
		}else{
			this.currentColumns = [];
		}
		var columns = this.currentColumns;
		var crtTableName = getBaseName(ajaxplorer.getContextNode().getPath());
		this.oForm.insert(new Element('input', {type:'hidden',name:'table_name', value:crtTableName}));
		var table = new Element('table', {width:'96%', className:'sqlRecordForm'});
		var tBody = new Element('tbody');
		table.insert(tBody);
		this.fields = $A([]);
		
		$A(columns).each(function(col){			
			this.fields.push(col.attributeName);
			var disable = false;
			var auto_inc = false;
			if(col.field_pk == "1"){
				this.oForm.insert(new Element('input', {type:'hidden',name:'pk_name', value:col.attributeName}));
				if(userSelection && !userSelection.isEmpty()){
					disable = true;
				}else if(col.field_flags.search('auto_increment') > -1){
					disable = true;
					auto_inc = true;
				}
			}
			var tr= new Element('tr');
			var labelTD = new Element('td', {className:'sqlLabelTd'}).update(col.attributeName + ' :');
			var typeTD = new Element('td', {className:'sqlTypeTd'}).update('('+col.field_type+(auto_inc?',auto':'')+')');
			var inputTD = new Element('td', {className:'sqlInputTd'});
			var input;
			if(!col.field_type)return;
			var type = col.field_type.toLowerCase();			
			switch(type){
				case "enum":
				case "set":
					input = new Element('select', {name:col.attributeName});					
					var setString = col.field_size;
					while(setString.search('\'')>-1) setString = setString.replace('\'', '');
					var arr = $A(setString.split(','));
					arr.each(function(setValue){
						input.insert(new Element('option', {value:setValue}).update(setValue) );
					});
					break;
				case "varchar":
				case "char":
				case "string":
				case "tinyblob":
				case "tinytext":
				case "longblob":
				case "longtext":
				case "mediumblob":
				case "mediumtext":
				case "blob":
				case "text":
					if(parseInt(col.field_size) >  70){
						input = new Element('textarea', {name:col.attributeName});
					}else{
						input = new Element('input', {name:col.attributeName});
					}
					break;
				default : 
					input = new Element('input', {name:col.attributeName});
					break;
			}
			if(disable) input.disable();
			inputTD.update(input);
			tr.insert(labelTD);
			tr.insert(inputTD);
			tr.insert(typeTD);
			tBody.insert(tr);
		}.bind(this));
		
		var crtElement = this.oForm.select('div[id="mysql_edit_record"]')[0];
		crtElement.insert({top:table});		
		var newRec = new Element('input', {type:'hidden',name:'record_is_new', value:'true'});
		this.oForm.insert(newRec);
		if(userSelection && !userSelection.isEmpty()){
			newRec.value = 'false';
			var item = userSelection.getUniqueNode();
			var meta = item.getMetadata();
			var value = new Hash();
			this.fields.each(function(fName){
				if(Prototype.Browser.IE && fName == "ID"){
					value.set(fName, meta.get("ajxp_sql_"+fName));
				}else{
					value.set(fName, meta.get(fName));
				}
			});
			var formManager = new FormManager();
			formManager.fetchValueToForm(this.oForm, this.fields, value.toObject());
		}
		modal.refreshDialogPosition(true, $('mysql_edit_record'));
	},
	
	submitRecordForm : function(){
		var oForm = modal.getForm();
		oForm.getElements().each(function(el){
			if($A(this.fields).include(el.getAttribute('name'))){
				el.setAttribute('name', 'ajxp_mysql_'+el.getAttribute('name'));
			}
		}.bind(this));
		ajaxplorer.actionBar.submitForm(oForm, true);
		hideLightBox();
	},
	
	createTableEditor: function(tableName){
		if(!tableName){
			this.displayReplicationChooser();			
		}else{
			var userSelection = ajaxplorer.getUserSelection();
			if(userSelection.getSelectionSource()){
				this.currentColumns = userSelection.getSelectionSource().getColumnsDef();
			}else{
				this.currentColumns = [];
			}		
			var columns = this.currentColumns;
			var fields = $A(["field_name", "field_origname", "field_type", "field_size", "field_flags", "field_default", "field_pk", "field_null"]);
			columns.each(function(col){
				col['field_origname'] = col['field_name'];
			});
			this.oForm.insert(new Element('input', {type:'hidden',name:'current_table',value:getBaseName(tableName)}));
			this.displayTableEditorForm(columns.length, fields, columns);
		}
	},
	
	displayReplicationChooser : function(){
		var chooser = $('replication_chooser').cloneNode(true).setStyle({display:'block'});
		this.oForm.insert(chooser);
		var button = chooser.select('input[id="toNext"]')[0];
		button.observe('click', function(e){
			Event.stop(e);
			this.newTableName = chooser.select('input[id="table_name"]')[0].value;
			var fieldsNumber = parseInt(chooser.select('input[id="fields_number"]')[0].value);
			if(this.newTableName && fieldsNumber){
				chooser.remove();
				this.displayTableEditorForm(fieldsNumber);
			}else{
				alert('Missing parameters!');
			}
		}.bind(this));
		cancelButton = chooser.select('input[id="can"]')[0];
		cancelButton.observe('click', function(e){
			hideLightBox();
			modal.close();
		}.bind(this) );
	},
	
	displayTableEditorForm : function(numberReplicates, fields, values){
		var templateTable = $('create_table_template').cloneNode(true).setStyle({display:'block'});
		var templateRow = templateTable.select('tbody > tr')[0];
		if(values){
			// MAKE "ADD COLUMN"
			var addTable = $('create_table_template').cloneNode(true).setStyle({display:'block'});
			addTable.select('input', 'select', 'textarea').each(function(fElem){
				fElem.name = 'add_'+fElem.name;
			});
			//addTable.select('td[edit="false"]').invoke('remove');
			addTable.select('td[new="false"]')[0].setStyle({width:'40px'});
			addRow = addTable.select('tbody tr')[0];
			var addButton = new Element('input', {type:'button', value:'Add', className:'dialogButton'});
			var submitDiv = new Element('div', {className:'dialogButtons'}).insert(addButton);
			var submitRow = new Element('tr').insert(new Element('td', {colspan:"9"}).insert(submitDiv));
			addRow.insert({after:submitRow});
			addButton.observe('click', function(e){
				this.triggerAddColumn();
			}.bind(this));
			
			
			// MAKE ACTIONS
			templateTable.select('td[edit="false"]').invoke('remove');
			templateRow.select('input', 'textarea', 'select').invoke('disable');
			templateRow.setAttribute('enabled', 'false');
			var activator = new Element('img', {
				src:ajxpResourcesFolder+'/images/actions/16/encrypted.png',
				height:'16',
				width:'16',
				border:'0',
				className:'enableRow',
				style:'cursor:pointer;'
			});
			templateRow.select('td[new="false"]')[0].update(activator);
			// Additionnal actions
			var deleteCol = new Element('img', {
				src:ajxpResourcesFolder+'/images/actions/16/button_cancel.png',
				height:'16',
				width:'16',
				hspace:'5',
				border:'0',
				className:'deleteRow',
				style:'cursor:pointer;'
			});
			activator.insert({before:deleteCol});
			templateTable.observe('click', function(e){
				if(e.findElement('img') && e.findElement('img').hasClassName('enableRow')){
					var row = e.findElement('tr');
					if(row.getAttribute('enabled') && row.getAttribute('enabled') == "true"){
						row.select('input', 'textarea', 'select').invoke('disable');
						row.setAttribute('enabled', 'false');
						e.findElement('img').src=ajxpResourcesFolder+'/images/actions/16/encrypted.png';
					}else{
						row.select('input', 'textarea', 'select').invoke('enable');
						row.setAttribute('enabled', 'true');
						e.findElement('img').src=ajxpResourcesFolder+'/images/actions/16/decrypted.png';
					}
					Event.stop(e);
				}else if(e.findElement('img') && e.findElement('img').hasClassName('deleteRow')){
					var row = e.findElement('tr');
					var origName = '';
					row.select('input').each(function(input){
						if(input.name.search('field_origname') > -1){
							origName = input.value;
							return;
						}
					});
					if(origName != ''){
						var confirm = window.confirm('Are you sure you want to delete the column '+origName+'?');
						if(confirm){
							this.triggerDeleteColumn(origName);
						}
					}
				}
			}.bind(this));
		}else{
			templateTable.select('td[new="false"]').invoke('remove');
		}
		if(addTable){
			this.oForm.insert(this.createFieldSet('Add column', addTable));
			this.oForm.insert(this.createFieldSet('Change columns (unlock to edit)', templateTable));
		}else{
			this.oForm.insert(this.createFieldSet('Step 2: Edit columns for table \"'+this.newTableName+'"', templateTable));
		}
		var fManager = new FormManager();
		fManager.replicateRow(templateRow, numberReplicates, this.oForm);
		if(fields && values){
			fManager.fetchMultipleValueToForm(this.oForm, fields, values);
		}
		if(this.newTableName){
			this.oForm.insert(new Element('input', {type:'hidden',name:'new_table',value:this.newTableName}));
		}
		modal.addSubmitCancel(this.oForm);
		this.oForm.onsubmit = function(){
			var rows = this.oForm.select('tbody tr');
			rows.each(function(row){
				if(row.getAttribute('enabled')=='false') row.remove();
			});
			ajaxplorer.actionBar.submitForm(this.oForm);
			hideLightBox();
			return false;			
		}.bind(this);
		modal.refreshDialogPosition(true, templateTable);
	},
	
	triggerDeleteColumn : function(columnName){
		var currentTable = this.oForm.select('input[name="current_table"]')[0].value;
		var parameters = new Hash();
		parameters.set('get_action', 'edit_table');
		parameters.set('delete_column', columnName);
		parameters.set('current_table', currentTable);
		var connexion = new Connexion();
		connexion.setParameters(parameters);		
		connexion.onComplete = function(transport){ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);};
		connexion.sendAsync();
		hideLightBox();
	},
	
	triggerAddColumn : function(){
		var params = new Hash();
		var currentTable = this.oForm.select('input[name="current_table"]')[0].value;
		params.set('get_action', 'edit_table');
		params.set('add_column', 'true');
		params.set('current_table', currentTable);
		this.oForm.select('input', 'textarea', 'select').each(function(elem){
			if(elem.name.search("add_") == 0){
				params.set(elem.name, elem.getValue());
			}
		});		
		var connexion = new Connexion();
		connexion.setParameters(params);		
		connexion.onComplete = function(transport){ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);};
		connexion.sendAsync();
		hideLightBox();		
	},
	
	createFieldSet:function(legend, content){
		var fSet = new Element('fieldset').insert(new Element('legend').update(legend)).insert(content);
		return fSet;		
	},
	
	parseXml : function(transport){
		//alert(transport.responseText);
		this.changeModifiedStatus(false);
		this.removeOnLoad();
	},
	
	parseTxt : function(transport){	
	},
	
	changeModifiedStatus : function(bModified){
		this.modified = bModified;
		var crtTitle = modal.dialogTitle.select('span.titleString')[0];
		if(this.modified){
			this.saveButton.removeClassName('disabled');
			if(crtTitle.innerHTML.charAt(crtTitle.innerHTML.length - 1) != "*"){
				crtTitle.innerHTML  = crtTitle.innerHTML + '*';
			}
		}else{
			this.saveButton.addClassName('disabled');
			if(crtTitle.innerHTML.charAt(crtTitle.innerHTML.length - 1) == "*"){
				crtTitle.innerHTML  = crtTitle.innerHTML.substring(0, crtTitle.innerHTML.length - 1);
			}		
		}
		// ADD / REMOVE STAR AT THE END OF THE FILENAME
	},
	
	setOnLoad : function(){	
		addLightboxMarkupToElement(this.textareaContainer);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+"/images/loadingImage.gif";
		$(this.textareaContainer).select("#element_overlay")[0].appendChild(img);
		this.loading = true;
	},
	
	removeOnLoad : function(){
		removeLightboxFromElement(this.textareaContainer);
		this.loading = false;	
	},
	
	close : function(){
		if(this.currentUseCp){
			this.cpCodeObject.close();
			modal.clearContent(modal.dialogContent);		
		}
	},
	
	setFullScreen: function(){
		this.oForm.absolutize();
		$(document.body).insert(this.oForm);
		this.oForm.setStyle({
			top:0,
			left:0,
			backgroundColor:'#fff',
			width:'100%',
			height:document.viewport.getHeight(),
			zIndex:3000});
		this.actionBar.setStyle({marginTop: 0});
		if(!this.currentUseCp){
			this.origContainerHeight = this.textarea.getHeight();
			this.heightObserver = fitHeightToBottom(this.textarea, this.oForm);
		}else{
			
		}		
		var listener = this.fullScreenListener.bind(this);
		Event.observe(window, "resize", listener);
		this.oForm.observe("fullscreen:exit", function(e){
			Event.stopObserving(window, "resize", listener);
			//Event.stopObserving(window, "resize", this.heightObserver);
		}.bind(this));		
		this.fullscreenMode = true;
	},
	
	exitFullScreen: function(){
		this.oForm.relativize();
		$$('.dialogContent')[0].insert(this.oForm);
		this.oForm.setStyle({top:0,left:0,zIndex:100});
		this.actionBar.setStyle({marginTop: -10});
		this.oForm.fire("fullscreen:exit");
		if(!this.currentUseCp){
			this.textarea.setStyle({height:this.origContainerHeight});
		}else{
			
		}		
		this.fullscreenMode = false;
	},
	
	fullScreenListener : function(){
		this.oForm.setStyle({
			height:document.viewport.getHeight()
		});
		if(!this.currentUseCp) {fitHeightToBottom(this.textarea, this.oForm);}
	}
	
});