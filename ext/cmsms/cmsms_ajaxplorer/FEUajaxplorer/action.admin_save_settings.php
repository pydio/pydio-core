<?php
if( !isset($gCms) ) exit;
if (! $this->CheckAccess()) return;
$this->SetCurrentTab('settings');
$this->SetPreference('ajxp_realurl',trim(rtrim($params['ajxp_realurl'],'/')));
$this->SetPreference('ajxp_secret',trim($params['ajxp_secret']));
$this->SetPreference('ajxp_link_text',trim($params['ajxp_link_text']));
$this->SetPreference('ajxp_auth_group',trim($params['ajxp_auth_group']));
//$this->RedirectToTab($id);
$this->Redirect($id, "defaultadmin", $returnid, array("active_tab"=>"settings","module_message"=>$this->Lang("modify_parameters")));
#
# EOF
#
?>