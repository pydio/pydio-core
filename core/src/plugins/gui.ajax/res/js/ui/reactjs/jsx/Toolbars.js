(function(global) {

    var MFB = React.createClass({

        getDefaultProps: function(){
            return {
                toolbarGroups: ['mfb'],
                highlight: 'upload'
            };
        },

        getInitialState: function(){
            return {actions: []};
        },

        actionsChange: function(){
            let controller = global.pydio.Controller;
            let actions = controller.getContextActions('genericContext', null, this.props.toolbarGroups);
            this.setState({actions: actions});
        },
        
        componentDidMount: function(){
            this._listener = this.actionsChange.bind(this);
            global.pydio.observe("context_changed", this._listener);
        },
        
        componentWillUnmount: function(){
            global.pydio.stopObserving("context_changed", this._listener);
        },

        close: function(e){
            this.refs.menu.toggleMenu(e);
        },

        render: function(){

            if(!this.state.actions.length){
                return null;
            }

            let children = [];
            let close = this.close.bind(this);
            let hl = null, hlName = this.props.highlight;
            this.state.actions.map(function(a){
                if(a.separator) return;
                let cb = function(e){
                    close(e);
                    a.callback();
                };
                if(hlName && a.action_id === hlName){
                    hl = <ReactMFB.ChildButton icon={a.icon_class} label={a.alt} onClick={cb}/>;
                } else{
                    children.push(<ReactMFB.ChildButton icon={a.icon_class} label={a.alt} onClick={cb}/>);
                }
            });
            if(hl) children.push(hl);

            return (
                <ReactMFB.Menu effect="slidein" position="tl" icon="mdi mdi-file" ref="menu">
                    <ReactMFB.MainButton iconResting="mdi mdi-plus" iconActive="mdi mdi-close"/>
                    {children}
                    <span className="hiddenOverlay"/>
                </ReactMFB.Menu>
            );
        }
    });


    var ButtonMenu = React.createClass({

        propTypes: {
            buttonTitle: React.PropTypes.string.isRequired,
            buttonClassName: React.PropTypes.string.isRequired,
            menuItems: React.PropTypes.array.isRequired,
            onMenuClicked: React.PropTypes.func.isRequired
        },

        getInitialState(){
            return {showMenu:false};
        },
        showMenu: function () {
            this.setState({showMenu: true});
        },
        hideMenu: function(event){
            if(!event){
                this.setState({showMenu: false});
                return;
            }
            let buttonElement = this.refs['menuButton'].getDOMNode();
            if(! (buttonElement.contains(event.target) || buttonElement === event.target )){
                this.setState({showMenu: false});
            }
        },
        componentDidMount: function(){
            this._observer = this.hideMenu.bind(this);
            document.addEventListener('click', this._observer, false);
        },
        componentWillUnmount: function(){
            document.removeEventListener('click', this._observer, false);
        },

        menuClicked:function(event, index, menuItem){
            this.props.onMenuClicked(menuItem);
            this.hideMenu();
        },
        render: function(){
            var menuAnchor = <ReactMUI.IconButton ref="menuButton" tooltip={this.props.buttonTitle} iconClassName={this.props.buttonClassName} onClick={this.showMenu}/>;
            if(this.state.showMenu) {
                var menuBox = <ReactMUI.Menu onItemClick={this.menuClicked} menuItems={this.props.menuItems}/>;
            }
            return (
                <span className="toolbars-button-menu">
                    {menuAnchor}
                    {menuBox}
                </span>
            );
        }

    });

    var ns = global.Toolbars || {};
    ns.MFB = MFB;
    ns.ButtonMenu = ButtonMenu;
    global.Toolbars = ns;

})(window);