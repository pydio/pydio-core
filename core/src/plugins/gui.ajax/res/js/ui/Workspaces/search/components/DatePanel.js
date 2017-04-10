import React from 'react';

import {Subheader, DropDownMenu, MenuItem, DatePicker, TextField, Toggle, FlatButton} from 'material-ui';

class SearchDatePanel extends React.Component {

    static get styles() {
        return {
            dropdownLabel: {
                padding: 0
            },
            dropdownUnderline: {
                marginLeft: 0,
                marginRight: 0
            },
            dropdownIcon: {
                right: 0
            },
            datePickerGroup: {
                display: "flex",
                justifyContent: "space-between"
            },
            datePicker: {
                flex: 1
            },
            dateInput: {
                width: "auto",
                flex: 1
            },
            dateClose: {
                lineHeight: "48px",
                right: 5,
                position: "relative"
            }
        }
    }

    constructor(props) {
        super(props)

        this.state = {
            value:'custom',
            startDate: null,
            endDate: null
        }
    }

    componentDidUpdate(prevProps, prevState) {
        if (prevState != this.state) {
            const {value, startDate, endDate} = this.state

            if (value === 'custom') {
                if (!startDate && !endDate) {
                    this.props.onChange({ajxp_modiftime: null})
                } else {
                    this.props.onChange({ajxp_modiftime: `[${startDate || 'XXX'} TO ${endDate || 'XXX'}]`})
                }
            } else {
                this.props.onChange({ajxp_modiftime: value})
            }
        }
    }

    render() {
        const today = new Date();

        const {dropdownLabel, dropdownUnderline, dropdownIcon, datePickerGroup, datePicker, dateInput, dateClose} = SearchDatePanel.styles
        const {inputStyle} = this.props
        const {value, startDate, endDate} = this.state;

        return (
            <div>
                <DatePickerFeed pydio={this.props.pydio}>
                {items =>
                    <DropDownMenu autoWidth={false} labelStyle={dropdownLabel} underlineStyle={dropdownUnderline} iconStyle={dropdownIcon} style={inputStyle} value={value} onChange={(e, index, value) => this.setState({value})}>
                        {items.map((item) => <MenuItem value={item.payload} label={item.text} primaryText={item.text} />)}
                    </DropDownMenu>
                }
                </DatePickerFeed>

                {value === 'custom' &&
                    <div style={{...datePickerGroup, ...inputStyle}}>
                        <DatePicker
                            textFieldStyle={dateInput}
                            style={datePicker}
                            value={startDate}
                            onChange={(e, date) => this.setState({startDate: date})}
                            hintText={"From..."}
                            autoOk={true}
                            maxDate={endDate || today}
                            defaultDate={startDate}
                        />
                        <span className="mdi mdi-close" style={dateClose} onClick={() => this.setState({startDate: null})} />
                        <DatePicker
                            textFieldStyle={dateInput}
                            style={datePicker}
                            value={endDate}
                            onChange={(e, date) => this.setState({endDate: date})}
                            hintText={"To..."}
                            autoOk={true}
                            minDate={startDate}
                            maxDate={today}
                            defaultDate={endDate}
                        />
                        <span className="mdi mdi-close" style={dateClose} onClick={() => this.setState({endDate: null})} />
                    </div>
                }
            </div>
        );
    }
}

const DatePickerFeed = ({pydio, children}) => {
    const getMessage = (messageId) => pydio.MessageHash[messageId];

    const items = [
        {payload: 'custom', text: 'Custom Dates'},
        {payload: 'AJXP_SEARCH_RANGE_TODAY', text: getMessage('493')},
        {payload: 'AJXP_SEARCH_RANGE_YESTERDAY', text: getMessage('494')},
        {payload: 'AJXP_SEARCH_RANGE_LAST_WEEK', text: getMessage('495')},
        {payload: 'AJXP_SEARCH_RANGE_LAST_MONTH', text: getMessage('496')},
        {payload: 'AJXP_SEARCH_RANGE_LAST_YEAR', text: getMessage('497')}
    ];

    return children(items)
}

export default SearchDatePanel
