/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : A dynamic panel displaying details on the current file.
 */
Class.create("PropertyPanel", {

	initialize: function(userSelection, htmlElement){
		this.rights = ['4', '2', '1'];
		this.accessors = ['u', 'g', 'a'];
		this.accessLabels = [MessageHash[288], MessageHash[289], MessageHash[290]];
		this.rightsLabels = ['r', 'w', 'x'];

		this.htmlElement = $(htmlElement).select("[id='properties_box']")[0];
		if(userSelection.isUnique()){
			this.origValue = userSelection.getUniqueNode().getMetadata().get('file_perms');
		}else{
			this.origValue = '';
		}
		this.createChmodForm();
				
		this.valueInput.observe((Prototype.Browser.IE?'change':'input'), function(e){
			this.updateBoxesFromValue(this.valueInput.value);
		}.bind(this));
		this.updateBoxesFromValue(this.valueInput.value);		

		if(userSelection.hasDir()){
			this.createRecursiveBox();
		}		
	},
	
	valueChanged : function(){
		return (this.origValue != this.valueInput.value);
	},
	
	createChmodForm : function(){
		this.checks = $H({});
		var chmodTable = new Element('table', {style:"font-size:11px;"});

        var tHead = new Element('thead');
        var tBody = new Element('tbody');
        chmodTable.insert(tHead);
        chmodTable.insert(tBody);

		// Header Line
        var headerRow = new Element('tr');
        var emptyLabel = new Element('td');
        headerRow.insert(emptyLabel);
		tHead.insert(headerRow);
		for(var j=0;j<3;j++){
			headerRow.insert(new Element('td').update(this.rightsLabels[j]+'&nbsp;&nbsp;').setStyle({textAlign:'center'}));
		}
		// Boxes lines
		for(var i=0;i<3;i++){
            var permRow = new Element('tr');
			var label = new Element('td').setStyle({textAlign:'right',paddingRight:'2px', width:'35px'});
			label.insert(this.accessLabels[i]);
			tBody.insert(permRow);
            permRow.insert(label);
			for(var j=0;j<3;j++){
				var check = this.createCheckBox(this.accessors[i], this.rights[j]);
				permRow.insert(check);
			}
		}		
		
		this.valueInput = new Element('input', {value:this.origValue, name:'chmod_value'}).setStyle({width:'95%'});
		var valueRow = new Element('tr');
		tBody.insert(valueRow);
		valueRow.insert(new Element('td'));
		valueRow.insert(new Element('td', {colspan:3}).update(this.valueInput));
				
		this.htmlElement.insert(chmodTable);
	},
	
	createCheckBox : function(accessor, right){
		var box = new Element('input', {type:'checkbox', id:accessor+'_'+right}).setStyle({width:'14px',height:'14px',borderWidth:'0'});
		var div = new Element('td', {align:"center"}).insert(box).setStyle({width:'25px'});
		box.observe('click', function(e){
			this.updateValueFromBoxes();
		}.bind(this));
		this.checks.set(accessor+'_'+right, box);
		return div;
	},
	
	createRecursiveBox : function(){
		var recuDiv = new Element('div', {style:'padding-top:8px;'});
		var recurBox = new Element('input', {type:'checkbox', name:'recursive'}).setStyle({width:'14px',height:'14px',borderWidth:'0'});
		recuDiv.insert(recurBox);
		recuDiv.insert(MessageHash[291]);
		this.htmlElement.insert(recuDiv);
		
		var choices = { "both":"Both", "file":"Files", "dir":"Folders"};
		var choicesDiv = new Element('div');
		recuDiv.insert(choicesDiv);
		for(var key in choices){
			var choiceDiv = new Element('div', {style:'padding-left:25px'});
			var choiceDivBox = new Element('input', {
				type:'radio',
				name:'recur_apply_to',
				value:key,
				style:'width:25px;border:0;'
			});
			choiceDiv.insert(choiceDivBox);
			if(key=='both'){
				choiceDivBox.checked = true;
			}
			choiceDiv.insert(choices[key]);
			choicesDiv.insert(choiceDiv); 
		}
		choicesDiv.hide();
		
		recurBox.observe('click', function(e){
			if(recurBox.checked) choicesDiv.show();
			else choicesDiv.hide();
			modal.refreshDialogAppearance();
		});
		
	},
	
	updateValueFromBoxes : function(){
		var value = '0';
		for(var i=0; i<3;i++){
			value = value + this.updateValueForAccessor(this.accessors[i]);
		}
		this.valueInput.value = value;
	},
	
	updateValueForAccessor : function(accessor){
		var value = 0;
		for(var i=0;i<3;i++){
			value += (this.checks.get(accessor+'_'+this.rights[i]).checked?parseInt(this.rights[i]):0);
		}
		return value;
	},
	
	updateBoxesFromValue : function(value){
		if(value.length != 4 )return;
		for(var i=0;i<3;i++){
			this.valueToBoxes(parseInt(value.charAt(i+1)), this.accessors[i]);
		}
	},
	
	valueToBoxes : function(value, accessor){				
		for(var i=0;i<3;i++){
			this.checks.get(accessor+'_'+this.rights[i]).checked = false;
		}
		if(value == 0) return;
		var toCheck = $A([]);
		switch(value){
			case 1: 
				toCheck.push('1');
				break;
			case 2: 
				toCheck.push('2');
				break;
			case 3: 
				toCheck.push('1');
				toCheck.push('2');
				break;
			case 4: 
				toCheck.push('4');
				break;
			case 5: 
				toCheck.push('4');
				toCheck.push('1');
				break;
			case 6: 
				toCheck.push('4');
				toCheck.push('2');
				break;
			case 7: 
				toCheck.push('2');
				toCheck.push('4');
				toCheck.push('1');
				break;			
		}
		toCheck.each(function(ch){
			this.checks.get(accessor+'_'+ch).checked = true;
		}.bind(this));
	}
	
});