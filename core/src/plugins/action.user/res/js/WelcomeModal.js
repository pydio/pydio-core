import React from 'react'
import ProfilePane from './ProfilePane'
import Pydio from 'pydio'
import {CardTitle, FlatButton} from 'material-ui'
const { ActionDialogMixin, CancelButtonProviderMixin, SubmitButtonProviderMixin} = Pydio.requireLib('boot');

/**
 * Sample Dialog class used for reference only, ready to be
 * copy/pasted :-)
 */
export default React.createClass({

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: '',
            dialogIsModal: true,
            dialogSize:'sm',
            dialogPadding: 0
        };
    },
    close: function(skip){

        if(this.props.onRequestStart){
            this.props.onRequestStart(skip);
        }
        this.props.onDismiss();
    },
    getMessage: function(id){
        return this.props.pydio.MessageHash['ajax_gui.tour.welcomemodal.' + id];
    },
    getButtons: function(){
        return [
            <FlatButton label={this.getMessage('skip')} onTouchTap={()=> {this.close(true)}}/>,
            <FlatButton label={this.getMessage('start')} primary={true} onTouchTap={() => this.close(false)}/>,
        ];
    },
    render: function(){
        return (
            <div>
                <div style={{position:'relative', width:'100%', height: 205, overflow: 'hidden', backgroundColor: '#eceff1'}}>
                    <ProfilePane miniDisplay={true} {...this.props} saveOnChange={true} />
                </div>
                <CardTitle title={this.getMessage('title')} subtitle={this.getMessage('subtitle')}/>
            </div>
        );
    }

});

