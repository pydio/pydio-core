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
 */

/**
 * Responsible for generating previews of nodes
 */
Class.create("PreviewFactory", {

    sequencialLoading : true,
    imagesHash : $H(),
    _crtImageIndex: 0,
    _thumbSize: 64,

    initialize : function(options){
        this.imagesHash = $H();
    },

    clear : function(){
        this.imagesHash = $H();
    },

    generateBasePreview: function(ajxpNode){
        return AbstractEditor.prototype.getPreview(ajxpNode);
    },

    setThumbSize : function(tSize){
        this._thumbSize = tSize;
    },

    enrichBasePreview: function(ajxpNode, mainObject){

        var editors = ajaxplorer.findEditorsForMime((ajxpNode.isLeaf()?ajxpNode.getAjxpMime():"mime_folder"), true);
		if(editors && editors.length)
		{
			this._crtImageIndex ++;
			var imgIndex = this._crtImageIndex;
			mainObject.IMAGE_ELEMENT.writeAttribute("data-is_loaded", "false");
			mainObject.IMAGE_ELEMENT.writeAttribute("id", "ajxp_image_"+imgIndex);
			var crtIndex = this._crtImageIndex;

			ajaxplorer.loadEditorResources(editors[0].resourcesManager);
			var editorClass = Class.getByName(editors[0].editorClass);
			if(editorClass){
				var oImageToLoad = {
					index:"ajxp_image_"+crtIndex,
					ajxpNode:ajxpNode,
					editorClass:editorClass,
					mainObject:mainObject
				};
                if(this.sequencialLoading){
                    this.imagesHash.set(oImageToLoad.index, oImageToLoad);
                }else{
                    this.loadImage(oImageToLoad);
                }
			}
		}

    },

    /**
   	 * Queue processor for thumbnail async loading
   	 */
   	loadNextImage: function(){
   		if(this.imagesHash && this.imagesHash.size())
   		{
   			if(this.loading) return;
   			var oImageToLoad = this.imagesHash.unset(this.imagesHash.keys()[0]);
   			oImageToLoad.PFacLoader = new Image();
            oImageToLoad.PFacLoader.onerror = this.loadNextImage.bind(this);
   			var loader = function(){
   				var img = oImageToLoad.mainObject.IMAGE_ELEMENT || $(oImageToLoad.index);
   				if(img == null || oImageToLoad.PFacLoader == null) return;
   				var newImg = oImageToLoad.editorClass.prototype.getPreview(oImageToLoad.ajxpNode);
   				newImg.setAttribute("data-is_loaded", "true");
   				if(img.parentNode) {
                    img.parentNode.replaceChild(newImg, img);
                }
   				oImageToLoad.mainObject.IMAGE_ELEMENT = newImg;
   				this.resizeThumbnail(newImg);
                oImageToLoad.PFacLoader = null;
   				this.loadNextImage();
   			}.bind(this);
            oImageToLoad.PFacLoader.src = oImageToLoad.editorClass.prototype.getThumbnailSource(oImageToLoad.ajxpNode);
   			if(oImageToLoad.PFacLoader.readyState && oImageToLoad.PFacLoader.readyState == "complete"){
   				loader();
   			}else{
   				oImageToLoad.PFacLoader.onload = loader;
   			}
   		}else{
   			//if(oImageToLoad.PFacLoader) oImageToLoad.PFacLoader = null;
   		}
   	},

    /**
     *
     * @param oImageToLoad
     */
    loadImage : function(oImageToLoad){

        var imageLoader = new Image();
        imageLoader.editorClass = oImageToLoad.editorClass;
        imageLoader.src = imageLoader.editorClass.prototype.getThumbnailSource(oImageToLoad.ajxpNode);
        var loader = function(){
            var img = oImageToLoad.mainObject.IMAGE_ELEMENT || $(oImageToLoad.index);
            if(img == null || imageLoader == null) return;
            var newImg = imageLoader.editorClass.prototype.getPreview(oImageToLoad.ajxpNode);
            newImg.setAttribute("data-is_loaded", "true");
            img.parentNode.replaceChild(newImg, img);
            oImageToLoad.mainObject.IMAGE_ELEMENT = newImg;
            this.resizeThumbnail(newImg);
            delete imageLoader;
        }.bind(this);
        if(imageLoader.readyState && imageLoader.readyState == "complete"){
            loader();
        }else{
            imageLoader.onload = loader;
        }

    },

    /**
   	 * Resize the thumbnails
   	 * @param element HTMLElement Optionnal, if empty all thumbnails are resized.
   	 */
   	resizeThumbnail: function(element){

        var thumbSize = this._thumbSize;
   		var defaultMargin = 5;
        var tW, tH, mT, mB;
        if(element.resizePreviewElement && element.getAttribute("data-is_loaded") == "true")
        {
            element.resizePreviewElement({width:thumbSize, height:thumbSize, margin:defaultMargin});
        }
        else
        {
            if(thumbSize >= 64)
            {
                tW = tH = 64;
                mT = parseInt((thumbSize - 64)/2) + defaultMargin;
                mB = thumbSize+(defaultMargin*2)-tH-mT-1;
            }
            else
            {
                tW = tH = thumbSize;
                mT = mB = defaultMargin;
            }
            element.setStyle({width:tW+'px', height:tH+'px', marginTop:mT+'px', marginBottom:mB+'px'});
        }

   	},

    renderSimpleLink: function(ajxpNodeOrPath, label){

        var p;
        if(typeof ajxpNodeOrPath == "string"){
            p = ajxpNodeOrPath;
        }else{
            p = ajxpNodeOrPath.getPath();
        }
        var link = new Element('span', {className:'simple_goto_link', 'data-ajaxplorerGoto':p}).update(label?label:p);
        link.observe("click", function(e){
            this.goTo(p);
        }.bind(ajaxplorer));
        return link;

    },

    renderUserLink: function(userId){

    }

});