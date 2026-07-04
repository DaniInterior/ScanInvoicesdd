<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2023 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2020-2021 Eric Seigne			<eric.seigne@cap-rel.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       scaninvoices/scaninvoices_thirdparty.php
 *	\ingroup    scaninvoices
 *	\brief      embedded into third parts
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	--$i;
	--$j;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
// Try main.inc.php using relative path
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	exit('Include of main fails');
}


$permissiontoaccess = $user->rights->scaninvoices->read;
$permissiontoadd = $user->rights->scaninvoices->write;
$permissiontodelete = $user->rights->scaninvoices->delete;

/*
Note: vérification des droits associés et nécessaires:
	- lire les tiers societe->lire;
	- créer des tiers societe->creer
	- etendre societe->client->voir
	- lire les fournisseurs (?) fournisseur->lire
	- lire les factures fournisseur (recherche doublons) fournisseur->facture->lire
	- créer facture fournisseur fournisseur->facture->creer
	- lire les produits (?) produit->lire
	- lire les services service->lire
	.../...?
*/
$otherModulesRights = [
	$user->rights->societe->lire,
	$user->rights->societe->creer,
	$user->rights->societe->client->voir,
	$user->rights->fournisseur->lire,
	$user->rights->fournisseur->facture->lire,
	$user->rights->fournisseur->facture->creer,
	$user->rights->produit->lire,
	$user->rights->service->lire
];
require_once DOL_DOCUMENT_ROOT."/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT."/societe/class/societe.class.php";
require_once DOL_DOCUMENT_ROOT."/contact/class/contact.class.php";

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
dol_include_once('/scaninvoices/class/settings.class.php');
dol_include_once('/scaninvoices/lib/scaninvoices_settings.lib.php');


// Load translation files required by the page
$langs->load("companies");
$langs->loadLangs(['scaninvoices@scaninvoices']);

// Get parameters
$socid = GETPOST('socid', 'int')?GETPOST('socid', 'int') : null;
$objid = GETPOST('id', 'int')?GETPOST('id', 'int') : null;
$action = GETPOST('action', 'aZ09');
$mesg='';

// Protection if external user
if ($user->socid > 0) {
	accessforbidden();
}

/*
 * Creation de l'objet fournisseur correspondant au socid
 */

$societe = new Societe($db);
$socresult = $societe->fetch($socid);
// $object shuold be the thirdparty. Because it is not in this page, we force $soc that is used by some modules as alternative var into their condition for module_parts features.
$soc = $societe;

$object = new Settings($db);
$resultAll = $object->fetchAll('', '', 0, 0, array('customsql'=>"t.fk_soc=$socid"));
//print json_encode($resultAll);
if ($resultAll) {
	$object = reset($resultAll);
	// print json_encode($object);
}

if (empty($action) && empty($objid)) $action = 'view';

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

$permissiontoaccess = $user->rights->scaninvoices->read;

// Security check
// $isdraft = (($object->statut == $object::STATUS_DRAFT) ? 1 : 0);
$result = restrictedArea($user, 'scaninvoices', 0, '', '', 'fk_soc', 'rowid', 0);
$result = restrictedArea($user, 'societe', $societe->id, '&societe');
if (empty($permissiontoaccess)) accessforbidden();
foreach ($otherModulesRights as $perm) {
	if (empty($perm)) {
		accessforbidden($langs->trans('ScanInvoicesNeedPerms'));
	}
}




/*
 * Actions
 */

// Set url to go back after a create successfull
$backtopage = dol_buildpath('/scaninvoices/scaninvoices_thirdparty.php', 1).'?socid='.$socid;

include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);
$formproject = new FormProjets($db);

$title = $langs->trans("ScanInvoices");
$help_url = '';
llxHeader('', $title, $help_url);

// Example : Adding jquery code
print '<script type="text/javascript" language="javascript">
jQuery(document).ready(function() {
	function init_myfunc()
	{
		jQuery("#myid").removeAttr(\'disabled\');
		jQuery("#myid").attr(\'disabled\',\'disabled\');
	}
	init_myfunc();
	jQuery("#mybutton").click(function() {
		init_myfunc();
	});
});
</script>';

$head = societe_prepare_head($societe);

dol_fiche_head($head, 'tabScanInvoices', $langs->trans("ThirdParty"), -1, 'company');

// Part to create
if ($action == 'create') {
	print load_fiche_titre($langs->trans("NewObject", $langs->transnoentitiesnoconv("Settings")), '', 'object_'.$object->picto);

	print '<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?socid='.$socid.'" method="POST">';

	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	// if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

	print dol_get_fiche_head(array(), '');

	// Set some default values
	//if (! GETPOSTISSET('fieldname')) $_POST['fieldname'] = 'myvalue';

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	unset($object->fields['label']);				// Hide field already shown in banner
	unset($object->fields['status']);				// Hide field already shown in banner
	// unset($object->fields['yml']);				// Hide field already shown in banner
	unset($object->fields['fk_soc']);					// Hide field already shown in banner
	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';
	print '<input type="hidden" name="status" value="' . $object::STATUS_VALIDATED . '">';
	print '<input type="hidden" name="fk_soc" value="' . $socid . '">';

	// Other attributes
	// include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	// print '<tr class="oddeven"><td>';
	// print $langs->trans("ProductGenericToUseForImport") . "<br />";
	// print $langs->trans("KeepEmptyToSaveLinesAsFreeLines");
	// print '</td><td>';
	// $form->select_produits($objid,'DEFAULT_PRODUCT_ID', '', 0, 0, -1);
	// print '</td></tr>';
	print '</table>'."\n";

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" name="add" value="'.dol_escape_htmltag($langs->trans("Create")).'">';
	print '&nbsp; ';
	print '<input type="'.($backtopage ? "submit" : "button").'" class="button button-cancel" name="cancel" value="'.dol_escape_htmltag($langs->trans("Cancel")).'"'.($backtopage ? '' : ' onclick="javascript:history.go(-1)"').'>'; // Cancel for create does not post form if we don't know the backtopage
	print '</div>';

	print '</form>';

	dol_set_focus('input[name="ref"]');
}

// Part to edit record
if ($objid && $socid && $action == 'edit') {
	print load_fiche_titre($langs->trans("Settings"), '', 'object_'.$object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="socid" value="' . $socid . '">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	// if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	unset($object->fields['label']);				// Hide field already shown in banner
	unset($object->fields['status']);				// Hide field already shown in banner
	// unset($object->fields['yml']);				// Hide field already shown in banner
	unset($object->fields['fk_soc']);					// Hide field already shown in banner
	// Common attributes

	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	// include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center"><input type="submit" class="button button-save" name="save" value="'.$langs->trans("Save").'">';
	print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';

	print '</form>';
}

// Part to show record
if ($action == "view") {
	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/scaninvoices/settings_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '';
	dol_banner_tab($societe, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	// Common attributes
	//$keyforbreak='fieldkeytoswitchonsecondcolumn';	// We change column just before this field
	unset($object->fields['label']);				// Hide field already shown in banner
	// unset($object->fields['yml']);				// Hide field already shown in banner
	unset($object->fields['fk_soc']);					// Hide field already shown in banner

	// print json_encode($object);

	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

	// Buttons for actions

	print '<div class="tabsAction">'."\n";
	$parameters = array();
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

	print dolGetButtonAction($langs->trans('ImportAnInvoice'), '', 'default', 'importinvoice.php?step=2&fournID='.$societe->id, '', $permissiontoadd);

	if ($object->id > 0) {
		print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER["PHP_SELF"].'?socid='.$societe->id.'&id='.$object->id.'&action=edit', '', $permissiontoadd);
	} else {
		print dolGetButtonAction($langs->trans('Setup'), '', 'default', $_SERVER["PHP_SELF"].'?socid='.$societe->id.'&action=create', '', $permissiontoadd);
	}

	print '</div>'."\n";
}

// $head = societe_prepare_head($object);
// dol_fiche_head($head, 'tabScanInvoices', $langs->trans("ThirdParty"), 0, 'company');

// if ($mesg) {
// 	if (preg_match('/class="error"/', $mesg)) dol_htmloutput_mesg($mesg, '', 'error');
// 	else {
// 		dol_htmloutput_mesg($mesg, '', 'ok', 1);
// 		print '<br>';
// 	}
// }

// dol_banner_tab($object, 'id', '', $user->rights->user->user->lire || $user->admin);

// print '<div class="underbanner clearboth"></div>';

// //
// // Build and execute select
// // --------------------------------------------------------------------
// $sql = 'SELECT * ';
// $sql .= " FROM ".MAIN_DB_PREFIX.$object->table_element." as t";
// $sql .= " WHERE fk_soc = '" . $socid . "'";

// $resql = $db->query($sql);
// $resql = $db->query($sql);

// $obj = null;
// $objid = null;
// if ($resql) {
// 	$obj = $db->fetch_object($resql);
// 	$objid = $obj->rowid;
// }

// // Product to import on
// print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '">';
// print '<input type="hidden" name="token" value="'.newToken().'">';
// print '<input type="hidden" name="action" value="save">';
// print '<input type="hidden" name="socid" value="' . $socid . '">';
// if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
// if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

// print '<table>';
// print '<tr class="oddeven"><td>';
// print $langs->trans("ProductGenericToUseForImport") . '</td><td>';
// $form->select_produits($objid,
// 	'DEFAULT_PRODUCT_ID', '', 0, 0, -1);
// print '<td>';
// print $langs->trans("KeepEmptyToSaveLinesAsFreeLines");
// print '</td></tr>';
// print '<tr class="oddeven"><td>';

// print '</td></tr>';
// print '</table>';

// print '<div class="center"><input type="submit" class="button" value="' . $langs->trans("Save") . '"></div>';

// print '</form>';

// End of page
llxFooter();
$db->close();
