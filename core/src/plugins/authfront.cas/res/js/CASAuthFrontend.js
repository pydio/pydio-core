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

(function(global) {

    let pydio = global.pydio;

    class LoginDialogModifier extends PydioReactUI.AbstractDialogModifier {

        constructor() {
            super();
            this._modifyLoginScreen = pydio.getPluginConfigs('authfront.cas').get('MODIFY_LOGIN_SCREEN');

            // String for login page
            this._auth_cas_msg = "Use CAS Credential";
            this._auth_pyd_msg = "Use Pydio Credential";
            this._auth_button_msg = "Click here";

            if(pydio.getPluginConfigs("authfront.cas").has("AUTH_CAS_MESS_STRING")){
                this._auth_cas_msg = pydio.getPluginConfigs("authfront.cas").get("AUTH_CAS_MESS_STRING");
            }
            if(pydio.getPluginConfigs("authfront.cas").has("AUTH_PYD_MESS_STRING")){
                this._auth_pyd_msg = pydio.getPluginConfigs("authfront.cas").get("AUTH_PYD_MESS_STRING");
            }
            if(pydio.getPluginConfigs("authfront.cas").has("AUTH_CLICK_MESS_STRING")){
                this._auth_button_msg = pydio.getPluginConfigs("authfront.cas").get("AUTH_CLICK_MESS_STRING")
            }

        }

        renderAdditionalComponents(props, state, accumulator) {
            if (!this._modifyLoginScreen) {
                return;
            }

            accumulator.top.push(
                <div>
                    <div style={{display:'flex', alignItems:'center', marginBottom: 30}}>
                        <div style={{flex:1, fontWeight: 500}}>{this._auth_cas_msg}</div>
                        <div><MaterialUI.RaisedButton label={this._auth_button_msg} onTouchTap={(e)=>{this._hiddenForm.submit()}}/></div>
                    </div>
                    <div style={{marginBottom: -10, fontWeight: 500}}>{this._auth_pyd_msg}</div>
                    <div style={{display:'none'}}>
                        <form ref={(f) => {this._hiddenForm = f}} id="enableredirecttocas" method="post" action="">
                            <input type="hidden" name="put_action_enable_redirect" value="yes"/>
                        </form>
                    </div>
                </div>
            );


        }

    }


    global.CASAuthFrontend = {
        LoginDialogModifier: LoginDialogModifier
    };

})(window);