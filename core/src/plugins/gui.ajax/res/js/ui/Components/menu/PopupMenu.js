(function(global){

    export default React.createClass({

        propTypes: {
            menuItems: React.PropTypes.array.isRequired,
            onMenuClicked: React.PropTypes.func.isRequired,
            onExternalClickCheckElements: React.PropTypes.func,
            className: React.PropTypes.string,
            style:React.PropTypes.object,
            onMenuClosed: React.PropTypes.func
        },

        getInitialState(){
            return {showMenu:false, menuItems:this.props.menuItems};
        },
        showMenu: function (style = null, menuItems = null) {
            this.setState({
                showMenu: true,
                style: style,
                menuItems:menuItems?menuItems:this.state.menuItems
            });
        },
        hideMenu: function(event){
            if(!event){
                this.setState({showMenu: false});
                if(this.props.onMenuClosed) this.props.onMenuClosed();
                return;
            }
            let hide = true;
            if(this.props.onExternalClickCheckElements){
                let elements = this.props.onExternalClickCheckElements();
                for(let i = 0; i < elements.length ; i ++){
                    if(elements[i].contains(event.target) || elements[i] === event.target ){
                        hide = false;
                        break;
                    }
                }
            }
            if(hide){
                this.setState({showMenu: false});
                if(this.props.onMenuClosed) this.props.onMenuClosed();
            }
        },
        componentDidMount: function(){
            this._observer = this.hideMenu;
        },
        componentWillUnmount: function(){
            document.removeEventListener('click', this._observer, false);
        },
        componentWillReceiveProps: function(nextProps){
            if(nextProps.menuItems){
                this.setState({menuItems:nextProps.menuItems});
            }
        },
        componentDidUpdate: function(prevProps, nextProps){
            if(this.state.showMenu){
                document.addEventListener('click', this._observer, false);
            }else{
                document.removeEventListener('click', this._observer, false);
            }
        },

        menuClicked:function(event, index, menuItem){
            this.props.onMenuClicked(menuItem);
            this.hideMenu();
        },
        render: function(){

            if(this.state.showMenu) {
                if(this.state.style){
                    return (
                        <div style={this.state.style} className="menu-positioner">
                            <ReactMUI.Menu
                                onItemClick={this.menuClicked}
                                menuItems={this.state.menuItems}
                            />
                        </div>
                    );
                }
                return (
                    <ReactMUI.Menu
                        onItemClick={this.menuClicked}
                        menuItems={this.state.menuItems}
                    />
                );
            }else{
                return null;
            }
        }

    });



})(window);