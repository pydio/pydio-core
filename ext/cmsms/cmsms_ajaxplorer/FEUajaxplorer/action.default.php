<?php
if (!isset($gCms)) exit;
//* we verify that user is logged in
$feusers=$this->GetModuleInstance('FrontEndUsers');
if(!$uid = $feusers->LoggedInId()) return;
//* we verify that user is in the authorized group
$group=$this->GetPreference('ajxp_auth_group','');
$groups=$feusers->GetMemberGroupsArray( $uid );
	foreach($groups as $grp) {
	$own[]=$grp['groupid'];
	}
if(!in_array($group, $own)) return;
//****** ok we create the link ********************
$link_text=$this->GetPreference('ajxp_link_text','');
echo $this->CreateFrontendLink($id,'15','redirect',$link_text,'','',false,true,'title='.$link_text,false);
//string   CreateFrontendLink  (string $id, string $returnid, string $action, [string $contents = ''], [string $params = array()], [string $warn_message = ''], [boolean $onlyhref = false], [boolean $inline = true], [string $addtext = ''], [ $targetcontentonly = false], [ $prettyurl = '']) 
?>
