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


(function() {
    var autoLink,
        __slice = [].slice;

    autoLink = function() {
        var k, linkAttributes, option, options, pattern, v;
        options = 1 <= arguments.length ? __slice.call(arguments, 0) : [];
        pattern = /(^|\s)((?:https?|ftp):\/\/[\-A-Z0-9+\u0026@#\/%?=()~_|!:,.;]*[\-A-Z0-9+\u0026@#\/%=~()_|])/gi;
        if (!(options.length > 0)) {
            return this.replace(pattern, "$1<a href='$2'>$2</a>");
        }
        option = options[0];
        linkAttributes = ((function() {
            var _results;
            _results = [];
            for (k in option) {
                if(option.hasOwnProperty(k)){
                    v = option[k];
                    if (k !== 'callback') {
                        _results.push(" " + k + "='" + v + "'");
                    }
                }
            }
            return _results;
        })()).join('');
        return this.replace(pattern, function(match, space, url) {
            var link;
            link = (typeof option.callback === "function" ? option.callback(url) : void 0) || ("<a href='" + url + "'" + linkAttributes + ">" + url + "</a>");
            return "" + space + link;
        });
    };

    String.prototype['autoLink'] = autoLink;

}).call(this);


Class.create("CommentsPanel", {

    loaderTimer: null,

    // Warning, method is called statically, there is no "this"
    loadInfoPanel : function(container, node){

        container.down("#comments_container");
        container.down("textarea").observe("focus", function(){
            ajaxplorer.disableAllKeyBindings();
        });
        container.down("textarea").observe("blur", function(){
            ajaxplorer.enableAllKeyBindings();
        });

        if(node.getMetadata().get("ajxp_has_comments_feed")){

            var timer = CommentsPanel.prototype.loaderTimer;
            if(timer) window.clearTimeout(timer);

            var loader = function(pe){

                try{
                    if(pe && ajaxplorer.getContextHolder().getSelectedNodes()[0] != node){
                        pe.stop();
                        return;
                    }
                }catch (e){
                    pe.stop();
                    return;
                }

                var conn = new Connexion();
                conn.setParameters({
                    file: node.getPath(),
                    get_action: "load_comments_feed",
                    sort_by:"date",
                    sort_dir: "asc"
                });
                conn.discrete = true;
                conn.onComplete = function(transport){
                    container.down("#comments_container").select('.comment_content').invoke("remove");
                    var feed = transport.responseJSON;
                    for(var i=0;i<feed.length;i++){
                        CommentsPanel.prototype.commentObjectToDOM($H(feed[i]), container, node, pe?true:false);
                    }
                    CommentsPanel.prototype.refreshScroller(container);
                    $("comments_container").scrollTop = 10000;
                };

                conn.sendAsync();

            };

            CommentsPanel.prototype.loaderTimer = window.setTimeout(function(){
                loader();
            }, 0.3);

            new PeriodicalExecuter(loader, 5);

        }

        var submitComment = function(){

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
                container.down('textarea').setValue("");
                CommentsPanel.prototype.refreshScroller(container);
                $("comments_container").scrollTop = 10000;
            };

            conn.sendAsync();

        }.bind(this);

        container.down("#comments_submit").observe("click", function(){
             submitComment();
        });

        container.down('textarea').observe("keydown", function(e){
            if(e.keyCode == Event.KEY_RETURN && e.ctrlKey){
                submitComment();
                return false;
            }
            return true;
        });

    },

    commentObjectToDOM: function(hash, container, node, skipAnim){

        var pFactory = new PreviewFactory();
        hash.set('content', hash.get('content').autoLink({target:'_blank'}));
        var tpl = new Template('<div class="comment_legend"><span class="icon-remove comment_delete"></span>#{author}, #{hdate}</div><div class="comment_text"><span class="comment_text_content">#{content}</span></div>');
        var el = new Element("div", {className:'comment_content'}).update(tpl.evaluate(hash._object));
        if(hash.get('rpath')){
            var link = pFactory.renderSimpleLink(hash.get('path'), hash.get('rpath'));
            link.addClassName('comment_file_path');
            link.writeAttribute('title', MessageHash['meta.comments.4'].replace('%s', hash.get('rpath')));
            el.down('.comment_text').insert(link);
        }
        // Fake file/folder detection
        var basename = getBaseName(hash.get('path'));
        if(basename && basename.indexOf('.') != -1){
            el.addClassName('comment_type_file');
        }else{
            el.addClassName('comment_type_folder');
        }
        if(!skipAnim) el.setStyle({opacity:0, display:'block'});
        container.down("#comments_container").insert(el);
        if(!skipAnim) new Effect.Appear(el, {duration:0.3});

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
                    CommentsPanel.prototype.commentObjectToDOM($H(feed[i]), container, node, true);
                }
                CommentsPanel.prototype.refreshScroller(container);
            };
            new Effect.Fade(el, {
                duration: 0.3,
                afterFinish:function(){
                    conn.sendAsync();
                }
            });
        });

    },

    refreshScroller:function(container){

        container.up('div[@ajxpClass="infoPanel"]').ajxpPaneObject.scrollbar.recalculateLayout();

    }

});