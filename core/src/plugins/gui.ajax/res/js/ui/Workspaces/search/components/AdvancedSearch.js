import React, {Component} from 'react';
import _ from 'lodash';

import {Subheader, TextField} from 'material-ui';
const {PydioContextConsumer} = require('pydio').requireLib('boot')

import DatePanel from './DatePanel';
import FileFormatPanel from './FileFormatPanel';
import FileSizePanel from './FileSizePanel';

class AdvancedSearch extends Component {

    static get styles() {
        return {
            text: {
                width: "calc(100% - 32px)",
                margin: "0 16px"
            }
        }
    }

    constructor(props) {
        super(props)

        this.state = {
            value: props.value
        }
    }

    onChange(values) {
        if (values.hasOwnProperty('basename')) {
            this.setState({
                value: values.basename
            })
        }

        this.props.onChange(values)
        this.props.onSubmit()
    }

    renderField(key, val) {

        const {text} = AdvancedSearch.styles

        if (typeof val === 'object') {
            const {label, renderComponent} = val;

            // The field might have been assigned a method already
            if (renderComponent) {
                return renderComponent({
                    ...props,
                    ...this.props,
                    label,
                    fieldname
                })
            }
        }

        const fieldname = (key === 'basename') ? key : 'ajxp_meta_' + key

        return (
            <TextField
                key={fieldname}
                value={this.state.value}
                style={text}
                className="mui-text-field"
                floatingLabelFixed={true}
                floatingLabelText={val}
                hintText={val}
                onChange={(e) => this.onChange({[fieldname]: e.target.value || null})}
            />
        );
    }

    render() {

        const {text} = AdvancedSearch.styles

        const {pydio, onChange, getMessage} = this.props

        return (
            <div className="search-advanced">
                <Subheader>{getMessage(489)}</Subheader>
                <AdvancedMetaFields {...this.props}>
                    {fields =>
                        <div>
                            {Object.keys(fields).map((key) => this.renderField(key, fields[key]))}
                        </div>
                    }
                </AdvancedMetaFields>

                <Subheader>{getMessage(490)}</Subheader>
                <DatePanel pydio={pydio} inputStyle={text} onChange={(values) => this.onChange(values)} />

                <Subheader>{getMessage(498)}</Subheader>
                <FileFormatPanel pydio={pydio} inputStyle={text} onChange={(values) => this.onChange(values)} />

                <Subheader>{getMessage(503)}</Subheader>
                <FileSizePanel pydio={pydio} inputStyle={text} onChange={(values) => this.onChange(values)} />
            </div>
        )
    }
}

AdvancedSearch = PydioContextConsumer(AdvancedSearch);

class AdvancedMetaFields extends Component {

    constructor(props) {
        super(props)

        const {pydio} = props

        const registry = pydio.getXmlRegistry()

        // Parse client configs
        let options = JSON.parse(XMLUtils.XPathGetSingleNodeText(registry, 'client_configs/template_part[@ajxpClass="SearchEngine" and @theme="material"]/@ajxpOptions'));

        this.build = _.debounce(this.build, 500)

        this.state = {
            options,
            fields: {}
        }
    }

    componentWillMount() {
        this.build()
    }

    build() {

        const {options} = this.state
        const {metaColumns, reactColumnRenderers} = {...options}

        const generic = {basename: this.props.getMessage(1)}

        // Looping through the options to check if we have a special renderer for any
        const specialRendererKeys = Object.keys({...reactColumnRenderers})
        const standardRendererKeys = Object.keys({...metaColumns}).filter((key) => specialRendererKeys.indexOf(standardRendererKeys) > -1)

        const columns = standardRendererKeys.map((key) => {key: metaColumns[key]}).reduce((obj, current) => obj = {...obj, ...current}, [])

        const renderers = Object.keys({...reactColumnRenderers}).map((key) => {
            const renderer = reactColumnRenderers[key]
            const namespace = renderer.split('.',1).shift()

            // If the renderer is not loaded in memory, we trigger the load and send to rebuild
            if (!window[namespace]) {
                ResourcesManager.detectModuleToLoadAndApply(renderer, () => this.build(), true);
                return
            }

            return {
                [key]: {
                    label: metaColumns[key],
                    renderComponent: renderer
                }
            }
        }).reduce((obj, current) => obj = {...obj, ...current}, [])

        const fields = {
            ...generic,
            ...columns,
            ...renderers
        }

        this.setState({
            fields
        })
    }

    render() {
        return this.props.children(this.state.fields)
    }
}

AdvancedMetaFields.propTypes = {
    children: React.PropTypes.func.isRequired,
};

export default AdvancedSearch
