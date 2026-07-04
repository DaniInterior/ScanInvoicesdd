<?php

/**
 * functions.php.
 *
 * Copyright (c) 2021 Eric Seigne <eric.seigne@cap-rel.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
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
// if(is_object($object)) {
// 	$result = restrictedArea($user, 'scaninvoices', $object->id, '', '', 'fk_soc', 'rowid', $isdraft);
// }
if (empty($permissiontoaccess)) {
	accessforbidden();
}
foreach ($otherModulesRights as $perm) {
	if (empty($perm)) {
		accessforbidden($langs->trans('ScanInvoicesNeedPerms'));
	}
}

require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT . '/expensereport/class/expensereport.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
dol_include_once('/scaninvoices/class/settings.class.php');

dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');

const JSON_TYPE = 'json';
const JSON_MIME_TYPE = 'application/json';
const HTML_TYPE = 'html';
const HTML_MIME_TYPE = 'text/html';
const TEXT_TYPE = 'text';

$middlewares = [];
$middlewaresIndex = 0;

function router($httpMethods, $route, $callback, $exit = true)
{
	if (!in_array($_SERVER['REQUEST_METHOD'], (array) $httpMethods)) {
		return;
	}

	$path = parse_url($_SERVER['REQUEST_URI'])['path'];

	$scriptName = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
	$len = strlen($scriptName);
	if ($len > 0 && $scriptName !== '/') {
		$path = substr($path, $len);
	}

	$matches = null;
	$regex = '/' . str_replace('/', '\/', $route) . '/';

	if (!preg_match_all($regex, $path, $matches)) {
		return;
	}

	if (empty($matches)) {
		$callback();
	} else {
		$params = [];
		foreach ($matches as $k => $v) {
			if (!is_numeric($k) && !isset($v[1])) {
				$params[$k] = $v[0];
			}
		}
		$callback($params);
	}

	if ($exit) {
		exit;
	}
}

function _next($res)
{
	global $middlewares, $middlewaresIndex;

	if ($middlewares[$middlewaresIndex]) {
		$middlewares[$middlewaresIndex++](
			$_SERVER,
			$res,
			function () use (&$res, &$middlewaresIndex, &$middlewares) {
				if ($middlewaresIndex == count($middlewares)) {
					if ($res['dataType'] == JSON_TYPE) {
						echo json_encode($res['body']);
					} else {
						echo $res['body'];
					}
				} else {
					_next($res);
				}
			}
		);
	}
}

function middleware($func)
{
	global $middlewares;
	array_push($middlewares, $func);
}

function entry($endpoint, $params)
{
	$baseVerb = getenv('BASE_VERB');

	return "^/{$baseVerb}/{$endpoint}/{$params}$";
}

function json($arr)
{
	header('Content-Type: ' . JSON_MIME_TYPE);
	_next(
		[
			'body' => $arr,
			'dataType' => JSON_TYPE,
		]
	);
}

function html($code)
{
	header('Content-Type: ' . HTML_MIME_TYPE);
	_next(
		[
			'body' => $code,
			'dataType' => HTML_TYPE,
		]
	);
}

function error($msg)
{
	header('HTTP/1.0 404 Not Found');
	_next(
		[
			'body' => $msg,
			'dataType' => TEXT_TYPE,
		]
	);
}
