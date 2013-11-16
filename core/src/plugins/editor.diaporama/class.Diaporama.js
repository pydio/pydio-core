/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 * Description : The image gallery manager.
 */
Class.create("Diaporama", AbstractEditor, {

	fullscreenMode: false,
	_minZoom : 10,
	_maxZoom : 500,
	
	initialize: function($super, oFormObject, options)
	{
        options = Object.extend({
            floatingToolbar:true,
            replaceScroller:false,
            toolbarStyle: "icons_only diaporama_toolbar",
            actions : (window.ajxpMinisite || window.ajxpMobile) ? {} : {}/* : {
                'toggleSideBar' : '<a id="toggleButton"><img src="'+ajxpResourcesFolder+'/images/actions/22/view_left_close.png"  width="22" height="22" alt="" border="0"><br><span message_id="86"></span></a>'
            }*/
        }, options);
		$super(oFormObject, options);

        if(this.actions.get("toggleButton")){
            var diapoInfoPanel = oFormObject.down("#diaporamaMetadataContainer");
            var diapoSplitter = oFormObject.down("#diaporamaSplitter");
            diapoSplitter.parentNode.setStyle({overflow:"hidden"});
            this.splitter = new Splitter(diapoSplitter, {
                direction:"vertical",
                "initA":250,
                minSize:0,
                fit:"height",
                fitParent:oFormObject.up(".dialogBox")
            });
            var replaceScroll = false;
            if(window.content_pane && window.content_pane.options.replaceScroller){
                replaceScroll = true;
            }
            this.infoPanel = new InfoPanel(diapoInfoPanel, {skipObservers:true,skipActions:true, replaceScroller:replaceScroll});
            var ipConfigs = ajaxplorer.getGuiComponentConfigs("InfoPanel");
            ipConfigs.each(function(el){
                this.infoPanel.parseComponentConfig(el.get("all"));
            }.bind(this));
        }else{
            //diapoInfoPanel.remove();
        }

		this.nextButton = this.actions.get("nextButton");
		this.previousButton = this.actions.get("prevButton");
		this.playButton = this.actions.get("playButton");
		this.stopButton = this.actions.get("stopButton");		
		this.actualSizeButton = this.actions.get('actualSizeButton');
		this.fitToScreenButton = this.actions.get('fitToScreenButton');
		
		this.imgTag = this.element.down('img[id="mainImage"]');
        this.imgTag.hide();
		this.imgBorder = this.element.down('div[id="imageBorder"]');
		this.imgContainer = this.element.down('div[id="imageContainer"]');
		this.zoomInput = this.actionBar.down('input[id="zoomValue"]');
		this.timeInput = this.actionBar.down('input[id="time"]');
        this.floatingToolbarAnchor = this.imgContainer;
        
        var id = this.imgContainer.parentNode.id;
        if(options.replaceScroller){
            this.scroller = new Element('div', {id:'ip_scroller_'+id, className:'scroller_track'});
            this.scroller.insert(new Element('div', {id:'ip_scrollbar_handle_'+id, className:'scroller_handle'}));
            this.imgContainer.insert({after:this.scroller});
            this.imgContainer.setStyle({overflow:"hidden"});
        }
        if(options.replaceScroller){
            this.scrollbar = new Control.ScrollBar(this.imgContainer,'ip_scroller_'+id, {fixed_scroll_distance:50});
        }
        this.imgContainer.observe("scroll", this.imageNavigator.bind(this));

		new SliderInput(this.zoomInput, {
			onSlide:function(value){
				this.setZoomValue(parseInt(value));
				this.zoomInput.value = parseInt(value) + ' %';
				this.resizeImage(false);				
			}.bind(this),
			range : $R(this._minZoom, this._maxZoom),
			increment : 1
		});
		new SliderInput(this.timeInput, {
			onSlide:function(value){				
				this.timeInput.value = parseInt(value) + ' s';
			}.bind(this),
			onChange:function(value){
				if(this.slideShowPlaying && this.pe){
					this.stop();
					this.play();
				}
			}.bind(this),
			range : $R(1, 15),
			increment : 1
		});
		
		var inputStyle = {
			backgroundImage:'url("'+ajxpResourcesFolder+'/images/locationBg.gif")',
			backgroundPosition:'left top',
			backgroundRepeat:'no-repeat'
		};
		this.zoomInput.setStyle(inputStyle);
		this.timeInput.setStyle(inputStyle);
		
		
		this.baseUrl = ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=preview_data_proxy&file=';
		this.nextButton.onclick = function(){
			this.next();
			this.updateButtons();
			return false;
		}.bind(this);
		this.previousButton.onclick = function(){
			this.previous();
			this.updateButtons();
			return false;
		}.bind(this);
		this.actualSizeButton.onclick = function(){
			this.setZoomValue(100);
			this.resizeImage(true);
			return false;
		}.bind(this);
		this.fitToScreenButton.onclick = function(){
			this.toggleFitToScreen();
			return false;
		}.bind(this);
		this.playButton.onclick = function(){
			this.play();
			this.updateButtons();
			return false;
		}.bind(this);
		this.stopButton.onclick = function(){
			this.stop();
			this.updateButtons();
			return false;
		}.bind(this);
        if(this.actions.get("toggleButton")){
            this.actions.get("fsButton").insert({before:this.actions.get("toggleButton")});
            this.actions.get("toggleButton").observe("click", function(e){
                Event.stop(e);
                this.splitter.toggleFolding();
            }.bind(this));
        }

		this.jsImage = new Image();
		this.imgBorder.hide();
		
		this.jsImage.onload = function(){
			this.jsImageLoading = false;
			this.imgTag.src = this.jsImage.src;
			this.resizeImage(true);
			var text = getBaseName(this.currentFile) + ' ('+this.sizes.get(this.currentFile).width+' X '+this.sizes.get(this.currentFile).height+')';
			this.updateTitle(text);
		}.bind(this);
		Event.observe(this.zoomInput, "keypress", function(e){
			if(e == null) e = window.event;
			if(e.keyCode == Event.KEY_RETURN || e.keyCode == Event.KEY_UP || e.keyCode == Event.KEY_DOWN){
				if(e.keyCode == Event.KEY_UP || e.keyCode == Event.KEY_DOWN)
				{
					var crtValue = parseInt(this.zoomInput.value);
					var value = (e.keyCode == Event.KEY_UP?(e.shiftKey?crtValue+10:crtValue+1):(e.shiftKey?crtValue-10:crtValue-1));
					this.zoomInput.value = value + ' %';
				}
				var newValue = parseInt(this.zoomInput.value);
				newValue = Math.max(this._minZoom, newValue);
				newValue = Math.min(this._maxZoom, newValue);
				this.setZoomValue(newValue);
				this.resizeImage(false);
				Event.stop(e);
			}
			return true;
		}.bind(this));
		this.timeInput.observe('change', function(e){			
			if(this.slideShowPlaying && this.pe){
				this.stop();
				this.play();
			}
		}.bind(this));
		
		// Observe +/- for zoom
		this.zoomObs = function(e){
			if(e.keyCode == 107 || e.keyCode == 109){
				var newValue = (e.keyCode == 107 ? parseInt(this.zoomInput.value) + 20 : parseInt(this.zoomInput.value) - 20);
				newValue = Math.max(this._minZoom, newValue);
				newValue = Math.min(this._maxZoom, newValue);
				this.setZoomValue(newValue);
				this.resizeImage(false);				
			}
		}.bind(this);
		Event.observe(document, "keydown", this.zoomObs);
		this.element.observe("editor:close", function(){
			Event.stopObserving(document, "keydown", this.zoomObs);
		}.bind(this));
		
		// Init preferences
		if(ajaxplorer && ajaxplorer.user){
			var autoFit = ajaxplorer.user.getPreference('diapo_autofit');
			if(autoFit && autoFit == "true"){
				this.autoFit = true;
				this.fitToScreenButton.select('img')[0].src = ajxpResourcesFolder + '/images/actions/22/zoom-fit-restore.png';
				this.fitToScreenButton.select('span')[0].update(MessageHash[326]);
			}
		}
		this.contentMainContainer = this.imgContainer ;
		this.element.observe("editor:close", function(){
			this.currentFile = null;
			this.items = null;
			this.imgTag.src = '';
			if(this.slideShowPlaying){
				this.stop();
			}
		}.bind(this) );
		
		this.element.observe("editor:enterFSend", function(e){
            if(this.splitter) this.splitter.options.fitParent = "window";
            this.resize();
        }.bind(this));
		this.element.observe("editor:exitFSend", function(e){
            if(this.splitter) this.splitter.options.fitParent = this.element.up(".dialogBox");
            this.resize();
        }.bind(this));
        if(this.editorOptions.context.elementName){
            fitHeightToBottom(this.imgContainer, $(this.editorOptions.context.elementName), 3);
        }else{
            fitHeightToBottom(this.contentMainContainer);
        }
		// Fix imgContainer
		if(Prototype.Browser.IE){
			this.IEorigWidth = this.element.getWidth();
			this.imgContainer.setStyle({width:this.IEorigWidth});
		}
        disableTextSelection(this.imgTag);
		if(window.ajxpMobile){
			this.setFullScreen();
			attachMobileScroll(this.imgContainer, "both");
		}
        if(this.splitter){
            this.splitter.options.onDrag = function(){
                this.resizeImage(false);
                this.actionBarPlacer();
            }.bind(this);
            if(window.ajxpMobile || !this.actions.get("toggleButton")) {
                window.setTimeout(function(){
                    if(!this.splitter.splitbar.hasClassName("folded")) this.splitter.fold();
                }.bind(this),2000);
            }
        }
	},
	
	resize : function(size){
		if(size){
			this.imgContainer.setStyle({height:size+'px'});
			if(this.IEorigWidth) this.imgContainer.setStyle({width:this.IEorigWidth});
		}else{
			if(this.fullScreenMode){
				fitHeightToBottom(this.imgContainer, this.element);
				if(this.IEorigWidth) this.imgContainer.setStyle({width:this.element.getWidth()});
			}else{
                if(this.editorOptions.context.elementName){
                    fitHeightToBottom(this.imgContainer, $(this.editorOptions.context.elementName), 3);
                }else{
                    fitHeightToBottom($(this.htmlElement));
                    fitHeightToBottom($(this.contentMainContainer), $(this.htmlElement));
                }
				if(this.IEorigWidth) this.imgContainer.setStyle({width:this.IEorigWidth});
			}
		}
		this.resizeImage(false);
        if(this.splitter){
            this.splitter.resize();
        }
		this.element.fire("editor:resize", size);
	},
	
	open : function($super, node)
	{
		$super(node);
        var userSelection = ajaxplorer.getUserSelection();
		var allNodes, sCurrentFile;
		if(userSelection.isUnique()){
			var allItems = userSelection.getContextNode().getChildren();
			sCurrentFile = node.getPath();
		}else{
			var allItems = userSelection.getSelectedNodes();
		}
		this.items = new Array();
		this.nodes = new Hash();
		this.sizes = new Hash();
        if($A(allItems).size() > 0){
            $A(allItems).each(function(node){
                var meta = node.getMetadata();
                if(meta.get('is_image')=='1'){
                    this.nodes.set(node.getPath(),node);
                    this.items.push(node.getPath());
                    this.sizes.set(node.getPath(),  {height:meta.get('image_height')||'n/a',
                        width:meta.get('image_width')||'n/a'});
                }
            }.bind(this));
        }else{
            var meta = node.getMetadata();
            if(meta.get('is_image')=='1'){
                this.nodes.set(node.getPath(),node);
                this.items.push(node.getPath());
                this.sizes.set(node.getPath(),  {height:meta.get('image_height')||'n/a',
                    width:meta.get('image_width')||'n/a'});
            }
        }

		if(!sCurrentFile && this.items.length) sCurrentFile = this.items[0];
		this.currentFile = sCurrentFile;
		
		this.setZoomValue(100);
		this.zoomInput.value = '100 %';	
		this.updateImage();
		this.updateButtons();
        if(this.splitter){
            this.splitter.resize();
        }
	},
		
	resizeImage : function(morph){	
		if(this.autoFit && morph){
			this.computeFitToScreenFactor();
		}
		var nPercent = this.getZoomValue();
		this.zoomInput.value = nPercent + ' %';
		var height = parseInt(nPercent*this.crtHeight / 100);	
		var width = parseInt(nPercent*this.crtWidth / 100);
		// Center vertically
		var marginTop=0;
		var marginLeft=0;
		this.containerDim = $(this.imgContainer).getDimensions();		
		if (height < this.containerDim.height){
			marginTop = parseInt((this.containerDim.height - height) / 2);
		}
		if (width < this.containerDim.width){
			marginLeft = parseInt((this.containerDim.width - width) / 2);
		}
		if(morph && this.imgBorder.visible()){
			new Effect.Morph(this.imgBorder,{
				style:{height:height+'px', width:width+'px',marginTop:marginTop+'px',marginLeft:marginLeft+'px'}, 
				duration:0.5,
				afterFinish : function(){
					this.imgTag.setStyle({height:height+'px', width:width+'px'});
                    if(this.imgTag.getStyle("opacity") == 0){
    					new Effect.Opacity(this.imgTag, {from:0,to:1.0, duration:0.3});
                    }
				}.bind(this)
			});
		}else{
			this.imgBorder.setStyle({height:height+'px', width:width+'px',marginTop:marginTop+'px',marginLeft:marginLeft+'px'});
			this.imgTag.setStyle({height:height+'px', width:width+'px'});
			if(!this.imgBorder.visible()){
				this.imgBorder.show();
				new Effect.Opacity(this.imgTag, {from:0,to:1.0, duration:0.3});
			}
		}
        if(this.scrollbar){
            this.scrollbar.track.setStyle({height:parseInt(this.imgContainer.getHeight())+"px"});
            this.scrollbar.recalculateLayout();
        }
        this.imageNavigator();
	},

    navigatorMove : function(navigator){
        // Empiric 50 value ...
        this.skipScrollObserver = true;
        var ratioX  = (this.imgContainer.getWidth()+50) / (navigator.containerWidth);
        this.imgContainer.scrollLeft = Math.min(parseInt(navigator.left * ratioX), this.imgContainer.scrollWidth);
        var ratioY  = (this.imgContainer.getHeight()+50) / navigator.containerHeight;
        this.imgContainer.scrollTop = Math.min(parseInt(navigator.top * ratioY), this.imgContainer.scrollHeight);
    },

    imageNavigator : function(navigator){
        var shadowCorrection = ($$('html')[0].hasClassName('boxshadow')?3:0);
        if(this.skipScrollObserver) return;
        var overlay = this.getNavigatorOverlay();
        if(!overlay) return;

        var nav = {};
        var img = this.imgBorder;
        var cont = this.imgContainer;
        nav.top = this.getIntegerStyle(img,"top") + this.getIntegerStyle(img,"marginTop") - cont.scrollTop;
        nav.left = this.getIntegerStyle(img,"left") + this.getIntegerStyle(img,"marginLeft") - cont.scrollLeft;
        nav.width = img.getWidth() - shadowCorrection;
        nav.height = img.getHeight() - shadowCorrection;
        nav.bottom = nav.top+nav.height;
        nav.right = nav.left+nav.width;
        nav.centerX = (nav.right-nav.left)/2;
        nav.centerY = (nav.bottom-nav.top)/2;
        nav.containerWidth = this.imgContainer.getWidth();
        nav.containerHeight = this.imgContainer.getHeight();

        var navigatorImg = overlay.next("img");
        var offset = navigatorImg.positionedOffset();
        var targetDim = navigatorImg.getDimensions();
        var ratioX = (targetDim.width) / (nav.width);
        var ratioY = (targetDim.height) / (nav.height);
        var realLeftOffset = Math.max(offset.left, navigatorImg.parentNode.positionedOffset().left);
        overlay.setStyle({
            top: (Math.max(-nav.top, 0) * ratioY + offset.top) + 'px',
            left: (Math.max(-nav.left,0) * ratioX + realLeftOffset) + 'px',
            width: (Math.min(nav.containerWidth*ratioX, targetDim.width-shadowCorrection)) + "px",
            height: (Math.min(nav.containerHeight*ratioY, targetDim.height-shadowCorrection)) + "px"
        });
    },

    getNavigatorOverlay : function(){
        if(!this.infoPanel) return;
        var ov = this.infoPanel.htmlElement.down("div.imagePreviewOverlay");
        if(ov && ov.draggableInitialized) {
            return ov;
        }
        var theImage = this.infoPanel.htmlElement.down("img");
        if(!theImage) return;
        if(!ov){
            ov = new Element('div',{className:"imagePreviewOverlay"}).setStyle({
                position:'absolute',
                cursor: 'move'
            });
            theImage.insert({before:ov});
        }
        if(!ov.draggableInitialized){
            theImage.stopObserving("mouseover");
            theImage.stopObserving("mouseout");
            theImage.stopObserving("click");
            ov.update("").setStyle({border :"1px solid red", display: "block",backgroundColor:"rgba(0,0,0,0.2)"});
            ov.draggableInitialized = new Draggable(ov, {
                onStart : function(){
                    theImage.up('div.infoPanelImagePreview').setStyle({marginTop:theImage.getStyle('margin-top')});
                    theImage.setStyle({marginTop:0});
                    this.skipScrollObserver = true;
                }.bind(this),
                onEnd : function(){
                    theImage.setStyle({marginTop:theImage.up('div.infoPanelImagePreview').getStyle('margin-top')});
                    theImage.up('div.infoPanelImagePreview').setStyle({marginTop:0});
                    this.skipScrollObserver = false;
                    this.imageNavigator();
                }.bind(this),
                onDrag:function(){
                    if(!theImage) return;
                    var offset = theImage.positionedOffset();
                    var dim = theImage.getDimensions();
                    var coord = {
                        top:parseInt(ov.getStyle("top"))-offset.top,
                        left:parseInt(ov.getStyle("left"))-offset.left,
                        containerWidth:ov.getWidth(),
                        containerHeight:ov.getHeight()
                    };
                    this.navigatorMove(coord);
                }.bind(this),
                snap:function(x,y,theDraggable){
                    if(!theImage) return;
                    var offset = theImage.positionedOffset();
                    var imageDim = theImage.getDimensions();
                    var objDim = theDraggable.element.getDimensions();
                    function constrain(n, lower, upper) {
                        if (n > upper) return upper;
                        else if (n < lower) return lower;
                        else return n;
                    }
                    return[
                        constrain(x, 0, imageDim.width-objDim.width) + offset.left,
                        constrain(y, 0, imageDim.height-objDim.height) + offset.top
                    ];

                }.bind(this)
            });
        }
        return ov;
    },

    getIntegerStyle : function(object, property){
        var val = parseInt(object.getStyle(property));
        if(!val) val = 0;
        return val;
    },

    updateInfoPanel:function(){
        if(!this.infoPanel) return;
        if(!this.currentFile || !this.nodes || !this.nodes.get(this.currentFile)) return;
        var node = this.nodes.get(this.currentFile);
        this.infoPanel.update(node);
    },
	
	updateImage : function(){

        var node = this.nodes.get(this.currentFile);
        if(node.getMetadata().get("image_dimensions_thumb")){
            var sizeLoader = new Image();
            var tmpThis = this;
            sizeLoader.onload = function(){
                node.getMetadata().set("image_width", this.width);
                node.getMetadata().set("image_height", this.height);
                node.getMetadata().set("image_dimensions_thumb", false);
                tmpThis.sizes.set(tmpThis.currentFile, {width:this.width, height: this.height});
                tmpThis.updateImage();
            };
            sizeLoader.src = this.baseUrl + encodeURIComponent(this.currentFile);
            return;
        }

        var dimObject = this.sizes.get(this.currentFile);
		this.crtHeight = dimObject.height;
		this.crtWidth = dimObject.width;
		if(this.crtWidth){
			this.crtRatio = this.crtHeight / this.crtWidth;
		}
		new Effect.Opacity(this.imgTag, {afterFinish : function(){
			this.jsImageLoading = true;
			this.jsImage.src  = this.baseUrl + encodeURIComponent(this.currentFile);
			if(!this.crtWidth && !this.crtHeight){
				this.crtWidth = this.imgTag.getWidth();
				this.crtHeight = this.imgTag.getHeight();
				this.crtRatio = this.crtHeight / this.crtWidth;
			}
            this.imgTag.show();
		}.bind(this), from:1.0,to:0, duration:0.3});

        this.updateInfoPanel();
	},

	setZoomValue : function(value){
		this.zoomValue = value;
	},
	
	getZoomValue : function(value){
		return this.zoomValue;
	},
	
	fitToScreen : function(){
		this.computeFitToScreenFactor();
		this.resizeImage(true);
	},
	
	computeFitToScreenFactor: function(){
		zoomFactor1 = parseInt(this.imgContainer.getHeight() / this.crtHeight * 100);
		zoomFactor2 = parseInt(this.imgContainer.getWidth() / this.crtWidth * 100);
		var zoomFactor = Math.min(zoomFactor1, zoomFactor2)-1;
		zoomFactor = Math.max(this._minZoom, zoomFactor);
		zoomFactor = Math.min(this._maxZoom, zoomFactor);		
		this.setZoomValue(zoomFactor);		
	},
	
	toggleFitToScreen:function(skipSave){
		var src = '';
		var id;
		if(this.autoFit){
			this.autoFit = false;
			src = 'zoom-fit-best';
			id = 325;
		}else{
			this.autoFit = true;
			src = 'zoom-fit-restore';
			id = 326;
			this.fitToScreen();
		}
		this.fitToScreenButton.select('img')[0].src = ajxpResourcesFolder + '/images/actions/22/'+src+'.png';
		this.fitToScreenButton.select('span')[0].update(MessageHash[id]);
		if(ajaxplorer && ajaxplorer.user && !skipSave){
			ajaxplorer.user.setPreference("diapo_autofit", (this.autoFit?'true':'false'));
			ajaxplorer.user.savePreferences();
		}
	},
		
	play: function(){
		if(!this.timeInput.value) this.timeInput.value = 3;
		this.pe = new PeriodicalExecuter(this.next.bind(this), parseInt(this.timeInput.value));
		this.slideShowPlaying = true;
        window.setTimeout(this.hideActionBar.bind(this), 3000);
	},

    hideActionBar: function(){
        new Effect.Fade(this.actionBar);
        this.element.observeOnce("mousemove", function(){
            this.actionBar.show();
        }.bind(this));
    },

	stop: function(){
		if(this.pe) this.pe.stop();
		this.slideShowPlaying = false;
	},
	
	next : function(){
		if(this.jsImageLoading){
			return;
		}
		if(this.currentFile != this.items.last())
		{
			this.currentFile = this.items[this.items.indexOf(this.currentFile)+1];
			this.updateImage();
		}
		else if(this.slideShowPlaying){
			this.currentFile = this.items[0];
			this.updateImage();
		}
	},
	
	previous : function(){
		if(this.currentFile != this.items.first())
		{
			this.currentFile = this.items[this.items.indexOf(this.currentFile)-1];
			this.updateImage();	
		}
	},
	
	updateButtons : function(){
		if(this.slideShowPlaying){
			this.previousButton.addClassName("disabled");
			this.nextButton.addClassName("disabled");
			this.playButton.addClassName("disabled");
			this.stopButton.removeClassName("disabled");
		}else{
			if(this.currentFile == this.items.first()) this.previousButton.addClassName("disabled");
			else this.previousButton.removeClassName("disabled");
			if(this.currentFile == this.items.last()) this.nextButton.addClassName("disabled");
			else this.nextButton.removeClassName("disabled");
			this.playButton.removeClassName("disabled");
			this.stopButton.addClassName("disabled");
		}
	},
	
    getSharedPreviewTemplate : function(node){

        return new Template('<img width="#{WIDTH}" height="#{HEIGHT}" src="#{DL_CT_LINK}">');

    },

	/**
	 * 
	 * @param ajxpNode AjxpNode
	 * @returns {___img1}
	 */
	getPreview : function(ajxpNode){
		var img = new Element('img', {
            src:Diaporama.prototype.getThumbnailSource(ajxpNode),
            className:'thumbnail_iconlike_shadow',
            align:"absmiddle"
        });
        if(!parseInt(ajxpNode.getMetadata().get("image_width"))){
            var imgObject = new Image();
            imgObject.onload = function(){
                img.DIMENSIONS_LOADING = false;
                ajxpNode.getMetadata().set("image_dimensions_thumb", true);
                ajxpNode.getMetadata().set("image_width", this.width);
                ajxpNode.getMetadata().set("image_height", this.height);
            }
            imgObject.onerror = function(){
                img.DIMENSIONS_LOADING = false;
            };
            img.DIMENSIONS_LOADING = true;
            imgObject.src = Diaporama.prototype.getThumbnailSource(ajxpNode);
        }
		var div = new Element('div');
		div.insert(img);
		div.resizePreviewElement = function(dimensionObject){
            var styleObj;
            if(!parseInt(ajxpNode.getMetadata().get("image_width"))){
                styleObj = fitRectangleToDimension({width:50,height:50}, dimensionObject);
                if(img.DIMENSIONS_LOADING) window.setTimeout(function(){ div.resizePreviewElement(dimensionObject); }, 1000);
            }else{
                var imgDim = {
                    width:parseInt(ajxpNode.getMetadata().get("image_width")),
                    height:parseInt(ajxpNode.getMetadata().get("image_height"))
                };
                styleObj = fitRectangleToDimension(imgDim, dimensionObject);
            }
			img.setStyle(styleObj);
			div.setStyle({
				height:styleObj.height, 
				width:styleObj.width, 
				/*position:'relative',*/
				display:'inline'
			});
            if($(div.parentNode)) $(div.parentNode).setStyle({position:"relative"});
		};
		img.observe("mouseover", function(event){
			var theImage = event.target;
			if(theImage.up('.thumbnail_selectable_cell')) return;
			if(!theImage.openBehaviour){
				var opener = new Element('div').update(MessageHash[411]);
				opener.setStyle({
					width:(styleObj?styleObj.width:''),
					display:'none', 
					position:'absolute', 
					color: 'white',
					backgroundColor: 'black',
					opacity: '0.6',
					fontWeight: 'bold',
					fontSize: '12px',
					textAlign: 'center',
					cursor: 'pointer'
				});
                opener.addClassName('imagePreviewOverlay');
				img.previewOpener = opener;
				theImage.insert({before:opener});
				theImage.setStyle({cursor:'pointer'});
				theImage.openBehaviour = true;
				theImage.observe("click", function(event){
					ajaxplorer.actionBar.fireAction('open_with');
				});
			}
            var off = theImage.positionedOffset();
            var realLeftOffset = Math.max(off.left, theImage.parentNode.positionedOffset().left);
			theImage.previewOpener.setStyle({
                display:'block',
                left: (realLeftOffset + 1) + 'px',
                width: (theImage.getWidth() - 2) + "px",
                top: (off.top + theImage.getHeight() - theImage.previewOpener.getHeight() -1 )  + "px"
            });
		});
		img.observe("mouseout", function(event){
			var theImage = event.target;
			if(theImage.up('.thumbnail_selectable_cell')) return;
			theImage.previewOpener.setStyle({display:'none'});
		});
		return div;
	},
	
	getThumbnailSource : function(ajxpNode){
        var repoString = "";
        if(ajaxplorer.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != ajaxplorer.repositoryId){
            repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
        }
		var source = ajxpServerAccessPath + repoString + "&get_action=preview_data_proxy&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
		if(ajxpNode.getParent()){
            var preview_seed = ajxpNode.getParent().getMetadata().get('preview_seed');
    		if(preview_seed){
    			source += "&rand="+preview_seed;
    		}
        }
		return source;
	}
	
});