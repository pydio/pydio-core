SQLEditor = Class.create({

	initialize: function(oFormObject)
	{
		this.oForm = $(oFormObject);
		modal.setCloseAction(function(){this.close();}.bind(this));
	},
	
	
	createEditor : function(){
	
		var userSelection = ajaxplorer.filesList.getUserSelection();
		if(userSelection.hasFile()){
			this.createRecordEditor(userSelection);
		}else{
			this.createTableEditor(userSelection);
		}		
	},
	
	createRecordEditor: function(userSelection){
		var columns = ajaxplorer.filesList.getColumnsDef();
		var crtTableName = getBaseName(ajaxplorer.filesList.getCurrentRep());
		
		this.oForm.insert(new Element('input', {type:'hidden',name:'table_name', value:crtTableName}));
		var table = new Element('table', {className:'sqlRecordForm'});
		this.fields = $A([]);
		
		$A(columns).each(function(col){			
			this.fields.push(col.attributeName);
			if(col.field_pk == "1"){
				this.oForm.insert(new Element('input', {type:'hidden',name:'pk_name', value:col.attributeName}));
			}
			var tr= new Element('tr');
			var labelTD = new Element('td', {className:'sqlLabelTd'}).update(col.attributeName + ' :');
			var typeTD = new Element('td', {className:'sqlTypeTd'}).update('('+col.field_type+')');
			var inputTD = new Element('td', {className:'sqlInputTd'});
			var input;
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
			inputTD.update(input);
			tr.insert(labelTD);
			tr.insert(inputTD);
			tr.insert(typeTD);
			table.insert(tr);			
		}.bind(this));
		
		this.oForm.insert({top:table});		
		var newRec = new Element('input', {type:'hidden',name:'record_is_new', value:'true'});
		this.oForm.insert(newRec);
		if(userSelection && !userSelection.isEmpty()){
			newRec.value = 'false';
			var item = userSelection.getUniqueItem();
			var value = new Hash();
			this.fields.each(function(fName){
				value.set(fName, item.getAttribute(fName));
			});
			var formManager = new FormManager();
			formManager.fetchValueToForm(this.oForm, this.fields, value.toObject());
		}
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
			var columns = ajaxplorer.filesList.getColumnsDef();
			var fields = $A(["field_name", "field_type", "field_size", "field_flags", "field_default", "field_pk", "field_null"]);
			
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
			templateTable.select('td[edit="false"]').invoke('remove');
			templateRow.select('input', 'textarea', 'select').invoke('disable');
			templateRow.setAttribute('enabled', 'false');
			var activator = new Element('a', {href:'#', className:'enableRow'}).update('E');
			templateRow.select('td[new="false"]')[0].update(activator);
			templateTable.observe('click', function(e){
				if(e.findElement('a') && e.findElement('a').hasClassName('enableRow')){
					var row = e.findElement('tr');
					if(row.getAttribute('enabled') && row.getAttribute('enabled') == "true"){
						row.select('input', 'textarea', 'select').invoke('disable');
						row.setAttribute('enabled', 'false');
						e.findElement('a').update('E');						
					}else{
						row.select('input', 'textarea', 'select').invoke('enable');
						row.setAttribute('enabled', 'true');
						e.findElement('a').update('D');
					}
					Event.stop(e);
				}
			});
		}else{
			templateTable.select('td[new="false"]').invoke('remove');
		}
		this.oForm.insert(templateTable);
		var fManager = new FormManager();
		fManager.replicateRow(templateRow, numberReplicates);
		if(fields && values){
			fManager.fetchMultipleValueToForm(templateTable, fields, values);
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
	},
	
	loadFile : function(fileName){
	},
	
	saveFile : function(){
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
			this.heightObserver = fitHeightToBottom(this.textarea, this.oForm, 0, true);
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
		if(!this.currentUseCp) {fitHeightToBottom(this.textarea, this.oForm, 0, true);}
	}
	
});