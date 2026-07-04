<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2021 Éric Seigne <eric.seigne@cap-rel.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    scaninvoices/admin/setup.php
 * \ingroup scaninvoices
 * \brief   ScanInvoices setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');
//require_once "../class/myclass.class.php";

// Translations
$mesg = ""; $mesg_type="";
$langs->loadLangs(array("admin", "scaninvoices@scaninvoices"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'scaninvoices';

$error = 0;
$setupnotempty = 0;

/*
 * Actions
 */

if ((float) DOL_VERSION >= 6) {
	include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';
}

if ($action == 'set') {
	$error = 0;
}

$defaultCREATEPRODUCTchecked = "";
if (!empty(getDolGlobalString('SCANINVOICES_IMPORT_CREATE_PRODUCT')) && getDolGlobalString('SCANINVOICES_IMPORT_CREATE_PRODUCT') == 1) {
	$defaultCREATEPRODUCTchecked = " checked";
}


if ($action == 'updateMask') {
	$maskconstorder = GETPOST('maskconstorder', 'alpha');
	$maskorder = GETPOST('maskorder', 'alpha');

	if ($maskconstorder) {
		$res = dolibarr_set_const($db, $maskconstorder, $maskorder, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), [], 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), [], 'errors');
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'SCANINVOICES_' . strtoupper($tmpobjectkey) . "_ADDON";
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
}
// Activate a model
elseif ($action == 'set') {
	$array = ['SCANINVOICES_DEFAULT_PRODUCT','SCANINVOICES_DEFAULT_LIVRAISON', 'SCANINVOICES_ADD_CATEG', 'SCANINVOICES_FILE_NAME', 'SCANINVOICES_FILE_NAME_PRE', 'SCANINVOICES_DISABLE_WARNING', 'SCANINVOICES_FORCE_SUPPLIER_SETTINGS_FROM_DOLIBARR'];
	foreach ($array as $key) {
		$value = GETPOST($key, 'alpha');
		dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
	}
}

/*
 * View
 */

$form = new Form($db);

$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

$page_name = "ScanInvoicesSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_scaninvoices@scaninvoices');

// Configuration header
$head = scaninvoicesAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', '', -1, "scaninvoices@scaninvoices");

// Setup page goes here
echo '<span class="opacitymedium">' . $langs->trans("ScanInvoicesSetupPage") . '</span><br><br>';


print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="">' . $langs->trans("Parameter") . '</td><td>' . $langs->trans("Value") . '</td></tr>';

$filenamepatern = array('1'=> $langs->trans("dol_object_ref"), '2' => $langs->trans("fourn_invoice_ref"));
print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_FILE_NAME") . "</b><br /><i>" . $langs->trans("SCANINVOICES_FILE_NAMETooltip") . '</i></td>';
print '<td>';
print $form->selectArray("SCANINVOICES_FILE_NAME", $filenamepatern, getDolGlobalString('SCANINVOICES_FILE_NAME'), 1);
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_FILE_NAME_PRE") . "</b><br /><i>" . $langs->trans("SCANINVOICES_FILE_NAME_PRETooltip") . '</i></td>';
print '<td>';
print '<input type="text" name="SCANINVOICES_FILE_NAME_PRE" value="' . getDolGlobalString('SCANINVOICES_FILE_NAME_PRE') . '" class="minwidth300">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_DEFAULT_PRODUCT") . "</b><br /><i>" . $langs->trans("SCANINVOICES_DEFAULT_PRODUCTTooltip") . '</i></td>';
print '<td>';
print scaninvoicesSelect_produits_fournisseurs_list(0, (int) getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT'), "SCANINVOICES_DEFAULT_PRODUCT", '', '', '', -1, 0, 0, 1, '', 0, '');
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_DEFAULT_LIVRAISON") . "</b><br /><i>" . $langs->trans("SCANINVOICES_DEFAULT_LIVRAISONTooltip") . '</i></td>';
print '<td>';
print scaninvoicesSelect_produits_fournisseurs_list(0, (int) getDolGlobalString('SCANINVOICES_DEFAULT_LIVRAISON'), "SCANINVOICES_DEFAULT_LIVRAISON", '', '', '', -1, 0, 0, 1, '', 0, '');
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_DISABLE_WARNING") . "</b><br /><i>" . $langs->trans("SCANINVOICES_DISABLE_WARNINGTooltip") . '</i></td>';
print '<td>';
echo '<input type="checkbox" name="SCANINVOICES_DISABLE_WARNING" value="1" class="minwidth300" '.(getDolGlobalInt('SCANINVOICES_DISABLE_WARNING') ? 'checked="checked"' : '').'>';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_FORCE_SUPPLIER_SETTINGS_FROM_DOLIBARR") . "</b><br /><i>" . $langs->trans("SCANINVOICES_FORCE_SUPPLIER_SETTINGS_FROM_DOLIBARRTooltip") . '</i></td>';
print '<td>';
echo '<input type="checkbox" name="SCANINVOICES_FORCE_SUPPLIER_SETTINGS_FROM_DOLIBARR" value="1" class="minwidth300" '.(getDolGlobalInt('SCANINVOICES_FORCE_SUPPLIER_SETTINGS_FROM_DOLIBARR') ? 'checked="checked"' : '').'>';
print '</td>';
print '</tr>';


//TODO - plus simple si on avait le form builder
// print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_ADD_CATEG") . "</b><br /><i>" . $langs->trans("SCANINVOICES_ADD_CATEGTooltip") . '</i></td>';
// print '<td>';
// print scaninvoicesSelect_produits_fournisseurs_list('',getDolGlobalString('SCANINVOICES_ADD_CATEG'),"SCANINVOICES_ADD_CATEG",'','','',-1,0,0,1,'',0,'');
// print '</td>';
// print '</tr>';


print '</table>';

print '<br><div class="right">';
print '<input class="button button-save" type="submit" value="' . $langs->trans("Save") . '">';

print '</div>';

print '</form>';
print '<br>';


print '</div>';


$moduledir = 'scaninvoices';
$myTmpObjects = array();
$myTmpObjects['ScanInvoices'] = array('includerefgeneration' => 0, 'includedocgeneration' => 0);


foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
	if ($myTmpObjectKey == 'ScanInvoices') {
		continue;
	}
	if ($myTmpObjectArray['includerefgeneration']) {
		/*
		 * Orders Numbering model
		 */
		$setupnotempty++;

		print load_fiche_titre($langs->trans("NumberingModules", $myTmpObjectKey), '', '');

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans("Name") . '</td>';
		print '<td>' . $langs->trans("Description") . '</td>';
		print '<td class="nowrap">' . $langs->trans("Example") . '</td>';
		print '<td class="center" width="60">' . $langs->trans("Status") . '</td>';
		print '<td class="center" width="16">' . $langs->trans("ShortInfo") . '</td>';
		print '</tr>' . "\n";

		clearstatcache();

		foreach ($dirmodels as $reldir) {
			$dir = dol_buildpath($reldir . "core/modules/" . $moduledir);

			if (is_dir($dir)) {
				$handle = opendir($dir);
				if (is_resource($handle)) {
					while (($file = readdir($handle)) !== false) {
						if (strpos($file, 'mod_' . strtolower($myTmpObjectKey) . '_') === 0 && substr($file, dol_strlen($file) - 3, 3) == 'php') {
							$file = substr($file, 0, dol_strlen($file) - 4);

							require_once $dir . '/' . $file . '.php';

							$module = new $file($db);

							// Show modules according to features level
							if ($module->version == 'development' && getDolGlobalString('MAIN_FEATURES_LEVEL') < 2) {
								continue;
							}
							if ($module->version == 'experimental' && getDolGlobalString('MAIN_FEATURES_LEVEL') < 1) {
								continue;
							}

							if ($module->isEnabled()) {
								dol_include_once('/' . $moduledir . '/class/' . strtolower($myTmpObjectKey) . '.class.php');

								print '<tr class="oddeven"><td>' . $module->name . "</td><td>\n";
								print $module->info();
								print '</td>';

								// Show example of numbering model
								print '<td class="nowrap">';
								$tmp = $module->getExample();
								if (preg_match('/^Error/', $tmp)) {
									$langs->load("errors");
									print '<div class="error">' . $langs->trans($tmp) . '</div>';
								} elseif ($tmp == 'NotConfigured') {
									print $langs->trans($tmp);
								} else {
									print $tmp;
								}
								print '</td>' . "\n";

								print '<td class="center">';
								$constforvar = 'SCANINVOICES_' . strtoupper($myTmpObjectKey) . '_ADDON';
								if (getDolGlobalString($constforvar) == $file) {
									print img_picto($langs->trans("Activated"), 'switch_on');
								} else {
									print '<a href="' . $_SERVER["PHP_SELF"] . '?action=setmod&token=' . newToken() . '&object=' . strtolower($myTmpObjectKey) . '&value=' . urlencode($file) . '">';
									print img_picto($langs->trans("Disabled"), 'switch_off');
									print '</a>';
								}
								print '</td>';

								$mytmpinstance = new $myTmpObjectKey($db);
								$mytmpinstance->initAsSpecimen();

								// Info
								$htmltooltip = '';
								$htmltooltip .= '' . $langs->trans("Version") . ': <b>' . $module->getVersion() . '</b><br>';

								$nextval = $module->getNextValue($mytmpinstance);
								if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
									$htmltooltip .= '' . $langs->trans("NextValue") . ': ';
									if ($nextval) {
										if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured') {
											$nextval = $langs->trans($nextval);
										}
										$htmltooltip .= $nextval . '<br>';
									} else {
										$htmltooltip .= $langs->trans($module->error) . '<br>';
									}
								}

								print '<td class="center">';
								print $form->textwithpicto('', $htmltooltip, 1, 0);
								print '</td>';

								print "</tr>\n";
							}
						}
					}
					closedir($handle);
				}
			}
		}
		print "</table><br>\n";
	}
}

// Page end
print dol_get_fiche_end();

dol_htmloutput_mesg($mesg);

llxFooter();
$db->close();
