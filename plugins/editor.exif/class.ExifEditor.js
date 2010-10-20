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
 * Description : The "online edition" manager, encapsulate the CodePress highlighter for some extensions.
 */
Class.create("ExifEditor", AbstractEditor, {

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		this.actions.get("downloadFileButton").observe('click', function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'?action=download&file='+this.currentFile);
			return false;
		}.bind(this));
		this.element.observe("editor:resize", function(){
			this.columnsLayout(true);
		}.bind(this));				
		this.actions.get("gpsLocateButton").hide();
		this.actions.get("gpsLocateButton").observe("click", function(){
			if(!this.gpsData) return;
			hideLightBox();
			ExifCellRenderer.prototype.openLocator(this.gpsData.GPS_Latitude,this.gpsData.GPS_Longitude);			
		}.bind(this) );
	},
	
	
	open : function($super, userSelection){
		$super(userSelection);
		var fileName = userSelection.getUniqueFileName();
		// LOAD FILE NOW
		this.contentMainContainer = new Element("div", {id:"exifContainer",style:"overflow:auto;background-image:url('"+ajxpResourcesFolder+"/images/strip.png');font-family:Trebuchet MS"});
		this.element.insert(this.contentMainContainer);
		fitHeightToBottom($(this.contentMainContainer), $(modal.elementName));
		this.updateTitle(getBaseName(fileName));
		this.loadFileContent(fileName);
	},
	
	loadFileContent : function(fileName){
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'extract_exif');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){
			this.parseXml(transp);
		}.bind(this);
		connexion.sendAsync();
	},
	
	
	refreshGPSData : function(){
		if(!this.gpsData) return;
		this.actions.get("gpsLocateButton").show();		
	},
		
	columnsLayout : function(reset){		
		var container = this.contentMainContainer;
		if(reset){
			container.select('div.exifSection').each(function(el){container.insert(el);});
			container.select('div.column').invoke("remove");
		}
		var divWidth = container.getWidth();
		var colNumber = Math.floor(divWidth / 300);
		var items = container.select('tr');
		var sepNumber = Math.floor(items.length/colNumber);		
		var columns = $A();
		for (var i = 0; i<colNumber;i++){
			var column = new Element('div',{className:'column',style:'float:left;width:'+Math.floor(100/colNumber)+'%'});
			columns[i] = column;
			container.insert(column);
		}
		for(var k=0;k<items.length;k++){
			var position = Math.floor(k/sepNumber);
			//console.log(k, position+'/'+colNumber);
			var div = items[k].up('table').previous('div.panelHeader').up('div');
			if(position == colNumber) position--;
			if(div.moved && columns[position-1] && columns[position-1].down('div') == div){
				continue;
			}
			columns[position].insert(div);
			div.moved = true;
		}
	},
	
	parseXml : function(transport){
		var response = transport.responseXML;
		var sections = XPathSelectNodes(response.documentElement, "exifSection");
		if(!sections || !sections.length) return;
		this.itemsCount = 0;
		for(var i=0;i<sections.length;i++){
			var tags = XPathSelectNodes(sections[i], "exifTag");
			var div = new Element("div", {className:'exifSection',style:'border:1px solid #ccc;margin:3px;border-top:0px;'});
			this.contentMainContainer.insert(div);
			var sectionName = sections[i].getAttribute("name");
			div.insert('<div class="panelHeader infoPanelGroup">'+sectionName+'</div>');
			div.insert('<table class="infoPanelTable" '+(Prototype.Browser.IE?'style="width:97%;"':'')+'><tbody></tbody></table>');
			var tBody = div.down('tbody');
			var even = false;
			this.itemsCount ++;
			for(var j=0;j<tags.length;j++){
				try{
					var tagName = tags[j].getAttribute("name");
					var tagValue = tags[j].firstChild.nodeValue;
					if(sectionName == "COMPUTED_GPS"){
						var split = tagValue.split('--');
						if(!this.gpsData) this.gpsData = {};
						this.gpsData[tagName] = split[1];
						tagValue = split[0];
					}
					tBody.insert('<tr'+(even?' class="even"':'')+'><td class="infoPanelLabel">'+tagName+'</td><td class="infoPanelValue">'+tagValue+'</td></tr>');
					even = !even;
					this.itemsCount ++;
				}catch(e){}
			}			
		}
		this.columnsLayout();
		this.refreshGPSData();
	}
	
});