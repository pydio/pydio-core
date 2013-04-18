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
Class.create("LogoWidget", AjxpPane, {

    initialize : function($super, element, options){
        $super(element, options);
        var configs = ajaxplorer.getPluginConfigs("guidriver");

        if(configs.get("CUSTOM_TOP_TITLE")){
            this.titleDiv = new Element('div', {className : 'custom_top_title'}).update(configs.get("CUSTOM_TOP_TITLE"));
        }

        if(configs.get("CUSTOM_TOP_LOGO")){
            var url = window.ajxpServerAccessPath + "&get_action=get_global_binary_param&binary_id=" + configs.get("CUSTOM_TOP_LOGO");
            this.image  = new Image();
            this.image.src = url;
            this.image.onload = function(){
                this.resizeImage(configs, true);
            }.bind(this);
        }

    },

    resizeImage : function(configs, insert){

        var w = this.image.width;
        var h = this.image.height;
        var imgH, imgW;
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
        if(imgH > parseInt(this.htmlElement.getHeight())){
            var elPadding = parseInt(this.htmlElement.getStyle('paddingTop')) + (imgH - parseInt(this.htmlElement.getHeight()));
            this.htmlElement.setStyle({paddingTop: elPadding + 'px'});
        }
        var htHeight = parseInt(this.htmlElement.getHeight());

        if(!configs.get('SKIP_BY_LOGO')){
            this.htmlElement.setStyle({
                backgroundImage : 'url(' + window.ajxpResourcesFolder + '/images/white_by.png)',
                backgroundSize : '66px',
                backgroundPosition : (imgW+10) + 'px '+ (htHeight - 14) +'px'
            });
        }
        if(this.titleDiv){
            this.titleDiv.setStyle({
                position:'absolute',
                left : (imgW + 8) + 'px',
                top : (htHeight - 37) + 'px',
                fontSize : '19px'
            });
        }

        if(insert){
            this.htmlElement.insert(this.image);
            if(this.titleDiv){
                this.htmlElement.insert(this.titleDiv);
            }
        }

    }


});