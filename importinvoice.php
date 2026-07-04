<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *	\file       scaninvoices/scaninvoicesindex.php
 *	\ingroup    scaninvoices
 *	\brief      Home page of scaninvoices top menu.
 */

 /**
 * This file is a popup, so minimal footer please
 *
 * @ignore
 * @return	void
 */
function llxFooter()
{
	dol_htmloutput_events();
	print "</body>\n</html>";
}


// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
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
dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');


$permissiontoaccess = $user->rights->scaninvoices->read;

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
// Security check - Protection if external user
if ($user->socid > 0) {
	accessforbidden();
}
if ($user->socid > 0) {
	$socid = $user->socid;
}
// $isdraft = (($object->statut == $object::STATUS_DRAFT) ? 1 : 0);
$isdraft = 0;
$mesg = "";
$result = restrictedArea($user, 'scaninvoices', 0, '', '', 'fk_soc', 'rowid', $isdraft);
if (empty($permissiontoaccess)) {
	accessforbidden();
}
foreach ($otherModulesRights as $perm) {
	if (empty($perm)) {
		accessforbidden($langs->trans('ScanInvoicesNeedPerms'));
	}
}

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';

// Load translation files required by the page
$langs->loadLangs(['scaninvoices@scaninvoices']);

$action = GETPOST('action', 'alpha');
$step = GETPOST('step', 'int') ? GETPOST('step', 'int') : null;

// Security check
//if (! $user->rights->scaninvoices->myobject->read) accessforbidden();
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$max = 5;
$now = dol_now();

/*
 * Actions
 */

// None

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$arrayofjs = array(
		'/scaninvoices/js/driver.min.js?ver=' . filemtime('js/driver.min.js'),
		'/scaninvoices/js/zeroupload.js?ver=' . filemtime('js/zeroupload.js')
	);
$arrayofcss =  array(
		'/scaninvoices/css/driver.min.css?ver=' . filemtime('css/driver.min.css'),
		'/scaninvoices/css/index.css?ver=' . filemtime('css/index.css')
	);

if ($step == 2) {
	//sur oblyon dans le cas particulier de l'inversion des menu gauche/haut on force le menu en mode caché + slide
	//on force quelques paramètres pour retrouver une utilisation normale
	if (getDolGlobalString('MAIN_THEME') == 'oblyon' && getDolGlobalString('MAIN_MENU_INVERT') == 1) {
		$conf->global->OBLYON_HIDE_LEFTMENU = 1;
		$conf->global->OBLYON_FULLSIZE_TOPBAR = 1;
		$conf->global->THEME_ELDY_ENABLE_PERSONALIZED = 1;
		$conf->global->OBLYON_EFFECT_LEFTMENU = "slide";
		$conf->global->MAIN_IHM_PARAMS_REV += 1;
	} else {
		$conf->dol_hide_leftmenu = true;
	}
}

$nomain = " ";
llxHeader('', 'ScanInvoices - Manual Import', '', '', 0, 0, $arrayofjs, $arrayofcss, '', '', $nomain, 0);

// as a "popup"
// top_htmlhead('', 'ScanInvoices - Manual Import', 0, 0, $arrayofjs, $arrayofcss, 0, 0);
// print "<body>\n";

$filename = null;

$apiInfoFromServer = scaninvoicesApiGetInfoAboutWebservice();

if ($step != 2) {
	print '<div id="id-right">';
	print '<div class="fiche">';
}

if (!empty(getDolGlobalString('SCANINVOICES_PROTOCOL_MISSMATCH'))) {
	print '<div id="ocr-server-card" style="max-width: 350px; min-height: 40px; padding: 2em; border: 1px solid #888; background: #f8f8f8; text-align: left; margin: 3em auto;">';
	print $apiInfoFromServer;
	print '</div>';
	return;
}

//Si le nom du fichier est passé en GET c'est qu'on arrive de la page d'import automatique qui n'a pas marché
if (GETPOST('filenamePDF', 'alphanohtml', 1)) {
	$filename = basename(GETPOST('filenamePDF', 'alphanohtml', 1));
}

// if($filename === null) {
if ($step) {
	include 'container/step2.php';
} else {
	include 'container/step1.php';
}

if ($mesg != '') {
	setEventMessages($mesg, [], 'errors');
}

print "</div>\n";

if ($step != 2) {
	print "</div>\n";
	print "</div>\n";
}

// End of page
llxFooter();
$db->close();
