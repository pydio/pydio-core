import React from 'react';
const {PydioContextConsumer} = require('pydio').requireLib('boot')

import {TextField} from 'material-ui';

class SearchFileSizePanel extends React.Component {

    constructor(props) {
        super(props)

        this.state = {
            from:false,
            to: null
        }
    }

    onChange() {
        this.setState({
            from: this.refs.from.getValue() || 0,
            to: this.refs.to.getValue() || 1099511627776
        })
    }

    componentWillUpdate(nextProps, nextState) {
        if (nextState === this.state) return

        const {from, to} = nextState

        this.props.onChange({
            ajxp_bytesize: (from && to) ? '['+from+' TO '+to+']' : null
        })
    }

    render() {

        const {inputStyle, getMessage, ...props} = this.props

        return (
            <div>
                <TextField
                    ref="from"
                    style={inputStyle}
                    hintText={getMessage(504)}
                    floatingLabelFixed={true}
                    floatingLabelText={getMessage(613)}
                    onChange={this.onChange.bind(this)}
                />
                <TextField
                    ref="to"
                    style={inputStyle}
                    hintText={getMessage(504)}
                    floatingLabelFixed={true}
                    floatingLabelText={getMessage(614)}
                    onChange={this.onChange.bind(this)}
                />
            </div>
        );
    }
}

SearchFileSizePanel = PydioContextConsumer(SearchFileSizePanel)
export default SearchFileSizePanel