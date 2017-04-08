const React = require('react')
const {FlatButton, RaisedButton, Popover, Divider} = require('material-ui')
const Pydio = require('pydio')
import PasswordForm from './PasswordForm'

class PasswordPopover extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {passOpen: false, passValid: false, passAnchor: null};
    }

    passOpenPopover(event){
        this.setState({passOpen: true, passAnchor:event.currentTarget});
    }

    passClosePopover(){
        this.setState({passOpen: false});
    }

    passValidStatusChange(status){
        this.setState({passValid: status});
    }

    passSubmit(){
        this.refs.passwordForm.post(function(value){
            if(value) this.passClosePopover();
        }.bind(this));
    }

    render(){
        let pydio = this.props.pydio;
        let {passOpen, passAnchor, passValid} = this.state;
        return (
            <div style={{marginLeft: 8}}>
                <RaisedButton
                    onTouchTap={this.passOpenPopover.bind(this)}
                    label={pydio.MessageHash[194]}
                    primary={true}
                />
                <Popover
                    open={passOpen}
                    anchorEl={passAnchor}
                    anchorOrigin={{horizontal: 'left', vertical: 'top'}}
                    targetOrigin={{horizontal: 'left', vertical: 'bottom'}}
                    onRequestClose={this.passClosePopover.bind(this)}
                >
                    <div>
                        <PasswordForm
                            style={{padding:10, backgroundColor:'#fafafa'}}
                            pydio={pydio}
                            ref="passwordForm"
                            onValidStatusChange={this.passValidStatusChange.bind(this)}
                        />
                        <Divider/>
                        <div style={{textAlign:'right', padding: '8px 0'}}>
                            <FlatButton label="Cancel" onTouchTap={this.passClosePopover.bind(this)}/>
                            <FlatButton disabled={!passValid} label="Ok" onTouchTap={this.passSubmit.bind(this)}/>
                        </div>
                    </div>
                </Popover>
            </div>
        );

    }

}

export {PasswordPopover as default}