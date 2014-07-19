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

Class.create("VisibilityToggler", AjxpPane, {

    /**
     * Constructor
     * @param $super klass Superclass reference
     * @param htmlElement HTMLElement Anchor of this pane
     * @param options Object Widget options
     */
    initialize : function($super, htmlElement, options){

        $super(htmlElement, options);
        var togId = options['widget_id'];
        var detectionId= options['detection_id'] ? options['detection_id'] : options['widget_id'];
        var updaterScroller = function(){
            try{
                if(htmlElement.up('[ajxpClass]').ajxpPaneObject.scrollbar){
                    htmlElement.up('[ajxpClass]').ajxpPaneObject.scrollbar.recalculateLayout();
                }
            }catch(e){}
        };
        updaterScroller();
        window.setTimeout(updaterScroller, 1500);
        htmlElement.observe("click", function(){
            if(!$(togId) || !$(detectionId)) return;
            if($(togId).ajxpPaneObject){
                $(togId).ajxpPaneObject.showElement(!$(detectionId).visible());
            }else{
                var show = !$(detectionId).visible();
                if(show) $(togId).show();
                else $(togId).hide();
            }
            htmlElement.removeClassName('simple-toggler-show').removeClassName('simple-toggler-hide');
            htmlElement.addClassName($(detectionId).visible()?'simple-toggler-hide':'simple-toggler-show');
            htmlElement.update($(detectionId).visible()?MessageHash[514]:MessageHash[513]);
            updaterScroller();
            window.setTimeout(updaterScroller, 1500);
        });

        this.parentElement = htmlElement.up();
        Droppables.add(this.parentElement, {
            hoverclass:'',
            accept:'ajxp_draggable',
            onHover:function(draggable, droppable, event)
            {
                if(!$(togId) || !$(detectionId)) return;
                if($(detectionId).visible()) return;
                if($(togId).ajxpPaneObject){
                    $(togId).ajxpPaneObject.showElement(!$(detectionId).visible());
                }else{
                    var show = !$(detectionId).visible();
                    if(show) $(togId).show();
                    else $(togId).hide();
                }
                htmlElement.removeClassName('simple-toggler-show').removeClassName('simple-toggler-hide');
                htmlElement.addClassName($(detectionId).visible()?'simple-toggler-hide':'simple-toggler-show');
                htmlElement.update($(detectionId).visible()?MessageHash[514]:MessageHash[513]);
                updaterScroller();
                window.setTimeout(updaterScroller, 1500);
            }.bind(this)
        });


    },

    destroy:function($super){
        Droppables.remove(this.parentElement);
        $super();
    }


});