import React from 'react';

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

        const {inputStyle, ...props} = this.props

        return (
            <div>
                <TextField
                    ref="from"
                    style={inputStyle}
                    hintText="1Mo,1Go,etc"
                    floatingLabelFixed={true}
                    floatingLabelText="Size greater than..."
                    onChange={this.onChange.bind(this)}
                />
                <TextField
                    ref="to"
                    style={inputStyle}
                    hintText="1Mo,1Go,etc"
                    floatingLabelFixed={true}
                    floatingLabelText="Size bigger than..."
                    onChange={this.onChange.bind(this)}
                />
            </div>
        );
    }
}

export default SearchFileSizePanel
