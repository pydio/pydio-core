<?php
if (!isset($gCms)) exit;
if (! $this->CheckAccess())
	{
	return $this->DisplayErrorPage($id, $params, $returnid,$this->Lang('accessdenied'));
	}
echo $this->StartTabHeaders();
echo $this->SetTabHeader('settings',$this->Lang('title_settings'));
echo $this->EndTabHeaders();
//*********************************************************************************
	echo $this->StartTabContent();
    echo $this->StartTab('settings',$params);
    include(dirname(__FILE__).'/function.admin_settings_tab.php');
    echo $this->EndTab();
	echo $this->EndTabContent();
//*********************************************************************************
?>