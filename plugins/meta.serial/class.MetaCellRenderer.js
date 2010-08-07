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
 * Description : Static class for renderers
 */
Class.create("MetaCellRenderer", {	
	initialize: function(){
	},
	
	starsRateFilter: function(tableCell){
		var value = 0;
		try{
			var content = tableCell.select('span')[0].innerHTML;
		}catch(e){
		}
		if(content) value = parseInt(content);
		var obj = new MetaCellRenderer();
		tableCell.update(obj.createStars(value));		
	},
	
	infoPanelStars : function(htmlElement){
		var td = htmlElement.select('#ip_stars_rate')[0];
		if(td){
			var obj = new MetaCellRenderer();
			td.update(obj.createStars(parseInt(td.innerHTML)));
		}
	},
		
	createStars : function(value){
		var imgOff = 'plugins/meta.serial/rating_off.png';
		var imgOn = 'plugins/meta.serial/rating.png';
		var cont = new Element('div');
		for(var i=1;i<6;i++){
			var img = new Element('img',{
				src:(value>=i?imgOn:imgOff),
				style:'float:left;cursor:pointer;margin-right:2px;',
				note:i
			});
			cont.insert(img);
		}
		cont.select('img').invoke('observe', 'click', function(event){
			var note = Event.element(event).readAttribute('note');
			window.setTimeout(function(){
				var selectedNode = ajaxplorer.getUserSelection().getUniqueNode();
				var conn = new Connexion();
				conn.setParameters(new Hash({
					get_action : 'edit_serial_meta',
					stars_rate : note,
					file	   : selectedNode.getPath(),
					dir		   : getRepName(selectedNode.getPath())
				}));
				conn.onComplete = function(){
					ajaxplorer.fireContextRefresh();
				};
				conn.sendAsync();
			}, 500);
		});
		return cont;
	}
});