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

    const Comment = React.createClass({

        propTypes: {
            comment: React.PropTypes.object.isRequired,
            pydio : React.PropTypes.instanceOf(Pydio).isRequired,
            removeComment: React.PropTypes.func.isRequired
        },

        render: function(){
            const c = this.props.comment;
            const {comment, pydio, removeComment} = this.props;

            const contents = comment.content.split('<br>').map(function(part){
                return <div className="part">{part}</div>
            });
            let link;
            if(comment.rpath){
                link = (<div className="link"><a
                    title={pydio.MessageHash['meta.comments.4'].replace('%s', comment.rpath)}
                    onTouchTap={() => {pydio.goTo(comment.path) }}
                >{comment.rpath}</a></div>);
            }
            let deleteButton;
            if(pydio.user && (comment.author === pydio.user.id)){
                const remove = () => {this.props.removeComment(c)};
                deleteButton = <div className="delete-comment mdi mdi-close" onTouchTap={remove}/>;
            }
            return (
                <div key={comment.uuid} className="comment">
                    <div className="date">{comment.hdate}</div>
                    <div className="comment-line">
                        <div style={{paddingTop:2}}><PydioComponents.UserAvatar avatarSize={30} pydio={this.props.pydio} userId={comment.author} displayLabel={false}/></div>
                        <MaterialUI.Paper zDepth={0} style={{backgroundColor: '#F5F5F5'}} className="content">
                            {deleteButton}
                            {contents}
                            {link}
                        </MaterialUI.Paper>
                    </div>
                </div>
            );
        }

    });

    const Panel = React.createClass({

        getInitialState: function(){
            return {comments: [], value: '', history:[], historyCursor: -1};
        },

        componentDidMount: function(){
            this.start(this.props.node);
        },

        componentWillUnmount: function(){
            this.stop();
        },

        componentWillReceiveProps: function(nextProps){
            if(nextProps.node !== this.props.node){
                this.start(nextProps.node);
            }
        },

        componentDidUpdate: function(){
            this.refs.comments.scrollTop = 10000;
        },

        mqObserver: function(currentNode, event){
            const message = XMLUtils.XPathSelectSingleNode(event, "/tree/metacomments");
            if(!message) {
                return;
            }
            const metaEvent = message.getAttribute('event');
            const path  = message.getAttribute('path');
            const crtPath = currentNode.getPath();
            if(path.indexOf(crtPath) !== 0){
                return;
            }
            if(metaEvent === 'newcomment' && crtPath === currentNode.getPath()){
                const data = JSON.parse(message.firstChild.nodeValue);
                let comments = this.state.comments || [];
                let found = false;
                comments.forEach((el)=> {
                    if(el.uuid = data.uuid) found = true;
                });
                if(!found){
                    comments.push(data);
                    this.setState({comments: comments});
                }
            }else{
                this.loadComments(currentNode);
            }
        },

        start: function(node){
            this.stop();
            var configs = this.props.pydio.getPluginConfigs("mq");
            if(configs){
                this._mqObs = (event) => {this.mqObserver(node, event)};
                this.props.pydio.observe("server_message", this._mqObs);
            }else {
                this._pe = new PeriodicalExecuter(function () {
                    this.loadComments(node);
                }.bind(this), 5);
            }
            this.loadComments(node);
        },

        stop: function(){
            if(this._pe){
                this._pe.stop();
            }
            if(this._mqObs){
                this.props.pydio.stopObserving("server_message", this._mqObs);
                this._mqObs = null;
            }
        },

        loadComments: function(node){

            PydioApi.getClient().request({
                get_action: 'load_comments_feed',
                file: node.getPath(),
                sort_by: 'date',
                sort_dir: 'asc'
            }, function(transport){

                if(!this.isMounted() || node !== this.props.node) return;
                this.setState({comments: transport.responseJSON});

            }.bind(this), null, {discrete: true});
        },

        removeComment: function(comment){
            PydioApi.getClient().request({
                get_action:'delete_comment',
                file: this.props.node.getPath(),
                comment_data: JSON.stringify(comment)
            }, () => {this.loadComments(this.props.node)});
        },

        insertComment: function(){
            let value = this.refs.new_comment.getValue();
            if(!value) return;
            PydioApi.getClient().request({
                get_action: "post_comment",
                file: this.props.node.getPath(),
                content: value
            }, (transp) => {
                let hist = this.state.history;
                hist.unshift(value);
                this.setState({value: '', history: hist, historyCursor:-1});
                if(!this._mqObs){
                    this.loadComments(this.props.node);
                }else{
                    const comments = [...this.state.comments, transp.responseJSON];
                    this.setState({comments});
                }
            });
        },

        keyDown: function(event){
            if(event.key === 'Enter'){
                this.insertComment();
            }
            if(!this.state.value || this.state.historyCursor !== -1){
                if(event.key === 'ArrowUp'){
                    let crt = this.state.historyCursor;
                    if(this.state.history[crt + 1]){
                        this.setState({historyCursor:crt+1, value: this.state.history[crt + 1]});
                    }
                }else if(event.key === 'ArrowDown'){
                    let crt = this.state.historyCursor;
                    if(this.state.history[crt - 1]){
                        this.setState({historyCursor:crt-1, value: this.state.history[crt - 1]});
                    }
                }
            }
        },

        render: function(){

            const stateComments = this.state.comments || [];
            const comments = stateComments.map(function(c){
                return (
                    <Comment
                        key={c.uuid}
                        comment={c}
                        pydio={this.props.pydio}
                        removeComment={this.removeComment}
                    />
                );
            }.bind(this));

            return (
                <PydioWorkspaces.InfoPanelCard style={this.props.style} title={this.props.pydio.MessageHash['meta.comments.1']} icon="comment-outline" iconColor="#795548">
                    <div style={{maxHeight: 300, overflowY: 'auto', overflowX:'hidden'}} ref="comments" className="comments_feed">
                        {comments}
                    </div>
                    <MaterialUI.Divider/>
                    <div style={{backgroundColor: 'white'}}>
                        <MaterialUI.TextField
                            hintText={this.props.pydio.MessageHash['meta.comments.2']}
                            hintStyle={{whiteSpace:'nowrap'}}
                            multiLine={true}
                            value={this.state.value}
                            ref="new_comment"
                            onKeyDown={this.keyDown}
                            onChange={(event, newValue) => {this.setState({value: newValue, historyCursor:-1})}}
                            fullWidth={true}
                            underlineShow={false}
                        />
                    </div>
                </PydioWorkspaces.InfoPanelCard>
            );
        }

    });

    global.MetaComments = {
        Panel: Panel
    }

})(window)
