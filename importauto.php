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
 *	\file       scaninvoices/importauto.php
 *	\ingroup    scaninvoices
 *	\brief      Home page of scaninvoices top menu.
 */

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


$permissiontoaccess = $user->rights->scaninvoices->read;
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
// Security check - Protection if external user
if ($user->socid > 0) {
	accessforbidden();
}
if ($user->socid > 0) {
	$socid = $user->socid;
}
// $isdraft = (($object->statut == $object::STATUS_DRAFT) ? 1 : 0);
$result = restrictedArea($user, 'scaninvoices', 0, '', '', 'fk_soc', 'rowid', 0);
if (empty($permissiontoaccess)) {
	accessforbidden();
}
foreach ($otherModulesRights as $perm) {
	if (empty($perm)) {
		accessforbidden($langs->trans('ScanInvoicesNeedPerms'));
	}
}


require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
dol_include_once('/scaninvoices/class/filestoimport.class.php');

// Load translation files required by the page
$langs->loadLangs(['scaninvoices@scaninvoices']);

$action = GETPOST('action', 'alpha');

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
	'/scaninvoices/css/index.css?ver=' . filemtime('css/index.css'),
	'/scaninvoices/css/driver.min.css?ver=' . filemtime('css/driver.min.css'),
);
$nomain = $mesg = "";
llxHeader('', 'ScanInvoices - Automatic Import', '', '', 0, 0, $arrayofjs, $arrayofcss, '', '', $nomain, 0);

echo load_fiche_titre($langs->trans('ScanInvoicesArea'), $langs->transnoentities('ScanInvoicesHelp', "<a href='https://doc.cap-rel.fr/projet_scaninvoices/'>", "</a"));

// print '<div class="fichecenter"><div class="fichethirdleft">';

if (isset($_POST['filenamePDF'])) {
	//Un pseudo file name : le nom du fournisseur pour pouvoir réapplique ce modele par la suite :)
	$filename = basename($_POST['fournisseur']);
}


//Un fichier déjà présent dans le stockage dolibarr (exemple fichier issu d'un import peppol)
if (isset($_GET['localFileName'])) {
	$localFileName = basename($_GET['localFileName']);
	include 'importauto-local.php';
} else {
	include 'importauto-form.php';
}

//Fix #38: Verifications de configuration qui pourrait empêcher le bon fonctionnement du module
//champs obligatoires pour créer un tiers
$array_to_check = array('IDPROF1', 'IDPROF2', 'IDPROF3', 'IDPROF4', 'IDPROF5', 'IDPROF6', 'EMAIL');
$langs->load("errors");
$error = 0;
$errors = array();
foreach ($array_to_check as $key) {
	if ($mysoc->country_id > 0) {
		$idprof_mandatory = 'SOCIETE_' . $key . '_MANDATORY';
		if (!empty(getDolGlobalString($idprof_mandatory))) {
			$error++;
			$errors[] = $langs->trans("ErrorProdIdIsMandatory", strtolower($key));
		}
	}
}
if ($error) {
	$mesg .= $langs->trans("DolibarrSetupRulesForCompanies") . ": ";
	$mesg .= implode(', ', $errors);
}

if (empty(getDolGlobalString('SCANINVOICES_DISABLE_WARNING'))) {
	if ($mesg) {
		setEventMessages($mesg, [], 'errors');
	}
}

// End of page
llxFooter();
$db->close();
