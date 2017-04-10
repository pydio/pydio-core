import React, {Component} from 'react';

import {TextField, Toggle} from 'material-ui';

class SearchFileFormatPanel extends Component {

    constructor(props) {
        super(props)

        this.state = {
            folder: false,
            ext: null
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

        const {inputStyle, ...props} = this.props

        return (
            <div>
                <Toggle
                    style={inputStyle}
                    name="toggleFolder"
                    value="ajxp_folder"
                    label="Folders Only"
                    onToggle={(e, toggled) => this.setState({folder: toggled})}
                />
                {!this.state.folder &&
                    <TextField
                        style={inputStyle}
                        className="mui-text-field"
                        hintText="Extension"
                        floatingLabelFixed={true}
                        floatingLabelText="File extension"
                        onChange={(e) => this.setState({ext: e.target.value})}
                    />
                }
            </div>
        );
    }
}

export default SearchFileFormatPanel
