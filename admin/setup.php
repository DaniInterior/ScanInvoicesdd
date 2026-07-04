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
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
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
$mesg = "";

$error = 0;
$setupnotempty = 0;

/*
 * Actions
 */

if ((float) DOL_VERSION >= 6) {
	include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';
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
} elseif ($action == 'set') {
	$array = ['SCANINVOICES_EMAIL', 'SCANINVOICES_URI', 'SCANINVOICES_PASS_API'];
	$changes = false;
	foreach ($array as $key) {
		$oldvalue = getDolGlobalString($key);
		$value = rtrim(GETPOST($key), '/');
		if ($value != $oldvalue) {
			dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
			$changes = true;
		}
	}
	if ($changes) {
		dolibarr_set_const($db, 'SCANINVOICES_KEY_API', '', 'chaine', 0, '', $conf->entity);
	}
}

$defaultURI = "https://ocr.cap-rel.fr";
if (!empty(getDolGlobalString('SCANINVOICES_URI'))) {
	$defaultURI = getDolGlobalString('SCANINVOICES_URI');
}
$defaultEmail = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
if (!empty(getDolGlobalString('SCANINVOICES_EMAIL'))) {
	$defaultEmail = getDolGlobalString('SCANINVOICES_EMAIL');
}
$defaultPassword = "HackMePleaseButHackMeSoft";
if (!empty(getDolGlobalString('SCANINVOICES_PASS_API'))) {
	$defaultPassword = getDolGlobalString('SCANINVOICES_PASS_API');
}
$resetPasswordLink = '';

if ($action == 'checkConnectAPI') {
	//Note: in case of remote api key removed or disabled, local api is set but can't be used anymore
	if (!empty(getDolGlobalString('SCANINVOICES_KEY_API'))) {
		if (scaninvoicesApiTryLoginWithAPIKey()) {
			//Ok
		} else {
			//set emptyn then will catch by next if :)
			dolibarr_set_const($db, "SCANINVOICES_PASS_API", '', 'chaine', 0, '', $conf->entity);
			$conf->global->SCANINVOICES_KEY_API = "";
		}
	}

	if (empty(getDolGlobalString('SCANINVOICES_KEY_API'))) {
		//Si ce compte utilisateur existe déjà
		dol_syslog("ScanInvoices:  : scaninvoicesApiTryLoginWithUserPass");
		switch (scaninvoicesApiTryLoginWithUserPass()) {
			case -2:
				dol_syslog("ScanInvoices:: scaninvoicesApiTryLoginWithUserPass (-2)");
			break;
			case 200:
				//SCANINVOICES_KEY_API is set in scaninvoicesApiTryLoginWithUserPass
				dol_syslog("ScanInvoices:: scaninvoicesApiTryLoginWithUserPass (1)");
			break;
			case 400:
			case 401:
				dol_syslog("ScanInvoices:: scaninvoicesApiTryLoginWithUserPass (2) login or password does not match, maybe new account ?");
				if (scaninvoicesApiCreateAccount()) {
					dol_syslog("ScanInvoices:  : scaninvoicesApiCreateAccount");
				} else {
					//this account exist but that password does not match -> add forgot password link
					$defaultURI = getDolGlobalString('SCANINVOICES_URI');
					$defaultEmail = getDolGlobalString('SCANINVOICES_EMAIL');
					$resetPasswordLink = "<a class='butAction' href='" . $defaultURI .  "/forgot-password?email=" . $defaultEmail . "' target='_blank'>" . $langs->trans("SCANINVOICES_PASS_APITooltipResetPass") . "</a>";
				}
			break;
			default:
		}
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
print dol_get_fiche_head($head, 'account', '', -1, "scaninvoices@scaninvoices");

//List of php functions (depends)
$phpListUsedFunctions = ['finfo_open'];
$phpListExtensions = ['intl', 'fileinfo', 'gd','mbstring'];
$loadedList = get_loaded_extensions();
$moduleError = 0;
foreach ($phpListExtensions as $extension) {
	if (!in_array($extension, $loadedList)) {
		//old code extension_loaded($extension)
		echo "<p>ERROR: $extension is missing, please install and load it</p>";
		$moduleError++;
	}
}
foreach ($phpListUsedFunctions as $function) {
	if (!function_exists($function)) {
		echo "<p>ERROR: $function is missing, please install and load it</p>";
		$moduleError++;
	}
}

if ($moduleError > 0) {
	print dol_get_fiche_end();
	llxFooter();
	exit;
}

// Setup page goes here
echo '<span class="opacitymedium">' . $langs->trans("ScanInvoicesSetupPage") . '</span><br><br>';

echo "<p>Dolibarr IP: " . scaninvoicesGetMyIP() . "</p>";

if ($resetPasswordLink != "") {
	print "<div id='resetPassDiv'>";
	print "<p>" .  $langs->trans("SCANINVOICES_EMAIL_USED_RESETPASS", $defaultEmail, $defaultURI) . "</p>";
	print "<p><b>" . $resetPasswordLink . "</b></p>";
	print "</div>";
} else {
	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="set">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td class="">' . $langs->trans("Parameter") . '</td><td>' . $langs->trans("Value") . '</td></tr>';

	print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_URI") . "</b><br /><i>" . $langs->trans("SCANINVOICES_URITooltip") . '</i></td>';
	print '<td>';
	print '<input type="text" name="SCANINVOICES_URI" value="' . $defaultURI . '" class="minwidth300" onchange="formChange();">';
	print '</td>';
	print '</tr>';

	print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_EMAIL") . "</b><br /><i>" . $langs->trans("SCANINVOICES_EMAILTooltip") . '</i></td>';
	print '<td>';
	print '<input type="text" name="SCANINVOICES_EMAIL" value="' . $defaultEmail . '" class="minwidth300" onchange="formChange();">';
	print '</td>';
	print '</tr>';

	print '<tr class="oddeven"><td class=""><b>' . $langs->trans("SCANINVOICES_PASS_API") . "</b><br /><i>" . $langs->trans("SCANINVOICES_PASS_APITooltip") .'</i></td>';
	print '<td>';
	print '<input type="password" name="SCANINVOICES_PASS_API" value="' . $defaultPassword . '" class="minwidth300" onchange="formChange();">';
	print '</td>';
	print '</tr>';

	print '</table>';

	print '<br><div class="right">';

	$btnDefaultStatus = "";
	if ($defaultEmail != "" && $defaultPassword != "" && $defaultURI != "") {
		$btnDefaultStatus = "style='visibility: hidden;'";
	}

	print '<input id="saveBtn" class="button button-save" type="submit" value="' . $langs->trans("Save") . '" ' . $btnDefaultStatus . '>';

	print '<a id="checkConnectBtn" class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=checkConnectAPI&token='.newToken().'">' . $langs->trans("CheckConnectToScanInvoices") . '</a>';

	print '</div>';

	print '</form>';
	print '<br>';

	print '</div>';

	print "<script language='javascript'>function formChange(){ $('#checkConnectBtn').remove(); $('#saveBtn').css('visibility', 'visible'); }</script>";
}

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
