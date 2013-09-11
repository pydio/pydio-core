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
Class.create("CommentsPanel", {

    // Warning, method is called statically, there is no "this"
    loadInfoPanel : function(container, node){

        console.log(container);

        container.down("#comments_container").update("Loading comments...");
        container.down("textarea").observe("focus", function(){
            ajaxplorer.disableAllKeyBindings();
        });
        container.down("textarea").observe("blur", function(){
            ajaxplorer.enableAllKeyBindings();
        });

        if(node.getMetadata().get("ajxp_has_comments_feed")){

            var conn = new Connexion();
            conn.setParameters({
                file: node.getPath(),
                get_action: "load_comments_feed"
            });
            conn.onComplete = function(transport){
                container.down("#comments_container").update('');
                var feed = transport.responseJSON;
                for(var i=0;i<feed.length;i++){
                    CommentsPanel.prototype.commentObjectToDOM($H(feed[i]), container, node);
                }
            };

            conn.sendAsync();

        }

        container.down("#comments_submit").observe("click", function(){

            if(!container.down('textarea').getValue()) {
                return;
            }
            var conn = new Connexion();
            conn.setParameters({
                file: node.getPath(),
                get_action: "post_comment",
                content: container.down('textarea').getValue()
            });
            conn.setMethod('POST');
            conn.onComplete = function(transport){
                CommentsPanel.prototype.commentObjectToDOM($H(transport.responseJSON), container, node);
            };

            conn.sendAsync();

        });

    },

    commentObjectToDOM: function(hash, container, node){

        var el = new Element("div").update("<div class='comment_content'><div class='comment_delete'>X</div>"+hash.get("content")+"</div><div class='comment_legend'>By "+hash.get("author")+" on "+hash.get("date")+"</div>");
        el.setStyle({opacity:0, display:'block'});
        container.down("#comments_container").insert(el);
        new Effect.Appear(el, {duration:0.3});
        if(hash.get("author") != ajaxplorer.user.id){
            el.down('.comment_delete').remove();
            return;
        }
        el.down('.comment_delete').observe("click", function(){

            var conn = new Connexion();
            conn.setParameters({
                file: node.getPath(),
                get_action: "delete_comment",
                comment_data: Object.toJSON(hash)
            });
            conn.setMethod('POST');
            conn.onComplete = function(transport){
                container.down("#comments_container").update('');
                var feed = transport.responseJSON;
                for(var i=0;i<feed.length;i++){
                    CommentsPanel.prototype.commentObjectToDOM($H(feed[i]), container, node);
                }
            };
            conn.sendAsync();
        });

    }

});