<?php
if (!isset($gCms)) exit;
//* we verify that user is logged in
$feusers = $gCms->modules['FrontEndUsers']['object'];
if(!$uid = $feusers->LoggedInId()) exit;
//prepare parameters for login_cmsms() fonction in ajxp auth.cmsms
$uid=$feusers->LoggedInName();
$sessionid=session_id();

$target="/content.php?get_action=login_cmsms&username=".$uid."&sessionid=".$sessionid;
$url=$this->GetPreference('ajxp_realurl','');
$link_text=$this->GetPreference('ajxp_link_text','');
$href=$url.$target;
header("Location: ".$href);
?>
