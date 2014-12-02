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
Class.create("CAS_LoginForm", {
    initialize:function(){
        document.observe("ajaxplorer:afterApply-login", this.observer.bind(this));
    },
    observer: function(){
        // String for login page
        var auth_cas_msg = "Use CAS Credential";
        var auth_pyd_msg = "Use Pydio Credential";
        var auth_button_msg = "Click here";
        if(ajaxplorer.getPluginConfigs("authfront.cas")._object.AUTH_CAS_MESS_STRING){
            auth_cas_msg = ajaxplorer.getPluginConfigs("authfront.cas")._object.AUTH_CAS_MESS_STRING;
        }
        if(ajaxplorer.getPluginConfigs("authfront.cas")._object.AUTH_PYD_MESS_STRING){
            auth_pyd_msg = ajaxplorer.getPluginConfigs("authfront.cas")._object.AUTH_PYD_MESS_STRING;
        }
        if(ajaxplorer.getPluginConfigs("authfront.cas")._object.AUTH_CLICK_MESS_STRING){
            auth_button_msg = ajaxplorer.getPluginConfigs("authfront.cas")._object.AUTH_CLICK_MESS_STRING;
        }

        // string form
        var login_via_cas_form = '<form id="enableredirecttocas" method="post" action=""> \
                                  <input type="hidden" name="put_action_enable_redirect" value="yes"> \
                                  </form>';
        var cas_session_span = '<span id="span_to_modify_login_form_with_cas" class="icon-chevron-right"></span><span style=" font-size: 16px;">' + auth_cas_msg + '</span>';
        var cas_session_a_tag = '<div style="width:100%;margin-top: 10px;margin-bottom: 10px; margin-left: 10px;" id="gui_login_nas_nasos" > \
                                <a href="javascript:document.forms[\'enableredirecttocas\'].submit();" \
                                style=" border-style:solid;padding-left:30px; padding-right: 30px; block;width: 125px;height: 12px;background: #ffffff;padding-top: 5px; padding-bottom:5px;text-align: center;border:1px;border-radius: 15px;color: #000000;font-weight: bold;" \
                                > ' + auth_button_msg + '</a>\
                                </div><br/>';
                                                        
        var login_session_span = '<span class="icon-chevron-right"></span><span style=" font-size: 16px;">' + auth_pyd_msg + '</span>';

        var objallforms = $("all_forms");
        if(!objallforms.down("#enableredirecttocas")){
            objallforms.insert({top:login_via_cas_form});
        }
        $$("div.dialogContent").first().setStyle("padding-top: 17% !important");
        var obj_loginform = $("login_form");
        if(!obj_loginform.down(("#span_to_modify_login_form_with_cas"))){
            obj_loginform.insert({top:login_session_span});
            obj_loginform.insert({top:cas_session_a_tag});
            obj_loginform.insert({top:cas_session_span});
        }

    }
});
var enableModifyGUI = ajaxplorer.getPluginConfigs("authfront.cas")._object.MODIFY_LOGIN_SCREEN;
if(enableModifyGUI){
    window.CASFORM = new CAS_LoginForm();
}
