<?php
$lang['friendlyname'] = 'Partage de fichiers';
$lang['postinstall'] = 'Assurez-vous de la permission "Modify FrontEndUser Properties"<br />Allez dans le menu utilisateurs & goupes >> Partage de fichiers et mettez a jour les preferences.';
$lang['postuninstall'] = 'Au revoir...';
$lang['really_uninstall'] = 'Etes-vous certain?';
$lang['uninstalled'] = 'Module desinstalle.';
$lang['installed'] = 'Version %s du module installee.';
$lang['upgraded'] = 'Module mis à jour a la version %s.';
$lang['description'] = 'Fait un pont (propagation du login) entre FEU et AjaXplorer.';
$lang['modify_parameters'] = 'Parametres sauvegardes';

$lang['error'] = 'Erreur!';
$land['admin_title'] = 'Administration AjaXplorer';
$lang['admindescription'] = 'Gere la relation avec votre installation AjaXplorer';
$lang['accessdenied'] = 'Acces refuse. Verifiez les permissions';
$lang['Settings'] = 'Parametrage';
$lang['title_settings'] = 'Parametrage';
$lang['ajxp_realurl'] = 'Entrer l\'URL de votre AjaXplorer (http://domaine.com/ajxp)';
$lang['ajxp_secret'] = 'Entrez le code secret correspondant a celui du fichier de conf.php de ajxp';
$lang['ajxp_link_text'] = 'Entrez le texte du lien';
$lang['submit'] = 'Envoyer';
$lang['ajxp_auth_group'] = 'Selectionnez le groupe autorise';

$lang['changelog'] = '<ul>
<li>Version 0.1.5 - 14 September 2010. Initial Release.<br />Propagation de l\'identification FEU vers AjXp</li>
</ul>';

$lang['help'] = '<h3>Que fait ce module</h3>
<p>Genere un lien qui permet a vos utilisateur frontend d\'utiliser AjaXplorer, une application de partage de fichiers, sans se connecter a nouveau.</p>
<h3>Prerequis</h3>
<p>Il est fortement conseiller de bien se familiariser avec <a href="http://www.ajaxplorer.info/" target="_blanc">AjaXplorer(AjXp)</a> avant d\'installer ce module.<br />
Vous devez valider le bon fonctionnement de AjXp en utilsation standard avant de le connecter a CMSMS.<br />
Une bonne connaissance de FEU et des methodes de protection de contenu est indispensable.</p>
<h3>Comment l\'utiliser</h3>
<ol type="I">
<li> - Installez AjXp dans votre domaine ou un autre si vous partagez les acces avec votre bdd.</li>
<li> - Creez un groupe FEU des utilisateurs ayant acces a AjXp.
<li> - Modifiez le fichier de configuration de AjXp selon les indications du module auth.cmsms.</li>
<li> - Installez ce module dans votre CMSMS.</li>
<li> - Rendez-vous dans "Utilisateurs/Groupes >> Partage de fichiers" et renseignez les parametres.</li>
<li> - Inserez le tag {cms_module module=\'FEUajaxplorer\'} dans une page ou un gabarit protege ou non.</li>
</ol>
Le lien apparaitra automatiquement lorsqu\'un utilisateur du groupe autorise est connecte. Pour des raisons de performance, il est preferable de l\'inclure dans une page ou un menu protege.

<h3>Support</h3>
<p>As per the GPL, this software is provided as-is. Please read the text of the license for the full disclaimer.</p>
<h3>Copyright and License</h3>
<p>Copyright &copy; 2010, JC Ghio <a href="mailto:jcg@interphacepro.com">&lt;jcg@interphacepro.com&gt;</a>. All Rights Are Reserved.</p>
<p>This module has been released under the <a href="http://www.gnu.org/licenses/licenses.html#GPL">GNU Public License</a>. You must agree to this license before using the module.</p>';
?>
