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
Class.create("ExifEditor", AbstractEditor, {

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		this.actions.get("downloadFileButton").observe('click', function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=download&file='+this.currentFile);
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
		this.contentMainContainer = new Element("div", {id:"exifContainer",style:"overflow:auto;font-family:Trebuchet MS"});
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
        if(!container) return;
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
			div.insert('<table class="infoPanelTable" '+(Prototype.Browser.IE?'style="width:97%;"':'')+' cellspacing="0" ><tbody></tbody></table>');
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
	},
	
	getPreview : function(ajxpNode){
		return Diaporama.prototype.getPreview(ajxpNode);
	},
	
	getThumbnailSource : function(ajxpNode){
		return Diaporama.prototype.getThumbnailSource(ajxpNode);
	}
	
});