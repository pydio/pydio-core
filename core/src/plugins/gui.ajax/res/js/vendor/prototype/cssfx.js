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


Effect.CSS_SUPPORTED = Modernizr.csstransitions && Modernizr.cssanimations;

Effect.CSS_ANIMATE = function(effectName, element, options){

    var className;
    var originalMethod;
    var endStyle = {};
    if(!options) options = {};
    ["webkitAnimationEnd", "mozAnimationEnd", "oAnimationEnd", "animationEnd", "transitionend", "animationend", "oanimationend", "mozanimationend"].map(
        function(event){
            element.stopObserving(event);
        });
    switch (effectName){
        case "RowFade":
            className = 'quick bounceOutLeft';
            originalMethod = 'Fade';
            break;
        case "RowAppear":
            className = 'quick fadeInLeft';
            originalMethod = 'Appear';
            break;
        case "ErrorShake":
            className = 'shake';
            originalMethod = 'Shake';
            break;
        case "MessageAppear":
            element.removeClassName('fadeOutDownBig');
            className = 'fadeInUpBig';
            endStyle = {opacity: 1};
            originalMethod ='Appear';
            element.setOpacity(0);
            element.show();
            break;
        case "MessageFade":
            className = 'long fadeOutDownBig';
            endStyle = {opacity: 0};
            originalMethod ='Appear';
            if(!options.afterFinish){
                options.afterFinish = function(){
                    element.hide();
                };
            }
            break;
        case "MenuAppear":
            className = 'super-quick fadeIn';
            endStyle = {opacity: 1};
            originalMethod ='Appear';
            break;
    }

    if(Effect.CSS_SUPPORTED){

        ["webkitAnimationEnd", "mozAnimationEnd", "oAnimationEnd", "animationEnd", "transitionend", "animationend", "oanimationend", "mozanimationend"].map(
            function(event){
                element.observeOnce(event, function(){
                    ('animated ' + className).split(" ").map(function(cName){
                        element.removeClassName(cName);
                    });
                    if(endStyle) element.setStyle(endStyle);
                    if(options && options.afterFinish) options.afterFinish();
                });

            }
        );
        element.addClassName('animated ' + className);

    }else{

        new Effect[originalMethod](element, options);

    }

};


/**
 * Migrating original Scriptaculous effects to CSS3-based effects when possible
 * @param element
 * @param options Same options
 * @constructor
 */
Effect.RowFade = function(element, options){ Effect.CSS_ANIMATE("RowFade", element, options);};
Effect.RowAppear = function(element, options){ Effect.CSS_ANIMATE("RowAppear", element, options); };
Effect.MessageFade = function(element, options){ Effect.CSS_ANIMATE("MessageFade", element, options);};
Effect.MessageAppear = function(element, options){ Effect.CSS_ANIMATE("MessageAppear", element, options); };
Effect.MenuAppear = function(element, options){ Effect.CSS_ANIMATE("MenuAppear", element, options); };
Effect.ErrorShake = function(element, options){ Effect.CSS_ANIMATE("ErrorShake", element, options); };