const React = require('react');
const {Textfit} = require('react-textfit');
const {muiThemeable} = require('material-ui/styles');
const PathUtils = require('pydio/util/path')
import ShareContextConsumer from '../ShareContextConsumer'

let HeaderPanel = React.createClass({

    render: function(){

        if(this.props.noModal){
            return (null);
        }
        let nodePath = this.props.shareModel.getNode().getPath();
        return (
            <div className="headerPanel" style={{backgroundColor:this.props.muiTheme.palette.primary1Color}}>
                <Textfit mode="single" max={30}>{this.props.getMessage('44').replace('%s', PathUtils.getBasename(nodePath))}</Textfit>
            </div>
        );
    }
});

HeaderPanel = ShareContextConsumer(HeaderPanel);
HeaderPanel = muiThemeable()(HeaderPanel);

export {HeaderPanel as default}