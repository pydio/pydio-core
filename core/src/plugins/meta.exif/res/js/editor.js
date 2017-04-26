import React, {Component} from 'react';

import { connect } from 'react-redux';
import { compose } from 'redux';

import { IconButton, Card, CardTitle, CardText, Table, TableBody, TableRow, TableRowColumn} from 'material-ui'

const { withSelection } = PydioHOCs;

class Editor extends Component {

    constructor(props) {
        super(props)

        this.state = {
            data: [],
            error: ""
        }
    }

    static get controls() {
        return {
            options: {
                locate: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-crosshairs-gps" tooltip={"Locate on a map"}/>
            }
        }
    }

    componentDidMount() {
        this.loadNodeContent(this.props)
    }

    componentWillReceiveProps(nextProps) {
        if (nextProps.node !== this.props.node) {
            this.loadNodeContent(nextProps)
        }
    }

    loadNodeContent(props) {
        const {node} = props

        const callback = (object) => {
            this.setState(object)
            typeof this.props.onLoad === 'function' && this.props.onLoad()
        }

        PydioApi.getClient().request({
            get_action:'extract_exif',
            file: node.getPath(),
            format:'json'
        },
        ({responseJSON}) => responseJSON ? callback({data: responseJSON}) : callback({error: 'Could not load JSON'}),
        () => callback({error: 'Could not load data'}));
    }

    render() {
        let content;

        const {showControls} = this.props
        const {data, error} = this.state;

        return (
            <Viewer
                {...this.props}
                onLocate={showControls ? () => this.openGpsLocator() : null}
                error={error}
                style={{display: "flex", justifyContent: "space-around", flexFlow: "row wrap"}}
            >
                {Object.keys(data).map(key =>
                    <Card style={{width: "calc(50% - 20px)", margin: 10, overflow: "auto"}}>
                        <CardTitle key={key+'-head'}>{key}</CardTitle>

                        <CardText>
                            <Table selectable={false}>
                                <TableBody displayRowCheckbox={false}>
                                {Object.keys(data[key]).map(itemKey =>
                                    <TableRow key={`${key}-${itemKey}`}>
                                        <TableRowColumn>{itemKey}</TableRowColumn>
                                        <TableRowColumn>{data[key][itemKey]}</TableRowColumn>
                                    </TableRow>
                                )}
                                </TableBody>
                            </Table>
                        </CardText>
                    </Card>
                )}
            </Viewer>
        );
    }
}

const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

const Viewer = compose(
    withMenu,
    withLoader,
    withErrors
)(props => <div {...props} />)

export default compose(
    connect(),
    withSelection((node) => node.getMetadata().get('is_image') === '1')
)(Editor)
