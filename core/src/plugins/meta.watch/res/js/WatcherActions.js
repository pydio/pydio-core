/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */

(function(global){

    let pydio = global.pydio;
    let MessageHash = global.pydio.MessageHash;

    class Callbacks{

        static toggleWatch(manager, args){

            if(args){

                var node = pydio.getUserSelection().getUniqueNode();
                var conn = new Connexion();
                conn.setParameters({
                    get_action: "toggle_watch",
                    watch_action : args,
                    file : node.getPath()
                });
                conn.onComplete = function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                };
                conn.sendAsync();
            }

        }
        
        

    }

    class Listeners {

        static dynamicBuilder(controller) {

            var n = pydio.getUserSelection().getUniqueNode();
            if(!n) return [];
            
            let builderMenuItems = [];
            var metaValue;
            if(n.getMetadata().get("meta_watched")){
                var metaValue = n.getMetadata().get("meta_watched");
            }
            builderMenuItems.push({
                name:MessageHash["meta.watch.11"],
                alt:MessageHash["meta.watch." + (n.isLeaf()?"12":"12b")],
                isDefault : (metaValue && metaValue == "META_WATCH_CHANGE"),
                image:ResourcesManager.resolveImageSource('meta.watch/ICON_SIZE/watch'+((metaValue && metaValue == "META_WATCH_CHANGE")?'':'_faded')+'.png', '',  22),
                callback:function(e){
                    this.apply('watch_change');
                }.bind(this)
            });
            builderMenuItems.push({
                name:MessageHash["meta.watch.9"],
                alt:MessageHash["meta.watch." + (n.isLeaf()?"10":"10b")],
                isDefault : (metaValue && metaValue == "META_WATCH_READ"),
                image:ResourcesManager.resolveImageSource('meta.watch/ICON_SIZE/watch'+((metaValue && metaValue == "META_WATCH_READ")?'':'_faded')+'.png', '',  22),
                callback:function(e){
                    this.apply('watch_read');
                }.bind(this)
            });
            builderMenuItems.push({
                name:MessageHash["meta.watch.13"],
                alt:MessageHash["meta.watch." + (n.isLeaf()?"14":"14b")],
                isDefault : (metaValue && metaValue == "META_WATCH_BOTH"),
                image:ResourcesManager.resolveImageSource('meta.watch/ICON_SIZE/watch'+((metaValue && metaValue == "META_WATCH_BOTH")?'':'_faded')+'.png', '',  22),
                callback:function(e){
                    this.apply('watch_both');
                }.bind(this)
            });
            if(metaValue){
                builderMenuItems.push({
                    separator:true
                });
                builderMenuItems.push({
                    name:MessageHash['meta.watch.3'],
                    alt:MessageHash["meta.watch." + (n.isLeaf()?"8":"4")],
                    isDefault : false,
                    image:ResourcesManager.resolveImageSource('meta.watch/ICON_SIZE/watch.png', '',  22),
                    callback:function(e){
                        this.apply('watch_stop');
                    }.bind(this)
                });
            }

            return builderMenuItems;

        }

    }

    global.WatcherActions = {
        Callbacks: Callbacks,
        Listeners:Listeners
    };

})(window)