import React, {Component, PropTypes} from 'react'
import {MenuItem, DropDownMenu} from 'material-ui';
const {PydioContextConsumer} = require('pydio').requireLib('boot')

class SearchScopeSelector extends Component {

    static get propTypes() {
        return {
            value           : PropTypes.string,
            onChange        : PropTypes.func.isRequired,
            onTouchTap      : PropTypes.func.isRequired,
            style           : PropTypes.object,
            labelStyle      : PropTypes.object
        };
    }

    render(){
        const {getMessage} = this.props;
        return (
            <DropDownMenu
                value={this.props.value}
                onChange={(e,i,v) => {this.props.onChange(v)}}
                onTouchTap={this.props.onTouchTap}
                autoWidth={true}
                style={this.props.style}
                underlineStyle={{display:'none'}}
                labelStyle={this.props.labelStyle}
            >
                <MenuItem value={'folder'} primaryText={getMessage(608)}/>
                <MenuItem value={'ws'} primaryText={getMessage(609)}/>
                <MenuItem value={'all'} primaryText={getMessage(610)}/>
            </DropDownMenu>

        );
    }

}

SearchScopeSelector = PydioContextConsumer(SearchScopeSelector);
export {SearchScopeSelector as default}