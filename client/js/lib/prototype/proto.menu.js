/** 
 * @description		prototype.js based context menu
 * @author        Juriy Zaytsev; kangax [at] gmail [dot] com; http://thinkweb2.com/projects/prototype/
 * @version       0.6
 * @date          12/03/07
 * @requires      prototype.js 1.6
*/

if (Object.isUndefined(Proto)) { var Proto = { } }

Proto.Menu = Class.create({
	initialize: function() {
		var e = Prototype.emptyFunction;
		this.ie = Prototype.Browser.IE;
		this.options = Object.extend({
			selector: '.contextmenu',
			className: 'protoMenu',
			mouseClick: 'right',
			anchor: 'mouse',
			pageOffset: 25,
			topOffset:0,
			leftOffset:0,
			menuTitle:'',
			fade: false,
			zIndex: 100,
			createAnchor:false,
			anchorContainer:null,
			anchorSrc:'',
			anchorTitle:'',
			anchorPosition:'last',
			beforeShow: e,
			beforeHide: e,
			beforeSelect: e,
			shadowOptions :	{
				distance: 4,
				angle: 130,
				opacity: 0.3,
				nestedShadows: 3,
				color: '#000000'
			}
		}, arguments[0] || { });
		
		
		this.shim = new Element('iframe', {
			style: 'position:absolute;filter:progid:DXImageTransform.Microsoft.Alpha(opacity=0);display:none',
			src: 'javascript:false;',
			frameborder: 0
		});
		if(this.options.createAnchor){
			this.createAnchor();		
		}else if(this.options.anchor != 'mouse'){
			this.options.selector = '[id="'+this.options.anchor+'"]';			
		}
		this.eventToObserve = ((this.options.mouseClick!='right' || Prototype.Browser.Opera)?'click':'contextmenu');
		this.options.fade = this.options.fade && !Object.isUndefined(Effect);
		this.container = new Element('div', {className: this.options.className, style: 'display:none'});
		if(this.options.mouseClick == 'right'){			
			$(document.body).observe('contextmenu', function(e){Event.stop(e);});
			$(document.body).insert(this.container.observe('contextmenu', Event.stop));
		}else{
			$(document.body).insert(this.container);
		}		
		if (this.ie) { $(document.body).insert(this.shim) }

		document.observe('click', function(e) {
			if (this.container.visible() && !e.isRightClick()) {
				this.options.beforeHide(e);
				if (this.ie) this.shim.hide();
				Shadower.deshadow(this.container);
				this.container.setStyle({height:'auto', overflowY:'hidden'});
				this.container.hide();
			}
		}.bind(this));
		
		$$(this.options.selector).invoke('observe', 
										 this.eventToObserve, 
										 this.observerFunction.bind(this));
	},
	
	observerFunction:function(e){
		if (this.options.mouseClick == 'right' && Prototype.Browser.Opera && !e.ctrlKey) return;
		this.show(e);		
	},
	
	removeElements:function(selector){
		$$(selector).invoke('stopObserving', 
							this.eventToObserve, 
							this.observerFunction.bind(this));
	},
	
	addElements:function(selectorOrObject){
		if(typeof(selectorOrObject) == "string"){
			$$(selectorOrObject).invoke('observe', 
								this.eventToObserve,
								this.observerFunction.bind(this));		
		}else{
			$(selectorOrObject).observe(this.eventToObserve, this.observerFunction.bind(this));
		}
	},
	
	refreshList: function() {
		if(this.container.select('ul').length) this.container.select('ul')[0].remove();
		var list = new Element('ul');
		if(this.options.menuTitle != ''){
			var text = this.options.menuTitle;
			list.insert(
				new Element('li', {
					text:text,				
					className:'menuTitle'
				}).update(text)
			);		
		}
		this.options.menuItems.each(function(item) {
			
			var newItem = new Element('li', {className: item.separator ? 'separator' : ''});
			
			if(item.moreActions){
				var actionsContainer = new Element('div', {className:'menuActions'});
				item.moreActions.each(function(action){
					actionsContainer.insert(
						new Element('a', {
							title:action.name,
							href:'#'
						})
						.writeAttribute('onclick', 'return false;')
						.observe('click', action.callback)
						.insert('<img src="'+action.image+'" width="16" height="16" border="0">')
					);
				});
				newItem.insert(actionsContainer);
			}
			
			newItem.insert(
					item.separator 
						? '' 
						: Object.extend(new Element('a', {
							href: '#',
							title: item.alt,
							className: (item.className || '') + (item.disabled ? ' disabled' : ' enabled'),
							style:(item.isDefault?'font-weight:bold':'')
						}), { _callback: item.callback })
						.writeAttribute('onclick', 'return false;')
						.observe('click', this.onClick.bind(this))
						.observe('contextmenu', Event.stop)
						.update('<img src="'+item.image+'" border="0" height="16" width="16" align="absmiddle"> '+ item.name)						
				);
			list.insert(newItem);
		}.bind(this));
		this.container.insert(list);
		try{
		Shadower.shadow(this.container,this.options.shadowOptions, true);
		}catch(e){}
	},
	
	show: function(e) {
		//e.stop();
		this.options.beforeShow(e);
		this.refreshList();	
		if(!this.options.menuItems.length) return;
		var elOff = {};
		var elDim = this.container.getDimensions();
		if(this.options.anchor == 'mouse'){
			elOff = this.computeMouseOffset(e);		
		}else{
			elOff = this.computeAnchorOffset();		
		}
		this.container.setStyle(elOff);		
		this.container.setStyle({zIndex: this.options.zIndex});
		if (this.ie) { 
			this.shim.setStyle(Object.extend(Object.extend(elDim, elOff), {zIndex: this.options.zIndex - 1})).show();
		}				
		if(this.options.fade){
			Effect.Appear(this.container, {
				duration: 0.25, 
				afterFinish : function(e){
					this.checkHeight(elOff.top);
					Shadower.showShadows(this.container, this.options.shadowOptions);
				}.bind(this)
			});
		}else{
			this.container.show();
			this.checkHeight(elOff.top);
			Shadower.showShadows(this.container, this.options.shadowOptions);			
		}
		this.event = e;
	},
	
	checkHeight : function(offsetTop){
		if(this.options.anchor == 'mouse') return;
		var vpHeight = getViewPortHeight();
		var vpOff = document.viewport.getScrollOffsets();
		var elDim = this.container.getDimensions();
		var y = parseInt(offsetTop);
		if((y - vpOff.top + elDim.height) > vpHeight){			
			this.container.setStyle({height:(vpHeight-(y - vpOff.top))+'px',overflowY:'scroll'}); 
			if(!this.containerShrinked) this.container.setStyle({width:elDim.width+16+'px'});
			this.containerShrinked = true;
		}else{
			this.container.setStyle({height:'auto', overflowY:'hidden'});
		}		
	},
	
	computeMouseOffset: function(e){
		var x = Event.pointer(e).x,
			y = Event.pointer(e).y,
			vpDim = document.viewport.getDimensions(),
			vpOff = document.viewport.getScrollOffsets(),
			elDim = this.container.getDimensions(),
			elOff = {
				left: ((x + elDim.width + this.options.pageOffset) > vpDim.width 
					? (vpDim.width - elDim.width - this.options.pageOffset) : x) + 'px',
				top: ((y - vpOff.top + elDim.height) > vpDim.height && (y - vpOff.top) > elDim.height 
					? (y - elDim.height) : y) + 'px'
			};
		return elOff;	
	},
	
	computeAnchorOffset: function(){
		if(this.anchorOffset) return this.anchorOffset;
		var anchorPosition = Position.cumulativeOffset($(this.options.anchor));
		var topPos = anchorPosition[1] + $(this.options.anchor).getHeight() + this.options.topOffset;
		var leftPos = anchorPosition[0] + this.options.leftOffset;
		this.anchorOffset = {top:topPos+'px', left:leftPos+'px'};
		return this.anchorOffset;
	},
	
	createAnchor:function(){
		if(!this.options.createAnchor || this.options.anchor == 'mouse') return;
		this.options.anchor = new Element('img', {
				id:this.options.anchor, 
				src:this.options.anchorSrc,
				alt:this.options.anchorTitle,
				align:'absmiddle'
			}).setStyle({cursor:'pointer'});
		this.options.anchorContainer.appendChild(this.options.anchor);
		this.options.selector = '[id="'+this.options.anchor.id+'"]';		
	},
	
	onClick: function(e) {
		//e.stop();
		if (e.target._callback && !e.target.hasClassName('disabled')) {
			this.options.beforeSelect(e);
			if (this.ie) this.shim.hide();
			Shadower.deshadow(this.container);
			this.container.hide();
			e.target._callback(this.event);
		}		
	}
});
