import Utils from './Utils'
import PopupMenu from './PopupMenu'

export default React.createClass({

    propTypes:{
        buttonTitle: React.PropTypes.oneOfType([React.PropTypes.string,React.PropTypes.object]).isRequired,
        className: React.PropTypes.string,
        menuItems:React.PropTypes.array,
        selectionContext:React.PropTypes.string,
        toolbars:React.PropTypes.array,
        raised:React.PropTypes.bool
    },

    showMenu: function(){
        if(this.props.menuItems){
            this.refs['menu'].showMenu(null, this.props.menuItems);
        }else{
            let actions = this.props.pydio.Controller.getContextActions('genericContext', null, this.props.toolbars);
            let items   = Utils.pydioActionsToItems(actions);
            this.refs['menu'].showMenu(null, items);
        }
    },

    menuClicked: function(object){
        object.payload();
    },

    render: function(){
        let label = <span>{this.props.buttonTitle} <span className="icon-caret-down"></span></span>
        let button;
        if(this.props.raised){
            button = <ReactMUI.RaisedButton
                primary={this.props.primary}
                secondary={this.props.secondary}
                disabled={this.props.disabled}
                label={label}
                onClick={this.showMenu}
            />
            ;
        }else{
            button = <ReactMUI.FlatButton
                primary={this.props.primary}
                secondary={this.props.secondary}
                disabled={this.props.disabled}
                label={label}
                onClick={this.showMenu}
            />;

        }
        return (
            <span id={this.props.id} className={this.props.className}>
                    {button}
                    <PopupMenu
                        ref="menu"
                        menuItems={[]}
                        onMenuClicked={this.menuClicked}
                    />
                </span>
        );
    }

});
