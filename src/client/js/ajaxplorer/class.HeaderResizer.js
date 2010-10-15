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
			handleWidth : 3,
			headerData : null
		}, options || { });
		if(this.options.headerData){
			this.generateHeader();
		}
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
		
		Event.observe(window, 'resize', function(e){
			this.checkBodyScroll();
			this.resizeHeaders();
			this.refreshBody();
		}.bind(this));
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
			console.log("INIT", initSizes);
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
		
	getCurrentSizes : function(){
		return this.currentSizes;
	},
	
	resize : function(size){
		this.mainSize = size;
		this.checkBodyScroll();
		this.resizeHeaders();
		if(this.timer){
			window.clearTimeout(this.timer);
		}
		this.timer = window.setTimeout(this.refreshBody.bind(this), 50);
	},
	
	resizeHeaders : function(sizes){
		
		//this.checkBodyScroll();
		
		var innerWidth = this.getInnerWidth();		
		if(!sizes && this.currentInner && innerWidth != this.currentInner){
			sizes = this.computePercentSizes(this.currentSizes, this.currentInner);						
		}
		//console.log("return");
		if(!sizes) return;
		this.log(innerWidth);
		var total = 0;
		for(var i=0;i<this.cells.length;i++){
			total += sizes[i] + this.options.handleWidth;
			if(i < this.cells.length -1 && total < innerWidth) {				
				this.cells[i].setStyle({width:(sizes[i] - (Prototype.Browser.IE?1:0)) + 'px'});
			}else{
				//this.log(innerWidth +':' + total + ':' + sizes[i]);
				// go back to previous total
				total -= sizes[i] + this.options.handleWidth;
				sizes[i] = Math.floor((innerWidth - total) / (this.cells.length - i));
				this.cells[i].setStyle({width:sizes[i] + 'px'});
				// Make sure it did not go to the line
				if(Position.cumulativeOffset(this.cells[i])[1] > Position.cumulativeOffset(this.element)[1] + 10){					
					sizes[i] = innerWidth - total - 1;
					this.cells[i].setStyle({width:sizes[i] + 'px'});
				}
				total += sizes[i] + this.options.handleWidth;
			}
		}
		this.currentSizes = sizes;
		this.log(this.currentSizes.join(','));
		this.currentInner = innerWidth;
		
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
				draggable.previous.setStyle({width:draggable._initLeftCell + delta});
				draggable.next.setStyle({width:draggable._initRightCell - delta});
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
		this.options.body.select(this.options.bodyRowSelector).each(function(row){
			var cells = row.select(this.options.bodyCellSelector);
			for(var i=0; i<cells.length;i++){
				if(newSizes[i]) this.setGridCellWidth(cells[i], newSizes[i]);
			}
		}.bind(this) );		
		this.checkBodyScroll();
	},
	
	setGridCellWidth : function(cell, width){
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
		
		var cellPaddLeft = cell.getStyle('paddingLeft') || 0;
		var cellPaddRight = cell.getStyle('paddingRight') || 0;
		var labelPaddLeft = label.getStyle('paddingLeft') || 0;
		var labelPaddRight = label.getStyle('paddingRight') || 0;
		var siblingWidth = 1;
		label.siblings().each(function(sib){
			siblingWidth += sib.getWidth();
		});
		//if(cell.getAttribute("ajxp_message_id"))console.log(computedWidth + ' // ' + (computedWidth - siblingWidth - parseInt(cellPaddLeft) - parseInt(cellPaddRight) - parseInt(labelPaddLeft) - parseInt(labelPaddRight)));
		label.setStyle({width:(computedWidth - siblingWidth - parseInt(cellPaddLeft) - parseInt(cellPaddRight) - parseInt(labelPaddLeft) - parseInt(labelPaddRight)) + 'px'});
		//console.log(width + " :: " + computedWidth +" :: "+ (computedWidth - siblingWidth - parseInt(cellPaddLeft) - parseInt(cellPaddRight) - parseInt(labelPaddLeft) - parseInt(labelPaddRight)));
		if(width) cell.setStyle({width:computedWidth + 'px'});	
	},	
	
	checkBodyScroll : function(){
		var body = this.options.body;		
		if( body.scrollHeight>body.getHeight()){
			this.element.setStyle({width:this.mainSize-20});
			this.element.reduced = true;
		}else if( body.scrollHeight <= body.getHeight()){
			this.element.setStyle({width:this.mainSize});
			this.element.reduced = null;
		}
		if(body.scrollWidth > body.getWidth() && !this.scroller){
			this.element._origWidth = this.element.getWidth();
			this.scroller = new Element('div', {style:"overflow:hidden;"});
			this.scroller.setStyle({width:this.element._origWidth});
			$(this.element.parentNode).insert({top:this.scroller});
			this.scroller.insert(this.element);
			this.element.setStyle({width:body.scrollWidth});
			this.scroller.observer = function(){
				//this.log(this.scroller.getWidth());
				this.scroller.scrollLeft = body.scrollLeft;				
			}.bind(this);
			body.observe("scroll", this.scroller.observer);
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
		return innerWidth = this.element.getWidth() - parseInt(this.element.getStyle('borderLeftWidth')) - parseInt(this.element.getStyle('borderRightWidth'));
	},
	
	getInnerHeight : function(element){
		return innerWidth = element.getHeight() - parseInt(element.getStyle('borderTopWidth')) - parseInt(element.getStyle('borderBottomWidth'));
	},		
	
	log : function(message){
		return;
		if(window.console){
			console.log(message);
		}else if($('mylogger')){			
			$('mylogger').insert('<div>'+message+'</div>');
			$('mylogger').scrollTop = $('mylogger').scrollHeight;
		}
	}	

});