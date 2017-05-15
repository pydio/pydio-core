/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */

import {RoleMessagesConsumerMixin} from '../util/MessagesMixin'

export default React.createClass({

    mixins:[RoleMessagesConsumerMixin],

    propTypes:{
        acl:React.PropTypes.string,
        disabled:React.PropTypes.bool,
        hideDeny:React.PropTypes.bool,
        hideLabels:React.PropTypes.bool,
        onChange:React.PropTypes.func
    },

    getInitialState:function(){
        return {acl: this.props.acl};
    },

    componentWillReceiveProps:function(newProps){
        if(this.refs.write){
            var acl = newProps.acl || "";
            var r = (acl != 'AJXP_VALUE_CLEAR' && acl.indexOf('r') != -1);
            var w = (acl != 'AJXP_VALUE_CLEAR' && acl.indexOf('w') != -1);
            var cR = this.refs.read.isChecked();
            var cW = this.refs.write.isChecked();
            if(r!=cR) this.refs.read.setChecked(r);
            if(w!=cW) this.refs.write.setChecked(w);
            if(!newProps.hideDeny){
                var d = (acl == 'AJXP_VALUE_CLEAR');
                var cD = this.refs.deny.isChecked();
                if(d!=cD) this.refs.deny.setChecked(d);
            }
        }

        this.setState({acl:newProps.acl});
    },

    getAcl:function(){
        return this.state.acl;
    },

    updateAcl: function(){

        if(this.props.disabled) return;

        var d = this.refs.deny.isChecked();
        var r = !d && this.refs.read.isChecked();
        var w = !d && this.refs.write.isChecked();
        var acl;
        if(!d) {
            acl = '' + (r?'r':'') + (w?'w':'');
            this.setState({acl: acl});
        }else {
            this.refs.write.setChecked(false);
            this.refs.read.setChecked(false);
            acl = 'AJXP_VALUE_CLEAR';
            this.setState({acl:acl});
        }
        if(this.props.onChange){
            this.props.onChange(acl, this.props.acl);
        }else{
            this.setState({acl:acl});
        }
    },

    render: function(){
        var acl = this.state.acl || '';
        var deny, children;
        if(!this.props.hideDeny){
            deny = (
                <div key="d">
                    <ReactMUI.Checkbox ref="deny" label={this.props.hideLabels?"":this.context.getMessage('react.5', 'ajxp_admin')} value="-" disabled={this.props.disabled}
                                       onCheck={this.updateAcl} defaultSwitched={acl.indexOf('AJXP_VALUE_CLEAR') != -1}/>
                </div>
            );
        }
        return (
            <div className="rights-selector">
                <div key="r">
                    <ReactMUI.Checkbox ref="read" label={this.props.hideLabels?"":this.context.getMessage('react.5a', 'ajxp_admin')} value="r"
                                       onCheck={this.updateAcl} disabled={this.props.disabled || acl=='AJXP_VALUE_CLEAR'}
                                       defaultSwitched={acl != 'AJXP_VALUE_CLEAR' && acl.indexOf('r') != -1}/>
                </div>
                <div key="w">
                    <ReactMUI.Checkbox ref="write" label={this.props.hideLabels?"":this.context.getMessage('react.5b', 'ajxp_admin')} value="w"
                                       onCheck={this.updateAcl} disabled={this.props.disabled || acl=='AJXP_VALUE_CLEAR'}
                                       defaultSwitched={acl != 'AJXP_VALUE_CLEAR' && acl.indexOf('w') != -1}/>
                </div>
                {deny}
            </div>
        );
    }

});

