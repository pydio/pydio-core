Class.create("HeaderResizer", {
	initialize:function(element, options){
		this.element = element;
		this.options = Object.extend({
			initSizes : null,
			initSizesType : 'percent',
			cellSelector : 'div.header_cell',
			handleSelector : 'div.resizer',
			body : $('tBody'), 
			bodyRowSelector : 'tr',
			bodyCellSelector : 'td',
			bodyIsMaster : false,
			handleWidth : 3,
			headerData : null,			
			useCSS3 : false
		}, options || { });
		if(this.options.headerData){
			this.generateHeader();
		}
		this.options.useCSS3 = this.detectCSS3();
		this.mainSize = this.element.getWidth();
		this.cells = this.element.select(this.options.cellSelector);		
		this.cells.each(function(el){disableTextSelection(el);});
		this.element.select(this.options.handleSelector).each(function(el){
			this.initDraggable(el);
		}.bind(this) );
		var sizes;
		if(this.options.initSizes){
			if(this.options.initSizesType == 'percent'){
				sizes = this.computePercentSizes(this.options.initSizes, 100);
			}else{
				sizes = this.options.initSizes;
			}
		}else{
			sizes = this.computeEqualSizes();
		}
		
		this.resizeHeaders(sizes);
		
		this.observe("drag_resize", function(e){
			this.refreshBody();
		}.bind(this));
	},
	
	generateHeader : function(){
		var data = this.options.headerData;
		this.element.addClassName('header_resizer');
		var initSizes;
		var index=0;
		data.each(function(el){
			this.element.insert('<div class="header_cell"><div class="header_label">'+el.label+'</div></div>');
			if(el  != data.last()){
				this.element.insert('<div class="resizer">&nbsp;</div>');
			}
			if(el.size){
				if(!initSizes) initSizes = $A();
				initSizes[index] = el.size;
			}
			index++;
		}.bind(this) );
		if(initSizes && initSizes.length){
			this.options.initSizes = initSizes;			
		}
	},
	
	computeEqualSizes : function(){
		var ratio = 1 / this.cells.length;
		var sizes = $A();
		var computeWidth = this.getInnerWidth() - (this.cells.length - 1) * this.options.handleWidth;
		var uniqueSize = Math.floor(computeWidth * ratio);
		for(var i=0;i<this.cells.length;i++) sizes[i] = uniqueSize;
		return sizes;
	},
	
	computePercentSizes : function(previousSizes, previousInner){
		var sizes = $A();
		var innerWidth = this.getInnerWidth();
		previousSizes.each(function(s){
			sizes.push(Math.round(s * innerWidth / previousInner));
		});
		return sizes;
	},
		
	getCurrentSizes : function(percent){
		if(!percent) return this.currentSizes;
		var innerWidth = this.getInnerWidth();
		var percentSizes = $A();
		for(var i=0;i<this.currentSizes.length;i++){
			percentSizes[i] = Math.floor(this.currentSizes[i]/innerWidth*100);
		}
		return percentSizes;
	},
	
	resize : function(size){
		
		this.mainSize = size;
		this.element.setStyle({width:this.mainSize});
		this.checkBodyScroll();
		this.resizeHeaders();
		this.log("Resize:"+size);
		if(this.timer){
			window.clearTimeout(this.timer);
		}
		this.timer = window.setTimeout(this.refreshBody.bind(this), 50);
	},
	
	resizeHeaders : function(sizes){
		
		//this.checkBodyScroll();
		
		var innerWidth = this.getInnerWidth();	
		if(!innerWidth) return;	
		if(!sizes && this.currentInner && innerWidth != this.currentInner){
			sizes = this.computePercentSizes(this.currentSizes, this.currentInner);						
		}
		//console.log("return");
		if(!sizes) return;
		this.log("Inner Width:"+innerWidth+'/'+this.element.offsetWidth);
		var total = 0;
		for(var i=0;i<this.cells.length;i++){
			if(!sizes[i]) sizes[i] = Math.floor((innerWidth - (this.verticalScrollerMargin||0) - total) / (this.cells.length - i));
			total += sizes[i] + this.options.handleWidth;
			if(i < this.cells.length -1 && total < innerWidth) {				
				this.cells[i].setStyle({width:(sizes[i] - (Prototype.Browser.IE?1:0)) + 'px'});
			}else{
				//this.log(innerWidth +':' + total + ':' + sizes[i]);
				// go back to previous total
				total -= sizes[i] + this.options.handleWidth;
				sizes[i] = Math.floor((innerWidth - (this.verticalScrollerMargin||0) - total) / (this.cells.length - i));
				this.cells[i].setStyle({width:sizes[i] + 'px'});
				// Make sure it did not go to the line
				if(Position.cumulativeOffset(this.cells[i])[1] > Position.cumulativeOffset(this.element)[1] + 10){					
					sizes[i] = innerWidth - total - 1  - (this.verticalScrollerMargin||0);
					this.cells[i].setStyle({width:sizes[i] + 'px'});
				}
				total += sizes[i] + this.options.handleWidth;
				if(i == this.cells.length - 1 && total < innerWidth - (this.verticalScrollerMargin||0)){
					sizes[i] = (innerWidth - (this.verticalScrollerMargin||0) - total);
					this.cells[i].setStyle({width:sizes[i] + 'px'});
				}
			}
		}
		this.currentSizes = sizes;
		this.log(this.currentSizes.join(','));
		this.currentInner = innerWidth;
		
		this.notify('headers_resize');
		
		//this.refreshBody();
		
	},
		
	initDraggable : function(handle){
		new Draggable(handle, {
			constraint:'horizontal',
			snap: function(x,y,draggable) {
				function constrain(n, lower, upper) {
					if (n > upper) return upper;
					else if (n < lower) return lower;
					else return n;
				}
				var newPosition = Element.cumulativeOffset(draggable.element).left;
				var delta = newPosition - draggable.originalLeft;
				if((x < 0&& -delta > draggable._initLeftCell - 10) || (delta < 0 && -delta > draggable._initLeftCell)){
					x = 0;
				}
				if(x > 0 && delta > draggable._initRightCell -10){
					x = 0;
				}
				return [constrain(x, -draggable._initLeftCell, draggable._initRightCell), y];
			},
			onDrag : function(draggable){
				var newPosition = Element.cumulativeOffset(draggable.element).left;
				var delta = newPosition - draggable.originalLeft;
				var newSizes = $A();
				for(var i=0;i<this.cells.length;i++){
					if(this.cells[i] == draggable.previous) {
						newSizes[i] = draggable._initLeftCell + delta;
					}else{
						newSizes[i] = this.currentSizes[i]  - (i==(this.cells.length-1)?delta:0);
					}
				}
				this.log(newSizes);
				draggable.element.setStyle({left:0});
				this.resizeHeaders(newSizes);
				if(draggable._ghost){
					if(draggable._ghost.getOffsetParent()){
						newPosition -= draggable._ghost.getOffsetParent().positionedOffset()[0] + 2;
					}
					draggable._ghost.setStyle({left:newPosition-(Prototype.Browser.IE?0:this.options.body.scrollLeft)});
				}
			}.bind(this),
			onStart : function(draggable){				
				draggable.element.addClassName('dragging');
				draggable.originalLeft = Element.cumulativeOffset(draggable.element).left;
				draggable.previous = draggable.element.previous();
				draggable.next = draggable.element.next();
				draggable._initLeftCell = draggable.previous.getWidth();
				draggable._initRightCell = draggable.next.getWidth();
				draggable._ghost = this.createResizeGhost();
				if(draggable._ghost){
					draggable._ghost.setStyle({left:draggable.originalLeft});
				}
			}.bind(this),
			onEnd : function(draggable){
				draggable.element.setStyle({left:0});
				draggable.element.removeClassName('dragging');
				if(draggable._ghost) draggable._ghost.remove();
				for(var i=0;i<this.cells.length;i++){
					if(this.cells[i] == draggable.previous) this.currentSizes[i] = draggable.previous.getWidth();
					if(this.cells[i] == draggable.next) this.currentSizes[i] = draggable.next.getWidth();
				}
				this.notify("drag_resize");
			}.bind(this)
			
		});
	},
	
	createResizeGhost : function(){
		if(!this.options.body) return null;
		var ghost = new Element('div', {className:'resizeGhost'});
		this.options.body.insert({before:ghost});
		ghost.setStyle({height: this.getInnerHeight(this.options.body) - ((this.options.body.scrollWidth > this.options.body.getWidth())?16:0), left:0});
		return ghost;
	},
	
	refreshBody : function(){
		var newSizes = this.getCurrentSizes();
		var useCSS3  = this.options.useCSS3;
		var sheet = this.createStyleSheet();
		
		if(Prototype.Browser.IE){
			this.options.body.select(this.options.bodyRowSelector).each(function(row){
				var cells = row.select(this.options.bodyCellSelector);
				for(var i=0; i<cells.length;i++){
					if(newSizes[i]) this.setGridCellWidth(cells[i], newSizes[i]);
				}
			}.bind(this) );					
			this.checkBodyScroll();
			return;
		}
		
		// ADD CSS3 RULE
		for(var i=0;i<newSizes.length;i++){
			if(useCSS3){
				var selector = "#"+this.options.body.id+" td:nth-child("+(i+1)+")";
			}else{
				var selector = "#"+this.options.body.id+" td.resizer_"+ (i);
			}
			var rule = "width:"+(newSizes[i] + (Prototype.Browser.IE?10:-6))+" !important;";
			
			this.addStyleRule(sheet, selector, rule);
			
			if(useCSS3){
				selector = "#"+this.options.body.id+" td:nth-child("+(i+1)+") .text_label";
			}else{
				selector = "#"+this.options.body.id+" td.resizer_"+ (i) + " .text_label";
			}
			rule = "width:"+(newSizes[i] - (Prototype.Browser.IE?0:this.options.headerData[i].leftPadding))+" !important;";
			this.addStyleRule(sheet, selector, rule);
		}

		this.checkBodyScroll();
	},
	
	addStyleRule : function(sheet, selector, rule){
		if(Prototype.Browser.IE){
			sheet.addRule(selector, rule);
		}else{
			sheet.insertRule(selector+"{"+rule+"}", sheet.length);
		}		
	},
	
	createStyleSheet : function(){
		if(Prototype.Browser.IE){
			return;
			if(!window.ajxp_resizer_sheet){
		        window.ajxp_resizer_sheet = document.createStyleSheet();		    
			}
			var sheet = window.ajxp_resizer_sheet;
	        // Remove previous rules
	        var rules = sheet.rules;
	        var len = rules.length;	
	        for (var i=len-1; i>=0; i--) {
	          sheet.removeRule(i);
	        }			
			
		}else{
			var cssTag = $('resizer_css');
			// Remove previous rules
			if(cssTag) cssTag.remove();
	        cssTag = new Element("style", {type:"text/css", id:"resizer_css"});
	        $$("head")[0].insert(cssTag);
	        var sheet = cssTag.sheet;		        
		}
		return sheet;		
	},
	
	removeStyleSheet : function(){
		if(Prototype.Browser.IE){
			return;
			if(window.ajxp_resizer_sheet){
		        // Remove previous rules
		        var sheet = window.ajxp_resizer_sheet;
		        var rules = sheet.rules;
		        var len = rules.length;	
		        for (var i=len-1; i>=0; i--) {
		          sheet.removeRule(i);
		        }			
			}			
		}else{
			var cssTag = $('resizer_css');
			if(cssTag) cssTag.remove();
		}		
	},
	
	detectCSS3 : function(){
		if(Prototype.Browser.IE){
			return false;
		}
		if(window.ajxp_resizer_csstest != undefined){
			return window.ajxp_resizer_csstest;
		}
		var sheet = this.createStyleSheet();
		var detected = false;
		try{
			this.addStyleRule(sheet, this.options.cellSelector + ":nth-child(1)", "color:rgb(0, 0, 15);");
			var test = this.element.down(this.options.cellSelector);
			if(test && test.getStyle('color') == "rgb(0, 0, 15)"){
				detected = true;
			}
			this.removeStyleSheet();
		}catch(e){}
		window.ajxp_resizer_csstest = detected;
		return detected;
	},
	
	setGridCellWidth : function(cell, width, labelPadding){
		var label = cell.down('.text_label');		
		if(!label) {			
			if(width) cell.setStyle({width:width + 'px'});
			return;
		}
		var computedWidth;
		if(width) computedWidth = parseInt(width);
		else {
			computedWidth = cell.getWidth() - 3;
		}
		
		/*
		Useless, we only pass in this function in IE
		var cellPaddLeft = cell.getStyle('paddingLeft') || 0;
		var cellPaddRight = cell.getStyle('paddingRight') || 0;
		var labelPaddLeft = label.getStyle('paddingLeft') || 0;
		var labelPaddRight = label.getStyle('paddingRight') || 0;
		*/
		var siblingWidth = 1 + (labelPadding || 0);
		label.siblings().each(function(sib){
			siblingWidth += sib.getWidth();
		});
		//if(cell.getAttribute("ajxp_message_id"))console.log(computedWidth + ' // ' + (computedWidth - siblingWidth - parseInt(cellPaddLeft) - parseInt(cellPaddRight) - parseInt(labelPaddLeft) - parseInt(labelPaddRight)));
		label.setStyle({width:(computedWidth - siblingWidth) + 'px'});
		//console.log(width + " :: " + computedWidth +" :: "+ (computedWidth - siblingWidth - parseInt(cellPaddLeft) - parseInt(cellPaddRight) - parseInt(labelPaddLeft) - parseInt(labelPaddRight)));
		if(width) cell.setStyle({width:computedWidth + 'px'});	
	},	
	
	checkBodyScroll : function(){
		var body = this.options.body;		
		if( body.scrollHeight>body.getHeight()){
			this.verticalScrollerMargin=18;
		}else if( body.scrollHeight <= body.getHeight()){
			this.verticalScrollerMargin=0;
		}
		if(!this.options.bodyIsMaster) return;
		if(body.scrollWidth > body.getWidth()){			
			if(!this.scroller){
				this.element._origWidth = this.element.getWidth();
				this.scroller = new Element('div', {style:"overflow:hidden;"});
				this.scroller.setStyle({width:this.element._origWidth});
				$(this.element.parentNode).insert({top:this.scroller});
				this.scroller.insert(this.element);
				this.scroller.observer = function(){
					this.log(body.scrollWidth);
					this.scroller.scrollLeft = body.scrollLeft;				
				}.bind(this);
				body.observe("scroll", this.scroller.observer);
			}else{
				this.scroller.setStyle({width: this.mainSize});
			}
			this.element.setStyle({width:body.scrollWidth});
			this.verticalScrollerMargin=0;
			this.resizeHeaders();
		}else if(body.scrollWidth <= body.getWidth() && this.scroller){
			this.element.setStyle({width:this.element._origWidth});
			$(this.scroller.parentNode).insert({top:this.element});
			body.stopObserving("scroll", this.scroller.observer);
			this.scroller.remove();
			this.scroller = null;			
			this.resizeHeaders();
		}
	},
		
	
	getInnerWidth : function(){
		var leftWidth = parseInt(this.element.getStyle('borderLeftWidth')) || 0;
		var rightWidth = parseInt(this.element.getStyle('borderRightWidth')) || 0;
		return innerWidth = this.element.getWidth() - leftWidth - rightWidth ;
	},
	
	getInnerHeight : function(element){
		return innerWidth = element.getHeight() - (parseInt(element.getStyle('borderTopWidth')) || 0) - (parseInt(element.getStyle('borderBottomWidth')) || 0);
	},		
	
	log : function(message){
		return;
		if(window.console){
			console.log(message);
		}else if($('info_panel')){			
			$('info_panel').insert('<div>'+message+'</div>');
			$('info_panel').scrollTop = $('info_panel').scrollHeight;
		}
	}	

});