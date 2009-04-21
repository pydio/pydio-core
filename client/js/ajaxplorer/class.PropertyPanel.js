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
PropertyPanel = Class.create({

	initialize: function(userSelection, htmlElement){
		this.rights = ['4', '2', '1'];
		this.accessors = ['u', 'g', 'a'];
		this.accessLabels = [MessageHash[288], MessageHash[289], MessageHash[290]];
		this.rightsLabels = ['r', 'w', 'x'];

		this.htmlElement = $(htmlElement).select("[id='properties_box']")[0];
		if(userSelection.isUnique()){
			this.origValue = userSelection.getUniqueItem().getAttribute('file_perms');
		}else{
			this.origValue = '';
		}
		this.createChmodForm();
		
		this.valueInput = new Element('input', {value:this.origValue, name:'chmod_value'}).setStyle({width:'76px', marginLeft:'55px'});
		this.valueInput.observe('input', function(e){
			this.updateBoxesFromValue(this.valueInput.value);
		}.bind(this));
		this.updateBoxesFromValue(this.valueInput.value);		
		this.htmlElement.insert(this.valueInput);

		if(userSelection.hasDir()){
			var recuDiv = new Element('div', {style:'padding-top:8px;'});
			var recurBox = new Element('input', {type:'checkbox', name:'recursive'}).setStyle({width:'25px'});
			recuDiv.insert(recurBox);
			recuDiv.insert(MessageHash[291]);
			this.htmlElement.insert(recuDiv);
		}		
	},
	
	valueChanged : function(){
		return (this.origValue != this.valueInput.value);
	},
	
	createChmodForm : function(){
		this.checks = $H({});
		var chmodDiv = new Element('div').setStyle({width: '140px'});
		// Header Line
		var emptyLabel = new Element('div').setStyle({cssFloat:'left',width:'50px', height:'16px'});
		chmodDiv.insert(emptyLabel);
		for(var j=0;j<3;j++){
			chmodDiv.insert(new Element('div').update(this.rightsLabels[j]+'&nbsp;&nbsp;').setStyle({cssFloat:'left',width:'30px', textAlign:'center'}));
		}
		// Boxes lines
		for(var i=0;i<3;i++){
			var label = new Element('div').setStyle({cssFloat:'left',width:'50px', height:'16px', textAlign:'right'});
			label.insert(this.accessLabels[i]);
			chmodDiv.insert(label);
			for(var j=0;j<3;j++){
				var check = this.createCheckBox(this.accessors[i], this.rights[j]);
				chmodDiv.insert(check);
			}
		}		
		this.htmlElement.insert(chmodDiv);
	},
	
	createCheckBox : function(accessor, right){
		var box = new Element('input', {type:'checkbox', id:accessor+'_'+right}).setStyle({width:'25px'});
		var div = new Element('div').insert(box).setStyle({cssFloat:'left',width:'30px'});
		box.observe('click', function(e){
			this.updateValueFromBoxes();
		}.bind(this));
		this.checks.set(accessor+'_'+right, box);
		return div;
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
			this.valueToBoxes(parseInt(value[i+1]), this.accessors[i]);
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