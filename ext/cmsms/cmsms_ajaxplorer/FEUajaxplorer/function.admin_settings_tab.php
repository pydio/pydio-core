<?php
if( !isset($gCms) ) exit;
$db =& $this->GetDb();
    
    $groups = array();
    $q = "SELECT * FROM ".cms_db_prefix()."module_feusers_groups";
    $dbresult = $db->Execute( $q );
    if( $dbresult ) {
    while( $row = $dbresult->FetchRow() )
      {
		  $groups[$row['groupname']] = $row['id'];
      }
    }
$selected=$this->GetPreference('ajxp_auth_group','');
$smarty->assign('formstart',$this->CGCreateFormStart($id,'admin_save_settings'));
$smarty->assign('formend',$this->CreateFormEnd());
$smarty->assign('ajxp_realurl',$this->GetPreference('ajxp_realurl',''));
$smarty->assign('ajxp_secret',$this->GetPreference('ajxp_secret',''));
$smarty->assign('ajxp_link_text',$this->GetPreference('ajxp_link_text',''));
$smarty->assign('ajxp_auth_group', $this->CreateInputDropdown($id,'ajxp_auth_group',$groups,-1,$selected));

echo $this->ProcessTemplate('admin_settings_tab.tpl');
#
# EOF
#
?>