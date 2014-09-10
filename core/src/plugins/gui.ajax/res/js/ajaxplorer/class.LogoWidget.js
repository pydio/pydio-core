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
Class.create("LogoWidget", AjxpPane, {

    initialize : function($super, element, options){
        $super(element, options);
        var configs = ajaxplorer.getPluginConfigs("guidriver");
        this.updateConfig(configs);
        if(options.link){
            var linkTitle;
            if(options.linkTitle){
                if(MessageHash[options.linkTitle]) linkTitle = MessageHash[options.linkTitle];
                else linkTitle = options.linkTitle;
            }
            var clickObs = function(){
                if(options.link.startsWith('triggerRepositoryChange:')){
                    ajaxplorer.triggerRepositoryChange(options.link.replace('triggerRepositoryChange:',''));
                }else{
                    if(options.linkTarget && options.linkTarget == 'new'){
                        window.open(options.link);
                    }else{
                        document.location.href = options.link;
                    }
                }
            };
            var observeElement;
            if(this.image){
                observeElement = this.image;
            }else if(this.titleDiv){
                observeElement = this.titleDiv;
            }else{
                var clickable = new Element('div', {title:linkTitle});
                clickable.setStyle({
                    cursor:'pointer',
                    height:element.getHeight() + 'px',
                    position:'absolute',
                    top:0,
                    left:0,
                    width:'180px'
                });
                element.insert({top:clickable});
                observeElement = clickable;
            }
            if(linkTitle) observeElement.writeAttribute("title", linkTitle);
            observeElement.observe("click", clickObs);
            observeElement.addClassName("linked");

        }

    },

    updateConfig : function(configs){

        if(configs.get("CUSTOM_TOP_TITLE")){
            if(!this.titleDiv){
                this.titleDiv = new Element('div', {className : 'custom_top_title'}).update(configs.get("CUSTOM_TOP_TITLE"));
                this.htmlElement.insert(this.titleDiv);
            }else{
                this.titleDiv.update(configs.get("CUSTOM_TOP_TITLE"));
            }
            if(!configs.get("CUSTOM_TOP_LOGO") || configs.get("CUSTOM_TOP_LOGO") == 'ajxp-remove-original'){
                if(this.image){
                    if(this.image.parentNode) this.image.remove();
                    this.image = null;
                }
                this.resizeImage(configs, true);
            }
        }else if(this.titleDiv){
            this.titleDiv.remove();
            this.titleDiv = null;
        }

        if(configs.get("CUSTOM_TOP_LOGO") && configs.get("CUSTOM_TOP_LOGO") != 'ajxp-remove-original'){
            var parameter = 'binary_id';
            if(configs.get("CUSTOM_TOP_LOGO_ISTMP")){
                parameter = 'tmp_file';
            }
            var url = window.ajxpServerAccessPath + "&get_action=get_global_binary_param&"+parameter+"=" + configs.get("CUSTOM_TOP_LOGO");
            if(configs.get("CUSTOM_TOP_LOGO").indexOf('plugins/') === 0){
                // It's not a binary but directly an image.
                url = configs.get("CUSTOM_TOP_LOGO");
            }
            if(!this.image){
                this.image  = new Image();
                this.image.addClassName('custom_logo_image');
                this.image.src = url;
                this.image.onload = function(){
                    this.resizeImage(configs, true);
                }.bind(this);
            }else if(this.image.src  != url){
                this.image.src = url;
                this.image.onload = function(){
                    this.resizeImage(configs, false);
                }.bind(this);
            }
        }else if(configs.get("CUSTOM_TOP_LOGO") == 'ajxp-remove-original' && this.image){
            if(this.image.parentNode) this.image.remove();
            this.image = null;
            this.htmlElement.setAttribute('style', '');
        }


    },

    resizeImage : function(configs, insert){

        var imgH, imgW;
        if(this.image){
            var w = this.image.width;
            var h = this.image.height;
            if(configs.get("CUSTOM_TOP_LOGO_H")){
                imgH = parseInt(configs.get("CUSTOM_TOP_LOGO_H")) || h;
                imgW = parseInt(imgH * w / h);
            }else if(configs.get("CUSTOM_TOP_LOGO_W")){
                imgW = parseInt(configs.get("CUSTOM_TOP_LOGO_W"));
                imgH = parseInt(imgW * h / w);
            }
            if(!imgW){
                imgW = w;
                imgH = h;
            }
            var imgTop = parseInt(configs.get("CUSTOM_TOP_LOGO_T")) || 0;
            var imgLeft = parseInt(configs.get("CUSTOM_TOP_LOGO_L")) || 0;
            this.image.setStyle({
                position    : 'absolute',
                height      : imgH + 'px',
                width       : imgW + 'px',
                top         : imgTop + 'px',
                left        : imgLeft + 'px'
            });
        }else{
            imgW = -3;
            imgH = 0;
        }
        // Reset height
        this.htmlElement.setStyle({paddingTop:(ajxpBootstrap.parameters.get("theme") == 'orbit' ? 0: '9px')});
        if(imgH > parseInt(this.htmlElement.getHeight())){
            var elPadding = parseInt(this.htmlElement.getStyle('paddingTop')) + (imgH - parseInt(this.htmlElement.getHeight()));
            this.htmlElement.setStyle({paddingTop: elPadding + 'px'});
        }
        var htHeight = parseInt(this.htmlElement.getHeight());

        if(!configs.get('SKIP_BY_LOGO')){
            this.htmlElement.setStyle({
                backgroundImage : 'url(' + window.ajxpResourcesFolder + '/images/white_by.png)',
                backgroundSize : '66px',
                backgroundPosition : (imgW+8) + 'px '+ (htHeight - 16) +'px'
            });
        }
        if(this.titleDiv){
            this.titleDiv.setStyle({
                position:'absolute',
                left : (imgW + 8) + 'px',
                top : (htHeight - 39) + 'px',
                fontSize : '19px'
            });
        }

        if(!(this.htmlElement.down('img.custom_logo_image'))){
            this.htmlElement.insert(this.image);
        }

        if(this.htmlElement.down('.linked')){
            this.htmlElement.down('.linked').setStyle({height:htHeight+'px'});
        }


    }


});