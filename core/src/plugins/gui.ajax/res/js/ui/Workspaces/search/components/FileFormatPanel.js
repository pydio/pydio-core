import React, {Component} from 'react';
const {PydioContextConsumer} = require('pydio').requireLib('boot')

import {TextField, Toggle} from 'material-ui';

class SearchFileFormatPanel extends Component {

    constructor(props) {
        super(props)

        this.state = {
            folder: this.props.values['ajxp_mime'] && this.props.values['ajxp_mime'] === 'ajxp_folder' ? true: undefined,
            ext: (this.props.values['ajxp_mime'] && this.props.values['ajxp_mime'] !== 'ajxp_folder' ? this.props.values['ajxp_mime'] : undefined),
        }
    }

    componentDidUpdate(prevProps, prevState) {
        if (prevState === this.state) return

        const {folder, ext} = this.state

        this.props.onChange({
            ajxp_mime: (folder) ? 'ajxp_folder' : ext
        })
    }

    render() {

        const {inputStyle, getMessage, ...props} = this.props

        return (
            <div>
                <Toggle
                    style={inputStyle}
                    name="toggleFolder"
                    value="ajxp_folder"
                    label={getMessage(502)}
                    toggled={this.state.folder}
                    onToggle={(e, toggled) => this.setState({folder: toggled})}
                />
                {!this.state.folder &&
                    <TextField
                        style={inputStyle}
                        className="mui-text-field"
                        hintText={getMessage(500)}
                        floatingLabelFixed={true}
                        floatingLabelText={getMessage(500)}
                        value={this.state.ext}
                        onChange={(e) => this.setState({ext: e.target.value})}
                    />
                }
            </div>
        );
    }
}

SearchFileFormatPanel = PydioContextConsumer(SearchFileFormatPanel)
export default SearchFileFormatPanel
