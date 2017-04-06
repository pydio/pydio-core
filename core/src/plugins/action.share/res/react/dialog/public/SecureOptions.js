const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {FlatButton, TextField, DatePicker} = require('material-ui')
const {ValidPassword} = require('pydio').requireLib('form')
const ShareModel = require('pydio').requireLib('ReactModelShare');

let PublicLinkSecureOptions = React.createClass({

    propTypes: {
        linkData: React.PropTypes.object.isRequired,
        shareModel: React.PropTypes.instanceOf(ShareModel)
    },

    updateDLExpirationField: function(event){
        var newValue = event.currentTarget.value;
        this.props.shareModel.setExpirationFor(this.props.linkData.hash, "downloads", newValue);
    },

    updateDaysExpirationField: function(event, newValue){
        if(!newValue){
            newValue = event.currentTarget.getValue();
        }
        this.props.shareModel.setExpirationFor(this.props.linkData.hash, "days", newValue);
    },

    onDateChange: function(event, value){
        var today = new Date();
        var date1 = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
        var date2 = Date.UTC(value.getFullYear(), value.getMonth(), value.getDate());
        var ms = Math.abs(date1-date2);
        var integerVal = Math.floor(ms/1000/60/60/24); //floor should be unnecessary, but just in case
        this.updateDaysExpirationField(event, integerVal);
    },

    resetPassword: function(){
        this.props.shareModel.resetPassword(this.props.linkData.hash);
    },

    updatePassword: function(newValue, oldValue){
        this.props.shareModel.updatePassword(this.props.linkData.hash, newValue);
    },

    renderPasswordContainer: function(){
        var linkId = this.props.linkData.hash;
        var passwordField;
        if(this.props.shareModel.hasHiddenPassword(linkId)){
            var resetPassword = (
                <FlatButton
                    disabled={this.props.isReadonly()}
                    secondary={true}
                    onClick={this.resetPassword}
                    label={this.props.getMessage('174')}
                />
            );
            passwordField = (
                <TextField
                    floatingLabelText={this.props.getMessage('23')}
                    disabled={true}
                    value={'********'}
                    onChange={this.updatePassword}
                />
            );
        }else if(!this.props.isReadonly()){
            passwordField = (
                <ValidPassword
                    attributes={{label:this.props.getMessage('23')}}
                    value={this.props.shareModel.getPassword(linkId)}
                    onChange={this.updatePassword}
                />
            );
        }
        if(passwordField){
            return (
                <div className="password-container">
                    <div style={{width:resetPassword ? '50%' : '100%', display:'inline-block'}}>
                        <span className="ajxp_icon_span icon-lock"/>
                        {passwordField}
                    </div>
                    {resetPassword &&
                    <div style={{width: '50%', display: 'inline-block'}}>
                        {resetPassword}
                    </div>
                    }
                </div>
            );
        }else{
            return null;
        }
    },

    formatDate : function(dateObject){
        var dateFormatDay = this.props.getMessage('date_format', '').split(' ').shift();
        return dateFormatDay
            .replace('Y', dateObject.getFullYear())
            .replace('m', dateObject.getMonth() + 1)
            .replace('d', dateObject.getDate());
    },

    render: function(){
        var linkId = this.props.linkData.hash;
        var passContainer = this.renderPasswordContainer();
        var crtLinkDLAllowed = this.props.shareModel.getPublicLinkPermission(linkId, 'download');
        var dlLimitValue = this.props.shareModel.getExpirationFor(linkId, 'downloads') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'downloads');
        var expirationDateValue = this.props.shareModel.getExpirationFor(linkId, 'days') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'days');
        var calIcon = <span className="ajxp_icon_span icon-calendar"/>;
        var expDate = null;
        var maxDate = null, maxDownloads = null, dateExpired = false, dlExpired = false;
        var auth = ShareModel.getAuthorizations(this.props.pydio);
        var today = new Date();
        if(parseInt(auth.max_expiration) > 0){
            maxDate = new Date();
            maxDate.setDate(today.getDate() + parseInt(auth.max_expiration));
        }
        if(parseInt(auth.max_downloads) > 0){
            // todo: limit the field values by default?
            maxDownloads = parseInt(auth.max_downloads);
        }
        if(expirationDateValue){
            if(expirationDateValue < 0){
                dateExpired = true;
            }
            expDate = new Date();
            expDate.setDate(today.getDate() + parseInt(expirationDateValue));
            var clearValue = function(){
                this.props.shareModel.setExpirationFor(linkId, "days", "");
                ReactDOM.findDOMNode(this.refs['expirationDate']).querySelector(".mui-text-field-input").value = "";
            }.bind(this);
            calIcon = <span className="ajxp_icon_span mdi mdi-close-circle" onClick={clearValue}/>;
            var calLabel = <span className="calLabelHasValue">{this.props.getMessage(dateExpired?'21b':'21')}</span>
        }
        if(dlLimitValue){
            var dlCounter = this.props.shareModel.getDownloadCounter(linkId);
            var resetDl = function(){
                if(window.confirm(this.props.getMessage('106'))){
                    this.props.shareModel.resetDownloadCounter(linkId, function(){});
                }
            }.bind(this);
            if(dlCounter) {
                var resetLink = <a style={{cursor:'pointer'}} onClick={resetDl} title={this.props.getMessage('17')}>({this.props.getMessage('16')})</a>;
                if(dlCounter >= dlLimitValue){
                    dlExpired = true;
                }
            }
            var dlCounterString = <span className="dlCounterString">{dlCounter+ '/'+ dlLimitValue} {resetLink}</span>;
        }
        return (
            <div>
                <h3 style={{paddingTop:0}}>{this.props.getMessage('196')}</h3>
                <div className="section-legend">{this.props.getMessage('24')}</div>
                {passContainer}
                <div className="expires">
                    <div style={{width:'50%', display:'inline-block', position:'relative'}} className={dateExpired?'limit-block-expired':null}>
                        {calIcon}
                        {calLabel}
                        <DatePicker
                            ref="expirationDate"
                            disabled={this.props.isReadonly()}
                            onChange={this.onDateChange}
                            key="start"
                            hintText={this.props.getMessage(dateExpired?'21b':'21')}
                            autoOk={true}
                            minDate={new Date()}
                            maxDate={maxDate}
                            defaultDate={expDate}
                            showYearSelector={true}
                            onShow={null}
                            onDismiss={null}
                            formatDate={this.formatDate}
                        />
                    </div>
                    <div style={{width:'50%', display:crtLinkDLAllowed?'inline-block':'none', position:'relative'}} className={dlExpired?'limit-block-expired':null}>
                        <span className="ajxp_icon_span mdi mdi-download"/>
                        <TextField
                            type="number"
                            disabled={this.props.isReadonly()}
                            floatingLabelText={this.props.getMessage(dlExpired?'22b':'22')}
                            value={this.props.shareModel.getExpirationFor(linkId, 'downloads') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'downloads')}
                            onChange={this.updateDLExpirationField}
                        />
                        {dlCounterString}
                    </div>
                </div>
            </div>
        );
    }
});

PublicLinkSecureOptions = ShareContextConsumer(PublicLinkSecureOptions)
export {PublicLinkSecureOptions as default}