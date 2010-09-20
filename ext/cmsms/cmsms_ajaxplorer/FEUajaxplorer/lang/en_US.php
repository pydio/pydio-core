<?php
$lang['friendlyname'] = 'File Sharing';
$lang['postinstall'] = 'Be sure to set "Modify FrontEndUser Properties" permissions to use this module!<br />Go to Users & Groups tab and fill in settings befor use.';
$lang['postuninstall'] = 'Goodby...';
$lang['really_uninstall'] = 'Really? Are you sure
you want to unsinstall this module?';
$lang['uninstalled'] = 'Module Uninstalled.';
$lang['installed'] = 'Module version %s installed.';
$lang['upgraded'] = 'Module upgraded to version %s.';
$lang['description'] = 'Make a bridge(autologin) between FEU users and AjaXplorer.';
$lang['modify_parameters'] = 'Parameters successfully modified';

$lang['error'] = 'Error!';
$land['admin_title'] = 'AjaXplorer Admin Panel';
$lang['admindescription'] = 'Manage autologin with your AjaXplorer installation';
$lang['accessdenied'] = 'Access Denied. Please check your permissions.';
$lang['Settings'] = 'Settings';
$lang['title_settings'] = 'Settings';
$lang['ajxp_realurl'] = 'Enter URL to youy AjaXplorer installation (http://domaine.com/ajxp)';
$lang['ajxp_secret'] = 'Enter the Secret code matching with the configuration file of AjaXplorer';
$lang['ajxp_link_text'] = 'Enter the text of the link';
$lang['submit'] = 'Submit';
$lang['ajxp_auth_group'] = 'Select the authorized groupe';

$lang['changelog'] = '<ul>
<li>Version 0.1.5 - 14 September 2010. Initial Release.</li>
</ul>';

$lang['help'] = '<h3>What Does This Do?</h3>
<p>Generates a link that allows a frontend user to use your AjaXplorer, application sharing files without connecting again.</p>
<h3>Prerequis</h3>
<p>It is strongly recommended to familiarize yourself with <a href="http://www.ajaxplorer.info/" target="_blanc">AjaXplorer(AjXp)</a> before install this module.<br />
You must validate AjXp in standard use before connecting to CMSMS.<br />
A good knowledge of FEU and methods of content protection is essential.</p>
<h3>How to use it</h3>
<ol type="I">
<li> - Install AjXp in your domain or another if you share access to your db.</li>
<li> - Create a FEU group of users who have access to AjXp.
<li> - Edit the AjXp configuration file as shown auth.cmsms module.</li>
<li> - Install this module in your CMSMS.</li>
<li> - Go to "Users / Groups >> File Sharing" and fill in the parameters.</li>
<li> - Insert {cms_module module=\'FEUajaxplorer\'}  tag in a page or a template protected or not.</li>
</ol>
The link will appear automatically when a user is logged in the authorized group. For performance reasons, it is better to include it in a secure page or a menu.
<h3>Support</h3>
<p>As per the GPL, this software is provided as-is. Please read the text of the license for the full disclaimer.</p>
<h3>Copyright and License</h3>
<p>Copyright &copy; 2010, JC Ghio <a href="mailto:jcg@interphacepro.com">&lt;jcg@interphacepro.com&gt;</a>. All Rights Are Reserved.</p>
<p>This module has been released under the <a href="http://www.gnu.org/licenses/licenses.html#GPL">GNU Public License</a>. You must agree to this license before using the module.</p>';
?>
