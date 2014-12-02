/** 
 * @description		prototype.js based context menu
 * @author        Juriy Zaytsev; kangax [at] gmail [dot] com; http://thinkweb2.com/projects/prototype/
 * @version       0.6
 * @date          12/03/07
 * @requires      prototype.js 1.6
*/

if (Object.isUndefined(Proto)) { var Proto = { }; }

Proto.Menu = Class.create({
	initialize: function() {
		var e = Prototype.emptyFunction;
		this.ie = (Prototype.Browser.IE && navigator.userAgent.indexOf('MSIE 10') == -1);
		this.options = Object.extend({
			selector: '.contextmenu',
			className: 'protoMenu',
			mouseClick: 'right',
			anchor: 'mouse',
			pageOffset: 25,
			topOffset:0,
			leftOffset:0,
			submenuArrow:ajxpResourcesFolder+'/images/arrow_right.png',
			position:'bottom',
			menuTitle:'',
            detailedItems: false,
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
		
		this.subMenus = $A();
		this.shim = new Element('iframe', {
			style: 'position:absolute;filter:progid:DXImageTransform.Microsoft.Alpha(opacity=0);display:none',
			src: 'javascript:false;',
			frameborder: 0
		});
		if(this.options.createAnchor){
			this.createAnchor();		
		}else if(typeof(this.options.anchor) == "string" && this.options.anchor != 'mouse'){
			this.options.selector = '[id="'+this.options.anchor+'"]';			
		}else if(typeof(this.options.anchor) != "string"){
			this.options.selector = this.options.anchor; // OBJECT
		}
		//this.eventToObserve = ((this.options.mouseClick!='right' || Prototype.Browser.Opera)?'click':'contextmenu');
		this.eventToObserve = ((this.options.mouseClick!='right')?'click':'contextmenu');
		if(this.options.mouseClick == 'over'){
			this.eventToObserve = 'mouseover';
		}
		this.options.fade = this.options.fade && !Object.isUndefined(Effect);
		this.container = new Element('div', {className: this.options.className, style: 'display:none'});

        this.observerFunctionBound = this.observerFunction.bind(this);
        this.mouseoverFunctionBound = this.mouseoverFunction.bind(this);
        this.mouseoutFunctionBound = this.mouseoutFunction.bind(this);

		if(this.options.mouseClick == 'right'){			
			$(document.body).observe('contextmenu', function(e){Event.stop(e);});
			$(document.body).insert(this.container.observe('contextmenu', Event.stop));
		}else{
			$(document.body).insert(this.container);
		}		
		if (this.ie) { $(document.body).insert(this.shim); }
		if(this.eventToObserve == 'mouseover'){
			this.container.observe("mouseover", this.mouseoverFunctionBound );
			this.container.observe("mouseout",this.mouseoutFunctionBound );
		}		

		document.observe('click', function(e) {
			if (this.container.visible() && !e.isRightClick() &&
                !(this.options.mouseClick == 'over' && !Object.isString(this.options.anchor) && this.options.anchor.id && Event.findElement(e, "#"+this.options.anchor.id))
                ) {
				this.hide();
			}
		}.bind(this));
		
		this.addElements(this.options.selector);
	},
	
	destroy : function(){
		try{
			if(this.subMenus.length) this.subMenus.invoke("destroy");
			if(this.ie) this.shim.remove();
			this.container.remove();
			if(this.options.createAnchor && this.options.anchor){
				this.options.anchor.remove();
			}
		}catch(e){}
	},
	
	observerFunction:function(e){
		if (this.options.mouseClick == 'left' && Event.findElement(e, '.protomenu_selector') && Event.findElement(e, '.protomenu_selector').hasClassName('disabled')){
			return;
		}
		this.show(e);
	},
	
	mouseoverFunction:function(e){
		if(this.timer){
			window.clearTimeout(this.timer);
			this.timer = null;
		}
        if(this.options.parent && this.options.parent.timer){
            window.clearTimeout(this.options.parent.timer);
            this.options.parent.timer = null;
        }
    },
	
	mouseoutFunction:function(e){
		this.timer = window.setTimeout(function(){
			this.hide(e);
		}.bind(this), 300);
	},
	
	removeElements:function(selectorOrObject){
		if(typeof(selectorOrObject) == "string"){
			$$(selectorOrObject).invoke('removeClassName', 'protomenu_selector');
			$$(selectorOrObject).invoke('stopObserving', 
								this.eventToObserve,
								this.observerFunctionBound);
		}else{
			$(selectorOrObject).removeClassName('protomenu_selector');
			$(selectorOrObject).stopObserving(this.eventToObserve, this.observerFunctionBound);
		}
	},
	
	addElements:function(selectorOrObject){
		if(typeof(selectorOrObject) == "string"){
			$$(selectorOrObject).invoke('observe', this.eventToObserve, this.observerFunctionBound);
			$$(selectorOrObject).invoke('addClassName', 'protomenu_selector');
			if(this.eventToObserve == "mouseover"){
					$$(selectorOrObject).invoke('observe', 'mouseover', this.mouseoverFunctionBound);
					$$(selectorOrObject).invoke('observe', 'mouseout', this.mouseoutFunctionBound);
			}			
		}else{
			$(selectorOrObject).observe(this.eventToObserve, this.observerFunctionBound);
			$(selectorOrObject).addClassName('protomenu_selector');
			if(this.eventToObserve == "mouseover"){
				$(selectorOrObject).observe("mouseover", this.mouseoverFunctionBound);
				$(selectorOrObject).observe("mouseout", this.mouseoutFunctionBound);
			}
		}
	},
	
	refreshList: function() {
		//if(this.container.select('ul').length) this.container.select('ul')[0].remove();
		this.container.childElements().invoke('remove');
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
        var registeredKeys = $A();
		this.options.menuItems.each(function(item) {

            if(!item.separator){
                var key = item.name;
                if(registeredKeys.indexOf(key) !== -1) return;
                registeredKeys.push(key);
            }

			var newItem = new Element('li', {className: item.separator ? 'separator' : ''});

			if(item.moreActions){
				var actionsContainer = new Element('div', {
                    className:'menuActions moreActions',
                    style:'padding-top:4px;'
                });
				item.moreActions.each(function(action){
                    if(action.icon_class){
                        actionsContainer.insert(
                            new Element('span', {
                                title:action.name,
                                className:action.icon_class
                            }).observe('click', action.callback));
                    }else{
                        actionsContainer.insert(
                            new Element('a', {
                                title:action.name,
                                href:'#'
                            })
                                .writeAttribute('onclick', 'return false;')
                                .observe('click', action.callback)
                                .insert('<img src="'+action.image+'" width="16" height="16" border="0">')
                        );
                    }
				});
				newItem.insert(actionsContainer);
                newItem.setStyle({position:"relative"});
				actionsContainer.observe("mouseover", function(){newItem.select('a.enabled')[0].addClassName("hovered");});
				actionsContainer.observe("mouseout", function(){newItem.select('a.enabled')[0].removeClassName("hovered");});
			}
			if(item.subMenu){
				var arrowContainer = new Element('div', {
                    className:'menuActions' + (window.ajaxplorer.currentThemeUsesIconFonts?' icon-caret-right':''),
                    style:'padding-right:7px;'
                });
				arrowContainer.insert(new Element('img', {src:this.options.submenuArrow, width:6,height:10}));
				newItem.insert(arrowContainer);
                newItem.setStyle({position:"relative"});
			}
			if(item.action_id && ajaxplorer && ajaxplorer.getActionBar() && ajaxplorer.getActionBar().getActionByName(item.action_id)){
                var actionObject = ajaxplorer.getActionBar().getActionByName(item.action_id);
                if(this.options.detailedItems){
                    item.name = '<span class="menu_label">'+ actionObject.getKeyedText() + '</span>' + '<span class="menu_description">'+ actionObject.options.title + '</span>'
                }else{
                    item.name = actionObject.getKeyedText();
                }
                item.title = actionObject.options.title;
            }
            var img = '';
            if(item.icon_class && window.ajaxplorer.currentThemeUsesIconFonts){
                img = new Element('span', {
                    className:item.icon_class + ' ajxp_icon_span',
                    title:item.alt
                });
            }else if(item.image){
                if(!item.separator) img = new Element('img', {src:item.image,border:'0',height:16,width:16,align:'absmiddle'});
            }else if(item.pFactory && item.ajxpNode){
                img = item.pFactory.generateBasePreview(item.ajxpNode);
            }

            if(item.separator && item.menuTitle){
                newItem.insert(item.menuTitle);
                newItem.addClassName('menuTitle');
            }else{
			    newItem.insert(
                    Object.extend(new Element('a', {
                        href: '#',
                        title: item.alt,
                        id:(item.action_id?'action_instance_'+item.action_id:''),
                        className: (item.className?item.className+' ':'') + (item.disabled ? 'disabled' : 'enabled'),
                        style:(item.isDefault?'font-weight:bold':'')
                    }), { _callback: item.callback })
                    .writeAttribute('onclick', 'return false;')
                    .observe('click', this.onClick.bind(this))
                    .observe('contextmenu', Event.stop)
                    .update(Object.extend(img, {_callback:item.callback} ))
                    .insert(item.name)
				);
            }
            if(newItem.down('u')){
                newItem.down('u')._callback = item.callback;
            }
            if(item.overlay){
                if(newItem.down('img')) newItem.down('img').insert({after:Object.extend(new Element('img', {src:item.overlay, style:'position:relative;margin-left:-12px;top:5px;'}), {_callback:item.callback})});
                if(newItem.down('span')) newItem.down('span').insert({after:Object.extend(new Element('img', {src:item.overlay, style:'position:relative;margin-left:-12px;top:5px;'}), {_callback:item.callback})});
            }
			newItem._callback = item.callback;
			if(item.subMenu){
				if(!item.subMenuBeforeShow){
					item.subMenuBeforeShow = Prototype.emptyFunction;
				}
				if(!item.subMenuBeforeHide){
					item.subMenuBeforeHide = Prototype.emptyFunction;
				}
				newItem.subMenu = new Proto.Menu({
				  mouseClick:"over",
				  anchor: newItem, 
				  className: this.options.className,
				  topOffset : 0,
				  leftOffset : -1,		 
				  menuItems: item.subMenu,
				  fade:false,
				  zIndex:2010,
				  position:'right',
				  parent:this,
				  beforeShow:function(){
				  	var object = newItem.subMenu;
				  	if(object.options.parent && object.options.parent.subMenus){
				  		object.options.parent.subMenus.invoke('hide');
				  	}
				  	if(object.options.anchor){
				  		object.options.anchor.addClassName('menuAnchorSelected');
				  	}
				  	window.setTimeout(function(){item.subMenuBeforeShow(object);},0);
				  },
				  beforeHide:function(){
				  	var object = newItem.subMenu;
				  	if(object.options.anchor){
				  		object.options.anchor.removeClassName('menuAnchorSelected');
				  	}
				  	window.setTimeout(function(){item.subMenuBeforeHide(object);},0);				  	
				  }
				});
				window.setTimeout(function(){item.subMenuBeforeShow(newItem.subMenu);},0);
				this.subMenus.push(newItem.subMenu);
			}			
			list.insert(newItem);
            if(item.pFactory && item.ajxpNode && img){
                newItem.IMAGE_ELEMENT = img;
                item.pFactory.enrichBasePreview(item.ajxpNode, newItem);
            }
		}.bind(this));
		this.container.insert(list);
        // Clean separators
        list.select("li.separator").each(function(sep){
            var next = sep.next("li");
            var prev = sep.previous("li");
            if(!prev || prev.hasClassName('separator') || prev.hasClassName('menuTitle')) {
                sep.remove();
                return;
            }
            if(!next || next.hasClassName('separator') || prev.hasClassName('menuTitle')) sep.remove();
        });
	},
	
	show: function(e) {
		//e.stop();
	  	if(this.options.parent && this.options.parent.subMenus){
	  		this.options.parent.subMenus.map(function(el){
	  			if(el != this) el.hide();
	  		}.bind(this)) ;
	  	}		
		this.options.beforeShow(e);
		this.refreshList();	
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
            this.checkHeight(elOff.top);
            this.correctWindowClipping(this.container, elOff, elDim);
			Effect.Appear(this.container, {
				duration: this.options.fadeTime || 0.15, 
				afterFinish : function(e){
					this.checkHeight(elOff.top);
                    this.correctWindowClipping(this.container, elOff, elDim);
				}.bind(this)
			});
		}else{
			this.container.show();
			this.checkHeight(elOff.top);
            this.correctWindowClipping(this.container, elOff, elDim);
		}
		this.event = e;
        window.setTimeout(function(){
            if(!this.container.select('li').length) this.hide();
        }.bind(this), 150);

	},

    correctWindowClipping: function(container, position, dim){
        var viewPort = document.viewport.getDimensions();
        if(parseInt(position.left) + dim.width > viewPort.width ){
            position.left = parseInt(position.left) + (viewPort.width - (parseInt(position.left) + dim.width) - 5);
            container.setStyle({left:position.left + "px"});
        }
    },


	checkHeight : function(offsetTop){
        offsetTop = parseInt(offsetTop);
		if(this.options.anchor == 'mouse') return;
		var vpHeight = getViewPortHeight()-10;
        if(this.options.menuMaxHeight){
            vpHeight = Math.min(this.options.menuMaxHeight, vpHeight);
        }else if(this.options.menuFitHeight){
            vpHeight = getViewPortHeight();
        }
		var vpOff = document.viewport.getScrollOffsets();
		var elDim = this.container.getDimensions();
		var y = parseInt(offsetTop);
		if((y - vpOff.top + elDim.height) >= vpHeight || this.options.menuFitHeight){
			this.container.setStyle({height:(vpHeight-(y - vpOff.top))+'px',overflowY:'scroll'}); 
			if(!this.containerShrinked) this.container.setStyle({width:elDim.width+16+'px'});
			this.containerShrinked = true;
        }else{
            this.container.setStyle({height:'auto', overflowY:'hidden'});
        }
        attachMobileScroll(this.container, "vertical");
    },
	
	computeMouseOffset: function(e){
		var x = Event.pointer(e).x,
			y = Event.pointer(e).y,
			vpDim = document.viewport.getDimensions(),
			vpOff = document.viewport.getScrollOffsets(),
			elDim = this.container.getDimensions();
			return {
				left: ((x + elDim.width + this.options.pageOffset) > vpDim.width 
					? (vpDim.width - elDim.width - this.options.pageOffset) : x) + 'px',
				top: ((y - vpOff.top + elDim.height) > vpDim.height && (y - vpOff.top) > elDim.height 
					? (y - elDim.height) : y) + 'px'
			};
	},
	
	computeAnchorOffset: function(){
		//if(this.anchorOffset) return this.anchorOffset;
		var anchorPosition = Position.cumulativeOffset($(this.options.anchor));
		var anchorScroll = this.options.anchor.cumulativeScrollOffset();
		var topPos, leftPos;

		if(this.options.position == 'bottom' || this.options.position == 'bottom right' || this.options.position == 'bottom middle'){
			topPos = anchorPosition[1] + $(this.options.anchor).getHeight() + this.options.topOffset - anchorScroll[1];
			leftPos = anchorPosition[0] + this.options.leftOffset - anchorScroll[0];
			if(this.options.position == 'bottom right'){
				leftPos = anchorPosition[0] + $(this.options.anchor).getWidth() + this.options.leftOffset - anchorScroll[0];
				leftPos -= this.container.getWidth();
			}else if(this.options.position == 'bottom middle'){
				leftPos = anchorPosition[0] + $(this.options.anchor).getWidth()/2 + this.options.leftOffset - anchorScroll[0];
				leftPos -= this.container.getWidth()/2;
			}
		}else if(this.options.position == 'right'){
			var vpDim = document.viewport.getDimensions();
			topPos = anchorPosition[1] + this.options.topOffset - anchorScroll[1];
			leftPos = anchorPosition[0] + $(this.options.anchor).getWidth() + this.options.leftOffset - anchorScroll[0];
			if(leftPos + this.container.getDimensions().width > vpDim.width){
				leftPos = anchorPosition[0] - (this.container.getDimensions().width);
			}
		}
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
		this.options.selector = this.options.anchor;
	},
	
	onClick: function(e) {
        var target = e.target;
        if(!e.target._callback && e.target.up('li') && e.target.up('li')._callback){
            target = e.target.up('li');
        }
        if (target._callback && !target.hasClassName('disabled')) {
            this.options.beforeSelect(e);
			if(this.options.anchor && typeof(this.options.anchor)!="string"){
				this.options.anchor.removeClassName('menuAnchorSelected');
			}
			if (this.ie) this.shim.hide();
			this.container.hide();
			target._callback(this.event);
		}		
	},
	
	hide : function(e){
		this.options.beforeHide(e);
		if (this.ie) this.shim.hide();
		this.container.setStyle({height:'auto', overflowY:'hidden'});
		this.container.hide();
		if(this.subMenus.length){
			// Re-created always on the fly
			$A(this.subMenus).invoke("destroy");
			this.subMenus = $A();
		}
	}
});
