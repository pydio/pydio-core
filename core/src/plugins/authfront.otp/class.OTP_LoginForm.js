/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 *
 */
Class.create("OTP_LoginForm", {
    initialize:function(){
        document.observe("ajaxplorer:afterApply-login", this.observer.bind(this));
    },
    observer: function(){
        // string
        var enableModifyGUI = pydio.getPluginConfigs("authfront.otp").get("MODIFY_LOGIN_SCREEN");
        if(enableModifyGUI){
            var f= modal.getForm();

            if(!f.down('input[name="otp_code"]')){
                try{
                    var el = f.down('input[name="password"]').up("div.SF_element");
                    var clone = el.cloneNode(true);
                    el.insert({after:clone});
                    var newField = clone.down('input[name="password"]');
                    newField.writeAttribute('name', 'otp_code');
                    newField.writeAttribute('data-ajxpLoginAdditionalParameter', 'true');
                    clone.down('div.SF_label').update('Unique Code (6 digits)');
                }catch(e){
                    if(console) console.log('Error while replacing OTP field', e);
                }}
        }
        else{
            var f= modal.getForm();
            if(f.down('input[name="otp_code"]')){
                f.remove(f.down('input[name="otp_code"]'));
            }
            var otpEnabled = '<span id="add_otp_notion" style=" font-size: 16px;"> * OTP enabled</span>';
            if(!f.down(("#add_otp_notion"))){
                f.insert({bottom:otpEnabled});
            }
        }
    }
});
window.OTPFORM = new OTP_LoginForm();