const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {FlatButton, TextField, DatePicker} = require('material-ui')
const {ValidPassword} = require('pydio').requireLib('form')
const ShareModel = require('pydio').requireLib('ReactModelShare');
import Card from '../main/Card'

let PublicLinkSecureOptions = React.createClass({

    propTypes: {
        linkData: React.PropTypes.object.isRequired,
        shareModel: React.PropTypes.instanceOf(ShareModel),
        style: React.PropTypes.object
    },

    updateDLExpirationField: function(event){
        var newValue = event.currentTarget.value;
        if(parseInt(newValue) < 0) newValue = - parseInt(newValue);
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
        if(newValue && !this.refs.passField.isValid()){
            this.props.shareModel.setValidStatus(false);
        } else{
            this.props.shareModel.setValidStatus(true);
        }
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
                    fullWidth={true}
                />
            );
        }else if(!this.props.isReadonly()){
            passwordField = (
                <ValidPassword
                    name="share-password"
                    ref="passField"
                    attributes={{label:this.props.getMessage('23')}}
                    value={this.props.shareModel.getPassword(linkId)}
                    onChange={this.updatePassword}
                />
            );
        }
        if(passwordField){
            return (
                <div className="password-container" style={{display:'flex', alignItems:'baseline', marginBottom: 10}}>
                    <span className="ajxp_icon_span mdi mdi-file-lock"/>
                    <div style={{width:resetPassword ? '50%' : '100%', display:'inline-block'}}>
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
        const linkId = this.props.linkData.hash;
        const passContainer = this.renderPasswordContainer();
        const crtLinkDLAllowed = this.props.shareModel.getPublicLinkPermission(linkId, 'download');
        let dlLimitValue = this.props.shareModel.getExpirationFor(linkId, 'downloads') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'downloads');
        const expirationDateValue = this.props.shareModel.getExpirationFor(linkId, 'days') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'days');
        const auth = ShareModel.getAuthorizations(this.props.pydio);
        const today = new Date();

        let calIcon = <span className="ajxp_icon_span mdi mdi-calendar-clock"/>;
        let expDate, maxDate, maxDownloads = null, dateExpired = false, dlExpired = false;
        if(parseInt(auth.max_expiration) > 0){
            maxDate = new Date();
            maxDate.setDate(today.getDate() + parseInt(auth.max_expiration));
        }
        if(parseInt(auth.max_downloads) > 0){
            maxDownloads = parseInt(auth.max_downloads);
            dlLimitValue = Math.min(dlLimitValue, maxDownloads);
        }
        if(expirationDateValue){
            if(expirationDateValue < 0){
                dateExpired = true;
            }
            expDate = new Date();
            expDate.setDate(today.getDate() + parseInt(expirationDateValue));
            var clearValue = function(){
                this.props.shareModel.setExpirationFor(linkId, "days", "");
            }.bind(this);
            calIcon = <span className="mdi mdi-close-circle ajxp_icon_span" onClick={clearValue}/>;
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
            <Card style={this.props.style} title={this.props.getMessage('196')}>
                <div className="section-legend">{this.props.getMessage('24')}</div>
                {passContainer}
                <div className="expires" style={{display:'flex', alignItems:'center'}}>
                    <div style={{flex:1, display:'flex', alignItems:'center', position:'relative'}} className={dateExpired?'limit-block-expired':null}>
                        {calIcon}
                        <DatePicker
                            ref="expirationDate"
                            key="start"
                            value={expDate}
                            minDate={new Date()}
                            maxDate={maxDate}
                            autoOk={true}
                            disabled={this.props.isReadonly()}
                            onChange={this.onDateChange}
                            showYearSelector={true}
                            floatingLabelText={this.props.getMessage(dateExpired?'21b':'21')}
                            mode="landscape"
                            formatDate={this.formatDate}
                            style={{flex: 1}}
                            fullWidth={true}
                        />
                    </div>
                    <div style={{flex:1, alignItems:'center', display:crtLinkDLAllowed?'flex':'none', position:'relative'}} className={dlExpired?'limit-block-expired':null}>
                        <span className="mdi mdi-download ajxp_icon_span"/>
                        <TextField
                            type="number"
                            disabled={this.props.isReadonly()}
                            floatingLabelText={this.props.getMessage(dlExpired?'22b':'22')}
                            value={dlLimitValue > 0 ? dlLimitValue : ''}
                            onChange={this.updateDLExpirationField}
                            fullWidth={true}
                            style={{flex: 1}}
                        />
                        {dlCounterString}
                    </div>
                </div>
            </Card>
        );
    }
});

PublicLinkSecureOptions = ShareContextConsumer(PublicLinkSecureOptions)
export {PublicLinkSecureOptions as default}