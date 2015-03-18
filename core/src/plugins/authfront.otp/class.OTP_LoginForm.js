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
        var otpEnabled = '<span id="add_otp_notion" style=" font-size: 16px;"> * OTP enabled</span>';

        var obj_loginform = $("login_form");
        if(!obj_loginform.down(("#add_otp_notion"))){
            obj_loginform.insert({bottom:otpEnabled});
        }
    }
});
var enableModifyGUI = ajaxplorer.getPluginConfigs("authfront.otp")._object.MODIFY_LOGIN_SCREEN;
if(!enableModifyGUI){
    window.OTPFORM = new OTP_LoginForm();
}