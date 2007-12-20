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
			pageOffset: 25,
			fade: false,
			zIndex: 100,
			beforeShow: e,
			beforeHide: e,
			beforeSelect: e
		}, arguments[0] || { });
		
		this.shim = new Element('iframe', {
			style: 'position:absolute;filter:progid:DXImageTransform.Microsoft.Alpha(opacity=0);display:none',
			src: 'javascript:false;',
			frameborder: 0
		});
		
		this.options.fade = this.options.fade && !Object.isUndefined(Effect);
		this.container = new Element('div', {className: this.options.className, style: 'display:none'});
		/*
		var list = new Element('ul');
		this.options.menuItems.each(function(item) {
			list.insert(
				new Element('li', {className: item.separator ? 'separator' : ''}).insert(
					item.separator 
						? '' 
						: Object.extend(new Element('a', {
							href: '#',
							title: item.name,
							className: (item.className || '') + (item.disabled ? ' disabled' : ' enabled')
						}), { _callback: item.callback })
						.observe('click', this.onClick.bind(this))
						.observe('contextmenu', Event.stop)
						.update(item.name)
				)
			)
		}.bind(this));
		*/
		$(document.body).observe('contextmenu', function(e){Event.stop(e);});
		$(document.body).insert(this.container.observe('contextmenu', Event.stop));		
		if (this.ie) { $(document.body).insert(this.shim) }
		
		document.observe('click', function(e) {
			if (this.container.visible() && !e.isRightClick()) {
				this.options.beforeHide(e);
				if (this.ie) this.shim.hide();
				this.container.hide();
			}
		}.bind(this));
		
		$$(this.options.selector).invoke('observe', Prototype.Browser.Opera ? 'click' : 'contextmenu', function(e){
			if (Prototype.Browser.Opera && !e.ctrlKey) {
				return;
			}
			this.show(e);
		}.bind(this));
	},
	
	observerFunction:function(e){
		if (Prototype.Browser.Opera && !e.ctrlKey) {
			return;
		}
		this.show(e);		
	},
	
	removeElements:function(selector){
		$$(selector).invoke('stopObserving', Prototype.Browser.Opera ? 'click' : 'contextmenu', this.observerFunction.bind(this));
	},
	
	addElements:function(selector){
		$$(selector).invoke('observe', Prototype.Browser.Opera ? 'click' : 'contextmenu',this.observerFunction.bind(this));		
	},
	
	refreshList: function() {
		if(this.container.select('ul').length) this.container.select('ul')[0].remove();
		var list = new Element('ul');
		this.options.menuItems.each(function(item) {
			list.insert(
				new Element('li', {className: item.separator ? 'separator' : ''}).insert(
					item.separator 
						? '' 
						: Object.extend(new Element('a', {
							href: '#',
							title: item.alt,
							className: (item.className || '') + (item.disabled ? ' disabled' : ' enabled'),
							style:''//'background-image:url('+item.image+');' 							
						}), { _callback: item.callback })
						.writeAttribute('onclick', 'return false;')
						.observe('click', this.onClick.bind(this))
						.observe('contextmenu', Event.stop)
						.update('<img src="'+item.image+'" border="0" height="16" width="16" align="absmiddle"> '+item.name)						
				)
			)
		}.bind(this));
		this.container.insert(list);
	},
	
	show: function(e) {
		//e.stop();
		this.options.beforeShow(e);
		this.refreshList();
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
		this.container.setStyle(elOff).setStyle({zIndex: this.options.zIndex});
		if (this.ie) { 
			this.shim.setStyle(Object.extend(Object.extend(elDim, elOff), {zIndex: this.options.zIndex - 1})).show();
		}
		this.options.fade ? Effect.Appear(this.container, {duration: 0.25}) : this.container.show();
		this.event = e;
	},
	onClick: function(e) {
		//e.stop();
		if (e.target._callback && !e.target.hasClassName('disabled')) {
			this.options.beforeSelect(e);
			if (this.ie) this.shim.hide();
			this.container.hide();
			e.target._callback(this.event);
		}		
	}
})