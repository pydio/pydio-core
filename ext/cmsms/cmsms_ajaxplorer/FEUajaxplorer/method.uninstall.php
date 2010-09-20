<?php
if (!isset($gCms)) exit;
		$this->RemovePreference();
		$this->Audit( 0, $this->Lang('friendlyname'), $this->Lang('uninstalled'));
?>