(function(global){

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

        start: function(node){
            this.stop();
            this._pe = new PeriodicalExecuter(function(){
                this.loadComments(node);
            }.bind(this), 5);
            this._pe.execute();
        },

        stop: function(){
            if(this._pe){
                this._pe.stop();
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
            }, () => {
                let hist = this.state.history;
                hist.unshift(value);
                this.setState({value: '', history: hist, historyCursor:-1});
                this.loadComments(this.props.node);
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
                const remove = () => {this.removeComment(c)};
                const contents = c.content.split('<br>').map(function(part){
                    return <div className="part">{part}</div>
                });
                let link;
                if(c.rpath){
                    link = (<div className="link"><a
                        title={this.props.pydio.MessageHash['meta.comments.4'].replace('%s', c.rpath)}
                        onTouchTap={() => {this.props.pydio.goTo(c.path) }}
                    >{c.rpath}</a></div>);
                }
                return (
                    <div key={c.uuid} className="comment">
                        <PydioComponents.UserAvatar pydio={this.props.pydio} userId={c.author} displayLabel={false}/>
                        <div className="date">{c.hdate}</div>
                        <div className="content">{contents}</div>
                        {link}
                        <div className="delete-comment mdi mdi-close" onTouchTap={remove}/>
                    </div>
                );
            }.bind(this));
            return (
                <PydioDetailPanes.InfoPanelCard title={this.props.pydio.MessageHash['meta.comments.1']}>
                    <div style={{maxHeight: 300, overflowY: 'auto'}} ref="comments">
                        {comments}
                    </div>
                    <MaterialUI.Divider/>
                    <div>
                        <MaterialUI.TextField
                            hintText={this.props.pydio.MessageHash['meta.comments.2']}
                            hintStyle={{whiteSpace:'nowrap'}}
                            multiLine={true}
                            value={this.state.value}
                            ref="new_comment"
                            onKeyDown={this.keyDown}
                            onChange={(event, newValue) => {this.setState({value: newValue, historyCursor:-1})}}
                            style={{width: '100%'}}
                        />
                    </div>
                </PydioDetailPanes.InfoPanelCard>
            );
        }

    });

    global.MetaComments = {
        Panel: Panel
    }

})(window)