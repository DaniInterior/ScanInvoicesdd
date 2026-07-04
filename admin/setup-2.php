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
require_once DOL_DOCUMENT_ROOT.'/includes/sabre/autoload.php';
use Sabre\DAV\Client;

// Translations
$langs->loadLangs(array("admin", "scaninvoices@scaninvoices"));
$mesg = ""; $mesg_type="";
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
	$array = ['SCANINVOICES_IMPORT_SHARE_ENABLE','SCANINVOICES_IMPORT_SHARE_TYPE', 'SCANINVOICES_IMPORT_SHARE_URI',
			  'SCANINVOICES_IMPORT_SHARE_LOGIN','SCANINVOICES_IMPORT_SHARE_PASS', 'SCANINVOICES_IMPORT_SHARE_PORT',
			  'SCANINVOICES_IMPORT_SHARE_MAILREPORT'];
	foreach ($array as $key) {
		$value = GETPOST($key);
		dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
	}
}

$defaultSHAREchecked = "";
if (!empty(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_ENABLE'))) {
	$defaultSHAREchecked = " checked";
}

$defaultSHAREtype = "";
if (!empty(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_TYPE'))) {
	$defaultSHAREtype = getDolGlobalString('SCANINVOICES_IMPORT_SHARE_TYPE');
}

$html = "";
if ($action == 'checkConnectAPI') {
	$html = "<p>CONTENU DU PARTAGE DAVFS</p>";

	$settings = null;
	$path = "";
	if ($defaultSHAREtype == 1) {
		$settings = scaninvoicesConvertNextcloudURItoSettings();
		$path = "/public.php/webdav";
	} elseif ($defaultSHAREtype == 2) {
		$settings = scaninvoicesConvertSynologyURItoSettings();
		$path = "";
	}
	dol_syslog("ScanInvoices : Check if network share is available ...");
	if (!empty($settings)) {
		$client = new Client($settings);
		try {
			$res = $client->propfind($path, ['{DAV:}displayname','{DAV:}getcontentlength', '{DAV:}getcontenttype','{DAV:}getlastmodified'], 1);
			// print json_encode($res);
			$html .= "<ul>\n";
			foreach ($res as $key => $value) {
				if ($value) {
					$html .= "<li> $key ::". $value['{DAV:}getcontenttype'] . "::" . $value['{DAV:}getlastmodified'] . "</li>\n";
				}
			}
			$html .= "</ul>\n";
			$mesg = '<div class="ok">'.$langs->trans('CheckConnectOK');
			$mesg_type = 'ok';
		} catch (Exception $e) {
			$html .= "Erreur DAV." . $langs->trans('DAV_ERROR_DOUBLE_CHECK_DOCUMENTATION');
			$mesg = '<div class="error">'.$langs->trans('CheckConnectError') . ": ";
			$mesg .= $e->getMessage();
			$mesg .= "</div>";
			$mesg_type = 'error';
		}
		// $html .= json_encode($res);
		$html .= "<p>Fin de liste du partage webdav</p>";
	}
}



/*
 * View
 */

$form = new Form($db);

$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

$page_name = "ScanInvoicesSetupImportShare";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_scaninvoices@scaninvoices');

// Configuration header
$head = scaninvoicesAdminPrepareHead();
print dol_get_fiche_head($head, 'importshare', '', -1, "scaninvoices@scaninvoices");

// Setup page goes here
echo '<span class="opacitymedium">' . $langs->trans("ScanInvoicesSetupPageGED") . '</span><br><br>';

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="">' . $langs->trans("Parameter") . '</td><td>' . $langs->trans("Value") . '</td></tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_IMPORT_SHARE_ENABLE") . "</b><br /><i>" . $langs->trans("SCANINVOICES_IMPORT_SHARE_ENABLETooltip") . '</i></td>';
print '<td>';
print '<input type="checkbox" name="SCANINVOICES_IMPORT_SHARE_ENABLE" value="1" class="minwidth300"'.$defaultSHAREchecked.' onchange="formChange();">';
print '</td>';
print '</tr>';

$typepattern = array('1'=> 'Nextcloud', '2' => 'Synology DAV');
print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_IMPORT_SHARE_TYPE") . "</b><br /><i>" . $langs->trans("SCANINVOICES_IMPORT_SHARE_TYPETooltip") . '</i></td>';
print '<td>';
print $form->selectArray("SCANINVOICES_IMPORT_SHARE_TYPE", $typepattern, getDolGlobalString('SCANINVOICES_IMPORT_SHARE_TYPE'), 1, 0, 0, '', 0, 0, 0, '', 'width150');
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_IMPORT_SHARE_URI") . "</b><br /><i>" . $langs->trans("SCANINVOICES_IMPORT_SHARE_URITooltip") . '</i></td>';
print '<td>';
print '<input type="text" name="SCANINVOICES_IMPORT_SHARE_URI" value="' . getDolGlobalString('SCANINVOICES_IMPORT_SHARE_URI') . '" class="minwidth300" onchange="formChange();">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_IMPORT_SHARE_PORT") . "</b><br /><i>" . $langs->trans("SCANINVOICES_IMPORT_SHARE_PORTTooltip") . '</i></td>';
print '<td>';
print '<input type="text" name="SCANINVOICES_IMPORT_SHARE_PORT" value="' . getDolGlobalString('SCANINVOICES_IMPORT_SHARE_PORT') . '" class="minwidth300" onchange="formChange();">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_IMPORT_SHARE_LOGIN") . "</b><br /><i>" . $langs->trans("SCANINVOICES_IMPORT_SHARE_LOGINTooltip") . '</i></td>';
print '<td>';
print '<input type="text" name="SCANINVOICES_IMPORT_SHARE_LOGIN" value="' . getDolGlobalString('SCANINVOICES_IMPORT_SHARE_LOGIN') . '" class="minwidth300" onchange="formChange();">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_IMPORT_SHARE_PASS") . "</b><br /><i>" . $langs->trans("SCANINVOICES_IMPORT_SHARE_PASSTooltip") . '</i></td>';
print '<td>';
print '<input type="text" name="SCANINVOICES_IMPORT_SHARE_PASS" value="' . getDolGlobalString('SCANINVOICES_IMPORT_SHARE_PASS') . '" class="minwidth300" onchange="formChange();">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_IMPORT_SHARE_MAILREPORT") . "</b><br /><i>" . $langs->trans("SCANINVOICES_IMPORT_SHARE_MAILREPORTTooltip") . '</i></td>';
print '<td>';
print '<input type="text" name="SCANINVOICES_IMPORT_SHARE_MAILREPORT" value="' . getDolGlobalString('SCANINVOICES_IMPORT_SHARE_MAILREPORT') . '" class="minwidth300" onchange="formChange();">';
print '</td>';
print '</tr>';

print '</table>';

print '<br><div class="right">';

$btnDefaultStatus = "";
if ($defaultSHAREchecked != "" && !empty(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_TYPE')) && !empty(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_URI'))) {
	$btnDefaultStatus = "style='visibility: hidden;'";
}

print '<input id="saveBtn" class="button button-save" type="submit" value="' . $langs->trans("Save") . '" ' . $btnDefaultStatus . '>';

print '<a id="checkConnectBtn" class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=checkConnectAPI">' . $langs->trans("CheckConnectToScanInvoices") . '</a>';

print '</div>';

print '</form>';
print '<br>';

print '</div>';

print "<script language='javascript'>function formChange(){ $('#checkConnectBtn').remove(); $('#saveBtn').css('visibility', 'visible'); }</script>";

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

		print load_fiche_titre($langs->trans("NumberingModules2", $myTmpObjectKey), '', '');

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
							if ($module->version == 'development' && getDolGlobalString('MAIN_FEATURES_LEVEL < 2')) {
								continue;
							}
							if ($module->version == 'experimental' && getDolGlobalString('MAIN_FEATURES_LEVEL < 1')) {
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

print $html;

// Page end
print dol_get_fiche_end();

dol_htmloutput_mesg($mesg, [], $mesg_type);

llxFooter();
$db->close();
