<?php
/* Copyright (C) 2021 Éric Seigne <eric.seigne@cap-rel.fr>
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
 * \file    scaninvoices/lib/scaninvoices.lib.php
 * \ingroup scaninvoices
 * \brief   Library files with common functions for ScanInvoices.
 */
require_once DOL_DOCUMENT_ROOT . '/core/lib/geturl.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
dol_include_once('/scaninvoices/core/modules/modScanInvoices.class.php');
dol_include_once('/scaninvoices/class/filestoimport.class.php');
dol_include_once('/scaninvoices/class/settings.class.php');
dol_include_once('/scaninvoices/lib/scaninvoices_settings.lib.php');

if (isset($db)) {
	$tmpmodule = new modScanInvoices($db);
	if ($tmpmodule->version != getDolGlobalString('SCANINVOICE_MODULE_VERSION')) {
		setEventMessages($langs->trans("ErrorScanInvoiceModuleVersionDatabase"), [], 'errors');
	}
}

//getDolGlobalString('SCANINVOICES_KEY_API'),
// $scaninvoices_apikey = "1|yvAK40gVwQNVARMViUvCcztaH9XNhV57DeYGbHH2";
// $scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
// print "chargement de la lib, endpoint = $scaninvoices_endpoint\n";

function scaninvoicesMultibyte_trim($str)
{
	if (!function_exists('mb_trim') || !extension_loaded('mbstring')) {
		return preg_replace("/(^\s+)|(\s+$)/u", '', $str);
	} else {
		return mb_trim($str);
	}
}

/**
 * Prepare admin pages header.
 *
 * @return array
 */
function scaninvoicesAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load('scaninvoices@scaninvoices');

	$h = 0;
	$head = [];

	$head[$h][0] = dol_buildpath('/scaninvoices/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Account');
	$head[$h][2] = 'account';
	++$h;

	$head[$h][0] = dol_buildpath('/scaninvoices/admin/setup-1.php', 1);
	$head[$h][1] = $langs->trans('ScanInvoicesSettings');
	$head[$h][2] = 'settings';
	++$h;

	$head[$h][0] = dol_buildpath('/scaninvoices/admin/setup-2.php', 1);
	$head[$h][1] = $langs->trans('Import_share');
	$head[$h][2] = 'importshare';
	++$h;

	$head[$h][0] = dol_buildpath('/scaninvoices/admin/setup-3.php', 1);
	$head[$h][1] = $langs->trans('Import_lines');
	$head[$h][2] = 'importlines';
	++$h;

	// $head[$h][0] = dol_buildpath('/scaninvoices/admin/setup-3.php', 1);
	// $head[$h][1] = $langs->trans('GED & DAV');
	// $head[$h][2] = 'ged';
	// ++$h;

	$head[$h][0] = dol_buildpath('/scaninvoices/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	++$h;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@scaninvoices:/scaninvoices/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@scaninvoices:/scaninvoices/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'scaninvoices');

	return $head;
}

function scanInvoicesuserAgent()
{
	global $conf, $db, $modScanInvoices;

	if (empty($modScanInvoices) || $modScanInvoices->version === null) {
		dol_include_once('/scaninvoices/core/modules/modScanInvoices.class.php');
		$modScanInvoices = new modScanInvoices($db);
	}

	$uuid = dolibarr_get_const($db, "SCANINVOICES_UUID", 0);
	dol_syslog("Server UUID : $uuid");
	if ($uuid == "") {
		$uuid = uniqid();
		$result = dolibarr_set_const($db, "SCANINVOICES_UUID", $uuid, 'chaine', 0, '', 0);
		dol_syslog("Set server UUID $uuid");
	}

	return 'dolibarr/' . getDolGlobalString('MAIN_INFO_SOCIETE_NOM') . " (scaninvoices@" . $modScanInvoices->version . ") [" . $uuid . "]";
}

function scanInvoicesApiCommonHeader($withBearer = true, $isJson = true)
{
	global $conf;
	$curlHeaders[] = 'User-Agent: ' . scanInvoicesuserAgent();
	$curlHeaders[] = 'Accept: ' . 'application/json';
	if ($withBearer) {
		$curlHeaders[] = 'Authorization: ' . 'Bearer ' . getDolGlobalString('SCANINVOICES_KEY_API');
	}
	if ($isJson) {
		$curlHeaders[] = 'Content-Type: ' . 'application/json';
	}
	return $curlHeaders;
}

function scaninvoicesApiTryLoginWithAPIKey()
{
	global $conf, $mesg, $langs, $db;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
	$retour = false;

	$url = $scaninvoices_endpoint . '/api/profile';
	dol_syslog('ScanInvoices:scaninvoicesApiTryLoginWithAPIKey Try to log in ' . $url . ' with api key ...');
	$param = ['json' => ['email' => getDolGlobalString('SCANINVOICES_EMAIL')]];
	$result = getURLContent($url, 'POST', json_encode($param), 1, scanInvoicesApiCommonHeader(), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		// $json = json_decode($result['content']);
		// $html = $json->data->html;
		$mesg = '<div class="ok">' . $langs->trans('CheckConnectOK');
		$retour = true;
	} else {
		$mesg = '<div class="error">' . $langs->trans('CheckConnectError');
		$retour = false;
	}
	if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
		dol_syslog("CURL error message is " . $result['curl_error_msg']);
		$mesg .= '<br />' . $result['curl_error_msg'];
	}
	$mesg .= "</div>";
	return $retour;
}

function scaninvoicesApiCreateAPIKey()
{
	global $conf, $mesg, $langs, $db;
	$retour = false;
	return $retour;
}

function scaninvoicesApiCreateAccount()
{
	global $conf, $mesg, $langs, $db, $user;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
	$retour = false;

	//Note : special case header without api key
	$url = $scaninvoices_endpoint . '/api/register';
	dol_syslog('ScanInvoices:scaninvoicesApiCreateAccount Try to create account on ' . $url . ' ...');
	if ($user->firstname == '') {
		$user->firstname = 'anonymous';
	}
	if ($user->lastname == '') {
		$user->lastname = 'anonyname';
	}
	$param = [
		'firstname' => $user->firstname,
		'name' => $user->lastname,
		'email' => getDolGlobalString('SCANINVOICES_EMAIL'),
		'password' => getDolGlobalString('SCANINVOICES_PASS_API'),
		'password_confirmation' => getDolGlobalString('SCANINVOICES_PASS_API'),
	];
	$result = getURLContent($url, 'POST', json_encode($param), 1, scanInvoicesApiCommonHeader(false), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$json = json_decode($result['content']);
		// $html = $json->data->html;
		$result2 = dolibarr_set_const($db, 'SCANINVOICES_KEY_API', $json->access_token, 'chaine', 0, '', $conf->entity);
		$mesg = '<div class="ok">' . $langs->trans('CreateAccountOK') . '</div>';
		$retour = true;
	} else {
		$mesg = '<div class="error">' . $langs->trans('CreateAccountError');
		if (isset($result['content'])) {
			$mesg .= '<br />' . json_encode($result['content']);
		}
		$mesg .= '</div>';
		$retour = false;
	}
	if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
		$mesg .= '<br />' . @implode('', $result['curl_error_msg']);
	}

	return $retour;
}

function scaninvoicesApiTryLoginWithUserPass()
{
	global $conf, $mesg, $langs, $db;
	$scaninvoices_endpoint = scaninvoicesgetDolGlobalString('SCANINVOICES_URI', 'https://ocr.cap-rel.fr');
	$retour = -1;

	//Note special case, we do not use api key
	$url = $scaninvoices_endpoint . '/api/login';
	$email = scaninvoicesgetDolGlobalString('SCANINVOICES_EMAIL', '');
	$pass = scaninvoicesgetDolGlobalString('SCANINVOICES_PASS_API', '');
	if (empty($scaninvoices_endpoint) || empty($email) || empty($pass)) {
		// setEventMessages("error 2", [json_encode($conf->global)], 'errors');
		return -2;
	}

	dol_syslog('ScanInvoices:scaninvoicesApiTryLoginWithUserPass Try to log with user / pass account on ' . $url . ' ...');
	$param = [
		'email' => $email,
		'password' => $pass,
	];

	$result = getURLContent($url, 'POST', json_encode($param), 1, scanInvoicesApiCommonHeader(false), ['http', 'https'], 2);
	if (is_array($result)) {
		//var_dump($result);
		$retour = isset($result['http_code']) ? $result['http_code'] : -1;
		if ($result['http_code'] == 200 && isset($result['content'])) {
			$json = json_decode($result['content']);
			$result2 = dolibarr_set_const($db, 'SCANINVOICES_KEY_API', $json->access_token, 'chaine', 0, '', $conf->entity);
			$mesg = '<div class="ok">' . $langs->trans('CheckConnectOK');
		}
		if ($result['http_code'] == 401) {
			$mesg = '<div class="error">' . $langs->trans('LoginWithUserPassError');
		}
		if (isset($result['curl_error_no']) && !empty($result['curl_error_no'])) {
			$mesg .= 'Error ' . $result['curl_error_no'] . ' when calling URL ' . $url;
		}
		if (isset($result['curl_error_msg']) && !empty($result['curl_error_msg'])) {
			$mesg .= '<br />' . json_encode($result['curl_error_msg']);
		}

		if (isset($result['content']) && !empty($result['content']) && $result['http_code'] != 200) {
			$mesg .= '<br />' . json_encode($result['content']);
		}
		$mesg .= '</div>';
	}
	dol_syslog('ScanInvoices:scaninvoicesApiTryLoginWithUserPass return value ' . $retour);
	return $retour;
}

function scaninvoicesApiGetCompanyDetailsWithVatNumber($vatNumberArg)
{
	global $conf, $mesg, $langs, $db;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
	$retour = false;
	$vatNumber = preg_replace('/[\W]/', '', $vatNumberArg);

	if (strtolower(substr($vatNumber, 0, 2)) != "fr" && strtolower(substr($vatNumber, 0, 2)) != "be" && strtolower(substr($vatNumber, 0, 3)) != "che") {
		dol_syslog("scaninvoicesApiGetCompanyDetailsWithVatNumber :: Not a french,belgium or swiss VAT number, exit");
		return $retour;
	}

	$data = new stdClass();

	$url = $scaninvoices_endpoint . '/api/companyinfo/' . $vatNumber;
	dol_syslog('ScanInvoices:scaninvoicesApiGetCompanyDetailsWithVatNumber call data2invoices webservice for ' . $url . ' ...');
	$result = getURLContent($url, 'GET', '', 1, scanInvoicesApiCommonHeader(true, false), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);


	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		//{"siret":"0899.782.787","siren":null,"name":"COMPLIS","dateCreationEtablissement":"2008-08-07","adresse":"Avenue Abbaye d'Affligem",
		//"cp":"1300","ville":"Wavre","pays":"Belgium","codePays":"BE","numeroTvaIntra":"BE0899782787","activitePrincipale":null,"categorieJuridique":"015",
		//"longitude":null,"latitude":null,"tel":"","mail":"","web":"","logo":null,"source":"from local cache, data from kbopub.economie.fgov.be kbo-open-data, last update 09\/2021","error":""}
		$jsonR = json_decode($result['content']);
		$data->fournisseur = $jsonR->name;
		$data->fournisseurAddr1 = $jsonR->adresse;
		$data->fournisseurAddr2 = '';
		$data->fournisseurAddr3 = '';
		$data->fournisseurAddrCP = $jsonR->cp;
		$data->fournisseurAddrCity = $jsonR->ville;
		$data->fournisseurAddrCountry = $jsonR->pays;

		/** @phpstan-ignore-next-line */
		$countryCode = getCountry(0, 'all', $db, '', 1, $data->pays);
		if (is_array($countryCode)) {
			$countryCodetmp = $countryCode['code'];
			$countryCode = $countryCodetmp;
		}
		$data->fournisseurCountryCode = $countryCode;
		if ($data->fournisseurCountryCode == "") {
			$data->fournisseurCountryCode = $jsonR->codePays;
		}

		$data->fournisseurVAT = $jsonR->numeroTvaIntra;
		dol_syslog('scaninvoicesApiGetCompanyDetailsWithVatNumber OK');
	} else {
		dol_syslog('scaninvoicesApiGetCompanyDetailsWithVatNumber error 1');
	}
	if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
		$mesg .= '<br />' . $result['curl_error_msg'];
	}

	return $data;
}


/**
 * scaninvoicesApiRunInvoiceAnalyze call ocr remote server to extract data, that function is called via api.php and AJAX request from client
 *
 * @param   [Filestoimport]  $object            object to import
 * @param   [string]         $completefilename  full local file name
 * @param   [int]            $fourID            to force supplier (fournisseur)
 *
 * @return  [type]                     [return description]
 */
function scaninvoicesApiRunInvoiceAnalyze(Filestoimport $object, $completefilename, $fournID = -1)
{
	global $conf, $mesg, $langs, $db, $user;
	$fournisseurID = -1;

	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
	$retour = array('error' => '');

	if ($object) {
		$object->status = Filestoimport::STATUS_VALIDATED;
		$object->date_ocr_send = dol_now();
	}

	if (empty($completefilename) || !is_readable($completefilename)) {
		dol_syslog('ScanInvoices ERROR: file not found or not readable: ' . $completefilename, LOG_ERR);
		$retour['error'] = 'File not found or not readable: ' . $completefilename;
		return $retour;
	}
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($finfo, $completefilename);
	$path_parts = pathinfo($completefilename);

	$url = $scaninvoices_endpoint . '/api/ocrs';
	dol_syslog('ScanInvoices:scaninvoicesApiRunInvoiceAnalyze call data2invoices webservice for ' . $url . ' ...');
	dol_syslog('ScanInvoices:completefilename= ' . $completefilename . ', size=' . filesize($completefilename));
	$cf = new CURLFile($completefilename);
	$param = [
		'name' => $path_parts['extension'],
		'filename' => $path_parts['basename'],
		'Mime-Type' => $mimeType,
		'pdf' => $cf,
		'action' => 'default',
		'lang' => 'fra',
		'profile' => 'invoice'
	];

	/** @phpstan-ignore-next-line */
	$result = getURLContent($url, 'POST', $param, 1, scanInvoicesApiCommonHeader(true, false), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$dataJson = json_decode($result['content']);
		dol_syslog("Retour brut du serveur : " . $result['content']);
		//Pour coller a la structure de facturx
		$data = new stdClass();
		$data->fileName = $completefilename;

		//maybe a base64file was included inside file (pdf into xml peppol for example)
		dol_syslog('  search for base64_file data');
		if (isset($dataJson->meta)) {
			if (isset($dataJson->meta->base64_file)) {
				dol_syslog('  base64_file present ..., filename = ' . $dataJson->meta->base64_file_name);
				$data->base64_file = $dataJson->meta->base64_file ?? '';
				$data->base64_file_name = $dataJson->meta->base64_file_name ?? '';
			}
			if (isset($dataJson->meta->company_details->name)) {
				$data->fournisseur = $dataJson->meta->company_details->name ?? $dataJson->meta->supplier_name;
				$data->fournisseurAddr1 = $dataJson->meta->company_details->adresse ?? '';
				$data->fournisseurAddr2 = '';
				$data->fournisseurAddr3 = '';
				$data->fournisseurAddrCP = $dataJson->meta->company_details->cp ?? '';
				$data->fournisseurAddrCity = $dataJson->meta->company_details->ville ?? '';
				$data->fournisseurAddrCountry = ucfirst(strtolower($dataJson->meta->company_details->pays));
				// Try to guess the country code
				$countryCode = $dataJson->meta->company_details->codePays;	// We use the code returned by web service in priority
				if (empty($countryCode)) {
					// If country code still unknown, we try to guess it from the label of country
					/** @phpstan-ignore-next-line */
					$countryCode = getCountry(0, 'all', $db, '', 1, ucfirst(strtolower($dataJson->meta->company_details->pays)));
					if (is_array($countryCode)) {
						$countryCodetmp = $countryCode['code'];
						$countryCode = $countryCodetmp;
					}
				}
				$data->fournisseurCountryCode = $countryCode;
				$data->fournisseurVAT = $dataJson->meta->company_details->numeroTvaIntra ?? $dataJson->meta->supplier_numtva;
				if (empty($data->fournisseur)) {
					$data->fournisseurVAT = $dataJson->meta->supplier_numtva;
				}
				if (empty($data->fournisseurVAT)) {
					$data->fournisseurVAT = $dataJson->meta->supplier_name;
				}
			}
		} else {
			$data->fournisseur = scaninvoicesGetJsonValue($dataJson, 'meta->supplier_name', '');
			$data->fournisseurVAT = scaninvoicesGetJsonValue($dataJson, 'meta->supplier_numtva', '');
			$data->fournisseurAddr1 = '';
			$data->fournisseurAddr2 = '';
			$data->fournisseurAddr3 = '';
			$data->fournisseurAddrCP = '';
			$data->fournisseurAddrCity = '';
			$data->fournisseurAddrCountry = '';
			$data->fournisseurCountryCode = '';
		}

		//bug nom de fournisseur sans aucun caractères, exemple "----"
		if (!preg_match('/[a-z]/i', $data->fournisseur)) {
			$data->fournisseur = scaninvoicesGetJsonValue($dataJson, 'meta->supplier_name', '');
		}

		$data->fournisseurMail = '';
		$data->lines = null;
		// dol_syslog("Retour brut du serveur 2 : " . json_encode($dataJson->meta));
		if (isset($dataJson->meta->products)) {
			// dol_syslog("dataJson include products lines : " . json_encode($dataJson->meta->products));
			if (empty(getDolGlobalString('SCANINVOICES_DISABLE_IMPORT_LINES'))) {
				$data->lines = $dataJson->meta->products;
			}
		} else {
			// dol_syslog("dataJson does not include products lines ! ");
		}

		//Recherche si le fournisseur existe dans dolibarr : on utilise le num de TVA si il existe
		// dol_syslog('scaninvoicesApiRunInvoiceAnalyze Faut-il créer un fournisseur ? num_tva='.$data->fournisseurVAT);
		// dol_syslog('scaninvoicesApiRunInvoiceAnalyze data='.json_encode($data));
		if (!empty($data->fournisseurVAT) && ($data->fournisseurVAT != "")) {
			// dol_syslog("  ScanInvoices::Search supplier id from his vat number : " . $data->fournisseurVAT);
			$fournisseurID = scaninvoicesFournisseurIdfromVAT($data->fournisseurVAT);
			if ($fournisseurID > 0) {
				$retour['fourn'] = "$data->fournisseur";
			}
		}
		//TODO SIREN / SIRET
		if ($fournisseurID <= 0) {
			//On essaye sur un exact match name
			// dol_syslog("  ScanInvoices::Search supplier id from his exact name : " . $data->fournisseur);
			$fournisseurID = scaninvoicesFournisseurIdfromExactName($data->fournisseur);
			if ($fournisseurID > 0) {
				$retour['fourn'] = "$data->fournisseur";
			}
		}

		//En priorité le fournisseur passé dans le form d'upload
		if ($fournID > 0) {
			$fournisseurID = $fournID;
		}
		dol_syslog('scaninvoicesApiRunInvoiceAnalyze fournisseur ID (1) = ' . $fournisseurID);

		if ($fournisseurID <= 0) {
			//Creation du fournisseur si les données le permettent
			$creaID = scaninvoicesCreate_supplier($data);
			if ($creaID > 0) {
				$fournisseurID = $creaID;
				dol_syslog('scaninvoicesApiRunInvoiceAnalyze creation fournisseur');
				$retour['fourn'] = "Création du fournisseur $data->fournisseur #$fournisseurID";
			} else {
				dol_syslog('scaninvoicesApiRunInvoiceAnalyze ERREUR CREAF-001 : ' . json_encode($data) . " :: || :: " . json_encode($dataJson));
				$retour['message'] = scaninvoicesMessageErreurAnalyse('CREAF-001 : impossible de créer ce fournisseur', $data, $dataJson);
				$retour['error'] = scaninvoicesMessageErreurAnalyse('CREAF-001 : impossible de créer ce fournisseur', $data, $dataJson);
				$retour['fourn'] = scaninvoicesMessageErreurAnalyse('CREAF-001 : impossible de créer ce fournisseur', $data, $dataJson);
			}

			if ($object) {
				$object->date_ocr_return = dol_now();
				if ($retour['error'] != "") {
					$object->status = Filestoimport::STATUS_ERROR;
				}
				$object->message = strip_tags($retour['message']);
				$object->update($user);
			}
		}

		if ($fournisseurID > 0) {
			dol_syslog('scaninvoicesApiRunInvoiceAnalyze fournisseur id (2) =' . $fournisseurID);
			$data->fournisseurID = $fournisseurID;
			$data->ladate = $dataJson->meta->date;
			if (isset($dataJson->meta->due_date)) {
				$data->due_date = $dataJson->meta->due_date;
			} else {
				$data->due_date = $data->ladate;
			}

			//'Pour le détail de la facture ref #'.$dataJson->meta->invoice_number.' du '.$dataJson->meta->date.' voir le document joint.';
			$data->label = $dataJson->meta->invoice_label ?? '';
			$data->facture = $dataJson->meta->invoice_number;
			$data->ht = $dataJson->meta->amount_untaxed;
			$data->ttc = $dataJson->meta->amount;
			$data->fileName = $completefilename;
			$data->vatFromServer = scaninvoicesGetTaxesRateFromSrv($result);
			$data->exo_tax_base = $dataJson->meta->exo_tax_base;
			$data->eco_part = $dataJson->meta->eco_part;
			$data->delivery_untaxed = $dataJson->meta->delivery_untaxed ?? '';

			//Le produit par défaut s'il est configuré
			$defaultproduct = new Settings($db);
			$resultDefProAll = $defaultproduct->fetchAll('', '', 0, 0, array('customsql' => "t.fk_soc=$fournisseurID"));
			$defaultproduct = reset($resultDefProAll);
			if ($resultDefProAll && !empty($defaultproduct->fk_default_product)) {
				$data->defaultProductID = $defaultproduct->fk_default_product;
				dol_syslog('scaninvoicesApiRunInvoiceAnalyze produit/service par défaut (from supplier) id=' . $defaultproduct->fk_default_product);
			} elseif (!empty(getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT'))) {
				$data->defaultProductID = getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT');
				dol_syslog('scaninvoicesApiRunInvoiceAnalyze produit/service par défaut (from module conf) (b) id=' . $data->defaultProductID);
			} else {
				dol_syslog('importInvoice pas de produit/service par défaut pour ce fournisseur');
			}

			// dol_syslog('scaninvoicesApiRunInvoiceAnalyze  data (2) :'.json_encode($data));
			if (empty($data->facture)) {
				$retour['message'] = scaninvoicesMessageErreurAnalyse('CREAF-002 : impossible de créer cette facture', $data, $dataJson);
				$retour['error'] = scaninvoicesMessageErreurAnalyse('CREAF-002 : impossible de créer cette facture', $data, $dataJson);
				$retour['fourn'] = scaninvoicesMessageErreurAnalyse('CREAF-002 : impossible de créer cette facture', $data, $dataJson);
				return $retour;
			}

			$res = scaninvoicesCreate_fact_fournisseur($data);

			if ($object) {
				$object->date_ocr_return = dol_now();
				if (isset($res['factureid'])) {
					$object->fk_invoice = $res['factureid'];
				}
				$object->fk_supplier = $data->fournisseurID;
				if ($res['error'] > 0) {
					$retour['error'] = $res['message'];
					//Creation ou détection fournisseur ok -> partiel
					$object->status = Filestoimport::STATUS_HALFSUCCESS;
				} else {
					$retour['error'] = "";
					$retour['message'] = $res['message'];
					$object->status = Filestoimport::STATUS_CLOSED;
				}
				$object->message = strip_tags($res['message']);
				$object->update($user);
			}
			$retour['fact'] = $res['message'];
			$retour['justif'] = $res['justif'];
		}
	} else {
		$mesg = '<div class="error">' . $langs->trans('scaninvoicesApiRunInvoiceAnalyzeError', $scaninvoices_endpoint);
		if (isset($result['content'])) {
			$mesg .= '<br />' . $result['content'];
		}
		if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
			$mesg .= '<br />' . $result['curl_error_msg'];
		}
		$mesg .= '</div>';
		$retour['error'] = $mesg;

		// Detect OCR-unavailable conditions from the curl/http result so the
		// frontend can stop a chained-import loop instead of hammering the
		// server. Covers: network/curl failure, server error, missing payload.
		$curlErrorNo = isset($result['curl_error_no']) ? (int) $result['curl_error_no'] : 0;
		$httpCode = isset($result['http_code']) ? (int) $result['http_code'] : 0;
		$emptyPayload = !isset($result['content']) || $result['content'] === '';
		if ($curlErrorNo !== 0 || $httpCode === 0 || $httpCode >= 500 || $emptyPayload) {
			$retour['ocr_unavailable'] = true;
			$retour['ocr_unavailable_reason'] = sprintf(
				'curl_error_no=%d, http_code=%d, empty_payload=%s, curl_error_msg=%s',
				$curlErrorNo,
				$httpCode,
				$emptyPayload ? '1' : '0',
				isset($result['curl_error_msg']) ? $result['curl_error_msg'] : ''
			);
			dol_syslog('scaninvoicesApiRunInvoiceAnalyze: OCR service unavailable, signal frontend to stop chain :: ' . $retour['ocr_unavailable_reason'], LOG_WARNING);
		} else {
			dol_syslog('scaninvoicesApiRunInvoiceAnalyze: OCR call failed but not flagged unavailable (http_code=' . $httpCode . ', curl_error_no=' . $curlErrorNo . ')', LOG_WARNING);
		}
	}

	return $retour;
}


/**
 * scaninvoicesApiGetInfoAboutWebservice get informations about OCR webservice (available, free, heavy loaded ...)
 * that function is directly called from php script (ie not from api.php AJAX)
 *
 * @return  [type]  [return description]
 */
function scaninvoicesApiGetInfoAboutWebservice($format = 'html')
{
	global $conf, $mesg, $langs, $db;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');

	$module = new modScanInvoices($db);

	$html = "";
	$arr = "";
	$json = "";

	$url = $scaninvoices_endpoint . '/api/ruok';
	$param = [
		'json' => [
			'email' => getDolGlobalString('SCANINVOICES_EMAIL'),
			'protocol' => $module->protocol,
		]
	];

	dol_syslog('ScanInvoices:get_info_from_ocr_webservice ' . $url . ' with ' . json_encode($param));

	$result = getURLContent($url, 'GET', json_encode($param), 1, scanInvoicesApiCommonHeader(), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$arr = json_decode($result['content']);
		$json = $arr->data->json;
		$html = $arr->data->html;

		//check if protocol version is the same
		if ($json->protocol != $module->protocol) {
			$conf->global->SCANINVOICES_PROTOCOL_MISSMATCH = true;
			$html = "<div id=\"ocr-caprel-account-status\">
            <h3 align=\"center\"><a href=\"https://ocr.cap-rel.fr\" target=\"_blank\">OCR WebServices by CAP-REL</a></h3>
            <p>" . $langs->trans('DEFAULT_OCR_WEBSERVICE_PROTOCOL1') . " (srv=" . $json->protocol . ", local=" . $module->protocol . ")</p>
            <p><a href=\"https://ocr.cap-rel.fr/download/scaninvoice\" target=\"_blank\">" . $langs->trans('DEFAULT_OCR_WEBSERVICE_PROTOCOL2') . "</a></p>
            </div>";
		}
		//save json list of server languages
		if (isset($json->srvlangs)) {
			$html .= "<input type=\"hidden\" name=\"srvlangs\" value=\"" . base64_encode(json_encode($json->srvlangs)) . "\">\n";
		}
	} elseif (is_array($result) && $result['http_code'] == 503) {
		$html = "<div id=\"ocr-caprel-account-status\">
		<h3 align=\"center\">" . $langs->trans('OCR_SERVER_UPGRADE_IN_PROGRESS') . "...</h3>
		<p>" . $langs->trans('OCR_SERVER_UPGRADE_IN_PROGRESS_MESSAGE') . ")</p>
		</div>";
	} else {
		$html = "<div id=\"ocr-caprel-account-status\">
            <h3 align=\"center\"><a href=\"https://ocr.cap-rel.fr\" target=\"_blank\">OCR WebServices by CAP-REL</a></h3>
            <p>" . $langs->trans('DEFAULT_OCR_WEBSERVICE_MSG1') . "</p>
            <p><a href=\"https://ocr.cap-rel.fr/tarifs\" target=\"_blank\">" . $langs->trans('DEFAULT_OCR_WEBSERVICE_MSG2') . "</a></p>
            <p>" . $langs->trans('DEFAULT_OCR_WEBSERVICE_MSG3', scaninvoicesGetMyIP()) . "</p>
            </div>";
	}
	if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
		$mesg = '<div class="error">' . $langs->trans('scaninvoicesApiGetInfoAboutWebservice');
		$mesg .= '<br />' . $result['curl_error_msg'];
		$mesg .= '</div>';
	}

	if ($format == 'html') {
		return $html;
	}
	return $json;
}

/**
 * Ask server to get informations about sensitives zones for a supplier
 *
 * @return  [type]  [return description]
 */
function scaninvoicesApiGetInvoicesZonesForSupplier($vatNumberArg, $exactNameArg)
{
	global $conf, $mesg, $langs, $db;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
	$retour = array('error' => '');
	$vatNumber = preg_replace('/[\W]/', '', $vatNumberArg);
	$param = ['objectType' => 'invoices', 'vatNumber' => $vatNumber, 'name' => $exactNameArg];

	$url = $scaninvoices_endpoint . '/api/ocrcutszones';
	dol_syslog("ScanInvoices:scaninvoicesApiGetInvoicesZonesForSupplier call webservice for $url with $vatNumber and $exactNameArg");
	/** @phpstan-ignore-next-line */
	$result = getURLContent($url, 'POST', $param, 1, scanInvoicesApiCommonHeader(true, false), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);


	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$ret = json_decode($result['content']);
		if (isset($ret->result)) {
			$retour['result'] = $ret->result;
		}
		dol_syslog('scaninvoicesApiGetInvoicesZonesForSupplier OK');
	} else {
		dol_syslog('scaninvoicesApiGetInvoicesZonesForSupplier error 1');
	}
	if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
		dol_syslog("ScanInvoices:scaninvoicesApiGetInvoicesZonesForSupplier error Curl details " . $result['curl_error_msg']);
		$mesg = '<div class="error">' . $langs->trans('scaninvoicesApiGetInvoicesZonesForSupplier');
		$mesg .= '<br />' . $result['curl_error_msg'];
		$mesg .= '</div>';
		$retour['error'] = $mesg;
	}

	return $retour;
}



function scaninvoicesSlugify($text)
{
	// replace non letter or digits by -
	$text = preg_replace('~[^\pL\d]+~u', '-', $text);
	// transliterate
	$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
	// remove unwanted characters
	$text = preg_replace('~[^-\w]+~', '', $text);
	// trim
	$text = trim($text, '-');
	// remove duplicate -
	$text = preg_replace('~-+~', '-', $text);
	// lowercase
	$text = strtolower($text);
	if (empty($text)) {
		return 'n-a';
	}

	return $text;
}

function scaninvoicesClean_ladate($str)
{
	$listeFormatsPossibles = ['dd/MMMM/y', 'dd MMMM y', 'd.M.y'];
	foreach ($listeFormatsPossibles as $format) {
		$fmt = new IntlDateFormatter(
			'fr-FR',
			IntlDateFormatter::FULL,
			IntlDateFormatter::FULL,
			'Etc/UTC',
			IntlDateFormatter::GREGORIAN,
			$format
		);

		// parse
		if ($ts = $fmt->parse($str)) {
			$d = date('Y-m-d', $ts);
			dol_syslog("clean ladate $str -> $d");

			return $d;
		}
	}
	// setlocale(LC_TIME, 'fr_FR');
	// dol_syslog("clean ladate $str");
	// $listeFormatsPossibles = ['DD-MM-YYYY', 'DD MMMM YYYY', 'j F Y'];
	// foreach ($listeFormatsPossibles as $format) {
	//     dol_syslog("clean ladate on essaye $format");
	//     if ($date = DateTime::createFromFormat($format, $str)) {
	//         return $date->format("Y-m-d");
	//     }
	// }
	//a defaut on retourne ce qu'on a donné en entrée
	return $str;
}

function scaninvoicesValidateDate($date, $format = 'Y-m-d')
{
	$d = DateTime::createFromFormat($format, $date);

	return $d && $d->format($format) === $date;
}

//Netttoyage d'un nombre éventuellement accompagné d'un signe euro par exemple
function scaninvoicesClean_amount($str)
{
	global $langs;

	$str = str_replace(['−', '‐', '‑', '‒', '–', '—', '―'], '-', html_entity_decode($str));
	$number = preg_replace('/[^\d\.,-]/', '', $str);
	$res = price2num($number, 'MU');
	dol_syslog(" clean amount $str -> $number -> $res");
	return $res;

	// no more price2num($number); due to locale dolibarr settings, let me understand the pb please :)
	// $dec = ',';
	// $thousand = '';
	// if ($langs->transnoentitiesnoconv("SeparatorDecimal") != "SeparatorDecimal") {
	//     $dec = $langs->transnoentitiesnoconv("SeparatorDecimal");
	// }
	// if ($langs->transnoentitiesnoconv("SeparatorThousand") != "SeparatorThousand") {
	//     $thousand = $langs->transnoentitiesnoconv("SeparatorThousand");
	// }
	// if ($thousand == 'None') {
	//     $thousand = '';
	// } elseif ($thousand == 'Space') {
	//     $thousand = ' ';
	// }
	// $amount = number_format($number, 2, $dec, $thousand);
	// if ($thousand != ',' && $thousand != '.') $amount = str_replace(',', '.', $amount); // To accept 2 notations for french users
	// $amount = str_replace(' ', '', $amount); // To avoid spaces
	// $amount = str_replace($thousand, '', $amount); // Replace of thousand before replace of dec to avoid pb if thousand is .
	// $amount = str_replace($dec, '.', $amount);

	// dol_syslog(" clean amount $str -> $amount");

	// return $amount;
}

/**
 * scaninvoicesCreate_fact_fournisseur : création de la facture fournisseur.
 *
 * @param mixed $data
 *
 * @return array message, factureid, factureurl
 */
function scaninvoicesCreate_fact_fournisseur($data)
{
	global $db, $conf, $user, $mysoc, $langs;

	dol_syslog("Création d'une facture fournisseur pour : " . $data->fournisseur . " fournisseurid = " . $data->fournisseurID);
	$message = '';
	$factureurl = "";
	$factureid = null;
	$justif = null;
	$ret = null;

	$data->ttc = scaninvoicesClean_amount($data->ttc);

	$nberror = 0;
	//shortlan
	if (!is_numeric($data->ttc)) {
		dol_syslog("Erreur 001 : le ttc ($data->ttc) n'est pas un montant valide");
		return array(
			'message' => scaninvoicesMessageErreurAnalyse('FACTTC-001 : le montant TTC ne semble pas correct : ' . $data->ttc, $data),
			'error' => 10,
			'factureurl' => $factureurl,
			'factureid' => $factureid
		);
	}

	require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
	$facfou = new FactureFournisseur($db);

	$ref = $data->facture;
	$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . "facture_fourn WHERE fk_soc = " . ((int) $data->fournisseurID) . " AND ref_supplier = '" . $db->escape($ref) . "' AND entity IN (" . getEntity('facture_fourn') . ") LIMIT 1";
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		dol_syslog("  Recherche d un doublon (1) sur ref $ref ...");
		if ($num > 0) {
			$obj = $db->fetch_object($resql);
			$factureid = $obj->rowid;
			dol_syslog("  doublon trouvé: $factureid ...");
			$toto = $facfou->fetch($factureid);
			$factureurl = $facfou->getNomUrl(1, '', '', '', '', 0, 0, 0);
			$message .= "Facture existante : $data->fournisseur du $data->ladate -> $factureurl";
		}
	}

	if ($factureid == null) {
		dol_syslog('  Pas de doublon ...');
		$facfou->ref = $ref; //sera remplacee par dolibarr
		$facfou->ref_supplier = $ref; //La ref pour eviter les doublons
		$facfou->socid = $data->fournisseurID;
		$facfou->libelle = scaninvoicesClean_label($data->label);
		$facfou->date = strtotime(scaninvoicesDateFormating($data->ladate, 'Y-m-d'));
		//default = date
		if (isset($data->due_date)) {
			dol_syslog(' ScanInvoices: supplier invoice due date is set, use it');
			$facfou->date_echeance = strtotime(scaninvoicesDateFormating($data->due_date, 'Y-m-d'));
		} else {
			dol_syslog(' ScanInvoices: supplier invoice due date is not set, use same date as invoice date...');
			$facfou->date_echeance = $facfou->date;
		}
		$facfou->note_public = '';

		// Load fully the data of the thirdparty of invoice (we need it for VAT rate detection later)
		$facfou->fetch_thirdparty();
		//default condition reglement
		if (empty($facfou->cond_reglement_id) && !empty($facfou->thirdparty->cond_reglement_supplier_id)) {
			$facfou->cond_reglement_id = $facfou->thirdparty->cond_reglement_supplier_id;
			//fix in case of specific due_date
			if (!isset($data->due_date)) {
				$facfou->date_echeance = $facfou->calculate_date_lim_reglement();
			}
		}
		//default reglement id
		if (empty($facfou->mode_reglement_id) && !empty($facfou->thirdparty->mode_reglement_supplier_id)) {
			$facfou->mode_reglement_id = $facfou->thirdparty->mode_reglement_supplier_id;
		}
		//default payment account
		if (empty($facfou->fk_account) && !empty($facfou->thirdparty->fk_account)) {
			$facfou->fk_account = $facfou->thirdparty->fk_account;
		}

		//forcee everything from dolibarr thirdpart settings
		if (getDolGlobalString('SCANINVOICES_FORCE_SUPPLIER_SETTINGS_FROM_DOLIBARR')) {
			dol_syslog(' ScanInvoices: configuration is to force supplier payment settings from thirdpart settings...');

			if (!empty($facfou->thirdparty->fk_account)) {
				$facfou->fk_account = $facfou->thirdparty->fk_account;
			}
			if (!empty($facfou->thirdparty->mode_reglement_supplier_id)) {
				$facfou->mode_reglement_id = $facfou->thirdparty->mode_reglement_supplier_id;
			}
			if (!empty($facfou->thirdparty->cond_reglement_supplier_id)) {
				$facfou->cond_reglement_id = $facfou->thirdparty->cond_reglement_supplier_id;
				$facfou->date_echeance = $facfou->calculate_date_lim_reglement();
			}
		}

		$factureid = $facfou->create($user);

		if ($factureid > 0) {
			//A voir si on fait aussi le reglement ou pas...
			// $reglement = new PaiementFourn($db);
			// $reglement->amounts = $data->ttc;
			// $reglement->datepaye = $data->ladate;
			// $reglement->paiementid = paiementIdFromSlug($data->moyen_paiement->slug);
			// $reglement->create($user, $facid, $user->socid);
			// $facfou->set_paid($user);

			//TODO gestion des multi taux de TVA
			dol_syslog(' Fact. fournisseur avec un seul taux de TVA');
			$label = scaninvoicesClean_label($data->label);

			$amount = scaninvoicesClean_amount($data->ttc);
			$untaxedamount = scaninvoicesClean_amount($data->ht);

			//taxe de transport ? delivery_untaxed
			$delivery_untaxed = 0;
			if (isset($data->delivery_untaxed) && !empty($data->delivery_untaxed)) {
				$delivery_untaxed = scaninvoicesClean_amount($data->delivery_untaxed);
				if ($delivery_untaxed > 0) {
					$untaxedamount -= $delivery_untaxed;
					$amount -= $delivery_untaxed;
				}
			}

			if ($amount == 0 && $untaxedamount > 0) {
				$amount = $untaxedamount;
			}
			$qty = '1';
			$price_base = 'TTC';
			$idprod = 0;
			$exo = 0;
			$ecopart = 0;

			dol_syslog("  base du HT pour le calcul  untaxedamount = $untaxedamount, amount=$amount");

			//fix #27: take care about invoices with "non vat part" (exo vat)
			if (isset($data->exo_tax_base) && $data->exo_tax_base != '') {
				$exo = price2num($data->exo_tax_base, 'MU');
				dol_syslog(" il y a de l'exo : " . $exo);
			}
			//then invoices with eco part / deee
			if (isset($data->eco_part) && $data->eco_part != '') {
				$ecopart = price2num($data->eco_part, 'MU');
				dol_syslog(" il y a de l'eco-part : " . $ecopart);
			}

			//fix #10 : apply default VAT only if ttc != ht
			$tauxtva = 0;
			if ($amount != $untaxedamount && (($amount - $exo) > 0)) {
				//better than #10 :
				if ($data->ht > 0) {
					$tauxtva = round((float) price2num((($amount - $exo) / ($untaxedamount - $exo + $ecopart) - 1) * 100), 1);
					dol_syslog(' Taux de TVA calculé à partir du TTC/HT ' . $tauxtva);
					dol_syslog(" Détail du calcul : ((($amount - $exo) / ($untaxedamount - $exo + $ecopart) - 1) * 100)");
				}
				//Liste des taux possibles
				$localtaxes_type = getLocalTaxesFromRate($tauxtva, 0, $mysoc, $facfou->thirdparty);
				if (!isset($localtaxes_type[0])) {
					$tauxtva = get_default_tva($facfou->thirdparty, $mysoc, $idprod);
					dol_syslog(" Ce taux n'est pas possible, recherche du taux par défaut : " . $tauxtva);
				}
			}
			$globalTauxtva = $tauxtva;
			$remise_percent = 0;
			$fk_product = 0;

			//Si jamais on a un produit par defaut
			if ($data->defaultProductID) {
				$fk_product = str_replace('idprod_', '', $data->defaultProductID);
			}

			//Si on a des lignes de factures dans la structure importée (par exemple facturX ou retour multiligne)
			// dol_syslog("______________________________________________________ ScanInvoices : products lines ? " . json_encode($data->lines));
			if (empty(getDolGlobalString('SCANINVOICES_DISABLE_IMPORT_LINES')) && is_array($data->lines) && (count($data->lines) > 0)) {
				//TODO disabled due to setup, documentation and user settings we have to do before
				$prevline = null;
				foreach ($data->lines as $line) {
					$remise_percent = 0;
					$fk_product = 0;

					//certaines factures ont 2 lignes pour un produit, si cette ligne n'a pas de prix
					//et de quantité on passe a la suivante
					$price = scaninvoicesClean_amount($line->price_base);
					if (($price == '' || $price == 0) && isset($line->unit_price)) {
						$price = scaninvoicesClean_amount($line->unit_price);
					}

					$qty = scaninvoicesClean_amount($line->qty);
					if (($qty == '' || $qty == 0) && isset($line->quantity)) {
						$qty = scaninvoicesClean_amount($line->quantity);
					}

					//On a le montant total de la ligne mais pas le P.U -> on le calcule
					if (($price == '' || $price == 0) && isset($line->amount_untax)) {
						if ($qty > 0) {
							$price = scaninvoicesClean_amount($line->amount_untax) / $qty;
						} else {
							$price = scaninvoicesClean_amount($line->amount_untax);
						}
					}

					//Si un taux de TVA existe pour ce produit on l'utilise, sinon on reste sur le taux global de la facture
					//tax_code pour se rapprocher de ce qu'on a sur Factur-X
					//tauxtva etait au début du projet docwizon
					dol_syslog("ScanInvoices TVA :: line is : " . json_encode($line));
					if (isset($line->tax_code)) {
						dol_syslog("ScanInvoices TVA :: set from tax_code : " . $line->tax_code);
						$tauxtva = scaninvoicesClean_amount($line->tax_code);
					} elseif (isset($line->tauxtva)) {
						dol_syslog("ScanInvoices TVA :: set from tauxtva");
						$tauxtva = scaninvoicesClean_amount($line->tauxtva);
					}
					if (!isset($tauxtva) && isset($line->tax_value)) {
						dol_syslog("ScanInvoices TVA :: set from tax_value");
						$tauxtva = scaninvoicesClean_amount($line->tax_value);
					}
					if (!isset($tauxtva)) {
						dol_syslog("ScanInvoices TVA empty :: set from global");
						$tauxtva = $globalTauxtva;
					}

					//On n'a que le total TTC de la ligne => calcul du prix unitaire ht
					if (($price == '' || $price == 0) && isset($line->amount_tax)) {
						$ratio = (100 + $tauxtva) / 100;
						if ($qty > 0) {
							$price = scaninvoicesClean_amount($line->amount_tax) / $ratio / $qty;
						} else {
							$price = scaninvoicesClean_amount($line->amount_tax) / $ratio;
						}
					}


					$desc = trim($line->desc);
					//La description peut-être sur la ligne précédente
					if ($desc == "" || strlen($desc) < 5) {
						$desc = $line->label;
						if ($desc == "" || strlen($desc) < 5) {
							if (null !== $prevline) {
								$desc = trim($prevline->desc);
								if ($desc == "" || strlen($desc) < 5) {
									$desc = $prevline->label;
								}
							}
						}
						dol_syslog(" ScanInvoices : products line use prevline description = " . $desc);
					}


					dol_syslog(" ScanInvoices : products line price=$price , qty=$qty , tauxtva=$tauxtva");
					if ($price == 0 && $qty == 0) {
						$prevline = $line;
						continue;
					}

					//La référence ... soit sur la ligne précédente ... et si pas de référence le label
					if (trim($line->ref) == "") {
						if (null !== $prevline && trim($prevline->ref) != "") {
							$ref = trim($prevline->ref);
							$line->ref = $ref;
							dol_syslog(" ScanInvoices : products line use prevline ref = " . $ref);
						} else {
							$ref = strtok($line->label, " ");
							$line->ref = $ref;
						}
					} else {
						$ref = str_replace(' ', '', $line->ref);
					}

					$fk_product = scaninvoicesSearchProductID($ref, $data->fournisseurID);

					//Creation d'un produit si prix > 0
					$price = scaninvoicesClean_amount($line->price_base);
					$qty = scaninvoicesClean_amount($line->qty);
					if ($price == 0) {
						$price = scaninvoicesClean_amount($line->unit_price);
					}

					//Creation d'un produit si prix > 0 et option SCANINVOICES_IMPORT_CREATE_PRODUCT
					if ($fk_product <= 0 && $price > 0 && getDolGlobalString('SCANINVOICES_IMPORT_CREATE_PRODUCT')) {
						$nproduit = new Product($db);
						//bug détecté nico : si tout est produit pb de déclaration de TVA !!!!
						$nproduit->type = getDolGlobalString('SCANINVOICES_IMPORT_CREATE_PRODUCT_TYPE') ?? Product::TYPE_SERVICE;

						$nproduit->status = 0; //pas en vente
						$nproduit->status_buy = 1; //en achat
						$nproduit->ref = $ref;
						$nproduit->barcode = -1;
						$nproduit->label = scaninvoicesClean_label($line->label);
						$nproduit->description = $line->desc;
						$nproduit->price = $price;
						$nproduit->tva_tx = $tauxtva;
						$retnp = $nproduit->create($user);
						if ($retnp > 0) {
							$fk_product = $retnp;
						} else {
							dol_syslog("______________________________________________________ ScanInvoices : create product error for " . json_encode($nproduit));
						}
					}

					//Si toujours pas de produit -> utilisation du produit par defaut pour le fournisseur
					if ($fk_product <= 0) {
						$defaultproduct = new Settings($db);
						$resultDefProAll = $defaultproduct->fetchAll('', '', 0, 0, array('customsql' => "t.fk_soc='" . $data->fournisseurID . "'"));
						$defaultproduct = reset($resultDefProAll);
						if ($resultDefProAll && !empty($defaultproduct->fk_default_product)) {
							$fk_product = $defaultproduct->fk_default_product;
							dol_syslog('scaninvoicesApiRunInvoiceAnalyze produit/service par défaut (from supplier) id=' . $defaultproduct->fk_default_product);
						} elseif (!empty(getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT'))) {
							$fk_product = getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT');
							dol_syslog('scaninvoicesApiRunInvoiceAnalyze produit/service par défaut (from module conf) (c) id=' . $data->defaultProductID);
						} else {
							dol_syslog('importInvoice pas de produit/service par défaut pour ce fournisseur');
						}
					}
					//fin du fin 0
					if ($fk_product <= 0) {
						$fk_product = 0;
					}

					//recup info détaillée pour savoir quel type -- autre bug merci nico
					$typeProductOrService = null;
					if ($fk_product > 0) {
						$detailtsProduit = new Product($db);
						if ($detailtsProduit->fetch($fk_product)) {
							$typeProductOrService = $detailtsProduit->type;
							//update product label au vol
							if (getDolGlobalString('SCANINVOICES_IMPORT_OVERRIDE_LABEL_PRODUCT')) {
								$detailtsProduit->label = scaninvoicesClean_label($line->label);
								$detailtsProduit->update($fk_product, $user, true);
							}
						}
					}

					dol_syslog("______________________________________________________ ScanInvoices : try to add line (ref fourn=$data->ref, price=$price, tauxtva=$tauxtva)" . json_encode($line));
					$txt = '';
					if (!empty($line->label)) {
						$txt = scaninvoicesClean_label($line->label);
					}
					if (!empty($line->desc)) {
						$txt .= " : " . $line->desc;
					}

					if ($price != 0 && $qty != 0) {
						//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
						$ret = $facfou->addline($txt, $price, $tauxtva, 0, 0, $qty, $fk_product, $line->remise_percent, '', '', 0, '', 'HT', $typeProductOrService, -1, false, 0, null, 0, 0, $ref);
						if ($ret < 0) {
							dol_syslog("______________________________________________________ ScanInvoices : add line error for txt=$txt, price=$price,tauxtva=" . $tauxtva . ",qty=" . $qty . "fk_product=$fk_product, ref=$ref");
						}

						// deee / ecotaxe - ecounit = à l'unité donc * qty
						if ($line->ecounit) {
							// TODO chercher un produit ecopart dans la base
							$ref = "ecopart";
							$txt = "eco-participation";
							$fk_product = scaninvoicesSearchProductID($ref, $data->fournisseurID);
							$price = price2num(scaninvoicesClean_amount($line->ecounit), 'MU');
							//dol_syslog("______________________________________________________ ScanInvoices : add ecopart line, ref=$ref, price=$price, qty=$qty" . json_encode($line));
							//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
							$typeProductOrService = 1; //service = 1
							$ret = $facfou->addline($txt, $price, $tauxtva, 0, 0, $qty, $fk_product, 0, '', '', 0, '', 'HT', $typeProductOrService, -1, false, 0, null, 0, 0, $ref);
						} elseif ($line->ecopart) {
							//ecopart dont qty = 1
							$qty = 1;
							// TODO chercher un produit ecopart dans la base
							$ref = "ecopart";
							$txt = "eco-participation";
							$fk_product = scaninvoicesSearchProductID($ref, $data->fournisseurID);
							$price = price2num(scaninvoicesClean_amount($line->ecopart), 'MU');
							//dol_syslog("______________________________________________________ ScanInvoices : add ecopart line, ref=$ref, price=$price, qty=$qty" . json_encode($line));
							//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
							$typeProductOrService = 1; //service = 1
							$ret = $facfou->addline($txt, $price, $tauxtva, 0, 0, $qty, $fk_product, 0, '', '', 0, '', 'HT', $typeProductOrService, -1, false, 0, null, 0, 0, $ref);
						}

						//redevance copie privé
						if ($line->rpcp) {
							// TODO chercher un produit rpcp dans la base
							$ref = "rpcp";
							$txt = "Redevance copie privé";
							$fk_product = scaninvoicesSearchProductID($ref, $data->fournisseurID);
							$price = price2num(scaninvoicesClean_amount($line->rpcp), 'MU');
							//dol_syslog("______________________________________________________ ScanInvoices : add ecopart line, ref=$ref, price=$price, qty=$qty" . json_encode($line));
							//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
							$typeProductOrService = 1; //service = 1
							$ret = $facfou->addline($txt, $price, $tauxtva, 0, 0, $qty, $fk_product, 0, '', '', 0, '', 'HT', $typeProductOrService, -1, false, 0, null, 0, 0, $ref);
						}


						//surtaxe #29
						if (isset($line->surtax_amount) || isset($line->surtax_unit_price)) {
							// TODO chercher un produit surtax dans la base -> finalement surtax == ecopart
							$ref = "ecopart";
							$txt = "eco-participation";
							$fk_product = scaninvoicesSearchProductID($ref, $data->fournisseurID);
							if (isset($line->surtax_qty) && isset($line->surtax_unit_price)) {
								$price = scaninvoicesClean_amount($line->surtax_unit_price);
								$qty = $line->surtax_qty;
							} elseif (isset($line->surtax_amount)) {
								$price = scaninvoicesClean_amount($line->surtax_amount);
								$qty = 1;
							} else {
								$price = "";
								$qty = "";
							}

							//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
							$typeProductOrService = 1; //service = 1
							$ret = $facfou->addline($txt, $price, $tauxtva, 0, 0, $qty, $fk_product, 0, '', '', 0, '', 'HT', $typeProductOrService, -1, false, 0, null, 0, 0, $ref);
						}
					} else {
						dol_syslog("  line not added (price or qty null)");
					}
				}
			} else {
				//Si on a de l'exo / deee
				if ($exo > 0 || $ecopart > 0) {
					$price_base = 'HT';
					$langs->load("scaninvoices");
					if ($exo > 0) {
						$label = $langs->trans("LineLabelForNewInvoicesWithEXO");
						if (($untaxedamount - $exo) > 0) {
							//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
							$typeProductOrService = 1; //service = 1
							$ret = $facfou->addline($label, $exo, 0, 0, 0, 1, 0, 0, '', '', 0, '', $price_base, $typeProductOrService, -1, false, 0, null, 0, 0, '');
						} else {
							//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
							$typeProductOrService = 1; //service = 1
							$ret = $facfou->addline($label, ($exo - $ecopart), 0, 0, 0, 1, 0, 0, '', '', 0, '', $price_base, $typeProductOrService, -1, false, 0, null, 0, 0, '');
						}
					}
					if ($ecopart > 0) {
						$label = $langs->trans("LineLabelForNewInvoicesWithECOPART");
						//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
						$typeProductOrService = 1; //service = 1
						$ret = $facfou->addline($label, $ecopart, $tauxtva, 0, 0, 1, 0, 0, '', '', 0, '', $price_base, $typeProductOrService, -1, false, 0, null, 0, 0, '');
					}
					if (($untaxedamount - $exo) > 0) {
						$label = $langs->trans("LineLabelForNewInvoicesWithMultiVAT", $tauxtva);
						//addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0,
						// $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0,
						//$pu_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
						//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
						$typeProductOrService = 1; //service = 1
						$ret = $facfou->addline($label, ($untaxedamount - $exo), $tauxtva, 0, 0, $qty, $fk_product, $remise_percent, '', '', 0, '', $price_base, $typeProductOrService, -1, false, 0, null, 0, 0, $data->ref);
					}
				} else {
					//Sinon on fait une seule ligne qui renvoie vers "regardez le détail dans ..."
					if (empty($label)) {
						$langs->load("scaninvoices");
						$label = $langs->trans("LineLabelForNewInvoicesIfNotdefine");
					}
					//addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0,
					// $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0,
					//$pu_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
					dol_syslog("  add line label=$label, amount=$amount, tauxtva=$tauxtva, qty=$qty, fk_product=$fk_product, remise_percent=$remise_percent, price_base=$price_base, dataref=" . $data->ref);
					//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
					$typeProductOrService = 1; //service = 1
					$ret = $facfou->addline($label, $amount, $tauxtva, 0, 0, $qty, $fk_product, $remise_percent, '', '', 0, '', $price_base, $typeProductOrService, -1, false, 0, null, 0, 0, $data->ref);
				}
			}

			//taxe de transport ? delivery_untaxed
			if (!empty($delivery_untaxed)) {
				dol_syslog("delivery_untaxed is not empty and value is = " . $delivery_untaxed);
				// TODO chercher un produit Livraison
				$txt = "Livraison";
				$ref = "transport";
				if (!empty(getDolGlobalString('SCANINVOICES_DEFAULT_LIVRAISON'))) {
					$fk_product = str_replace('idprod_', '', getDolGlobalString('SCANINVOICES_DEFAULT_LIVRAISON'));
				} else {
					$fk_product = scaninvoicesSearchProductID($ref, $data->fournisseurID);
				}
				$qty = 1;
				$tauxtva = 0;
				$price = $delivery_untaxed;
				//    function addline($desc, $pu, $txtva, $txlocaltax1, $txlocaltax2, $qty, $fk_product = 0, $remise_percent = 0, $date_start = '', $date_end = '', $ventil = 0, $info_bits = '', $price_base_type = 'HT', $type = 0, $rang = -1, $notrigger = false, $array_options = 0, $fk_unit = null, $origin_id = 0, $pu_ht_devise = 0, $ref_supplier = '', $special_code = '', $fk_parent_line = 0, $fk_remise_except = 0)
				$typeProductOrService = 1; //service = 1
				$ret = $facfou->addline($txt, $price, $tauxtva, 0, 0, $qty, $fk_product, 0, '', '', 0, '', 'HT', $typeProductOrService, -1, false, 0, null, 0, 0, $ref);
			} else {
				dol_syslog("delivery_untaxed is empty");
			}


			if ($ret < 0) {
				dol_syslog("Error insert line on FacFou : " . json_encode($facfou->errors));
				++$nberror;
			}
			if ($nberror) {
				$db->rollback();
				$message = $langs->transnoentities(
					'ERROR_MESSAGE_CREATE_INVOICE',
					$data->facture,
					$data->ladate,
					$facfou->error
				);
			} else {
				$factureurl = $facfou->getNomUrl(1, '', '', '', '', 0, 0, 0);
				$message = $langs->transnoentities(
					'SUCCESS_MESSAGE_CREATE_INVOICE',
					$data->facture,
					$data->ladate,
					$factureurl
				);
				$db->commit();
			}
		} else {
			$db->rollback();
			$message = $langs->transnoentities(
				'ERROR_MESSAGE_INVOICE_DUPLICATE',
				$data->facture,
				$data->ladate,
				$facfou->error
			);
		}
	}
	//Si pas d'erreur pour la création de la fact. fournisseur on essaye de joindre le justificatif
	if ($nberror == 0) {
		dol_syslog("  Pas d erreurs ...");
		if ($factureid && $facfou->fetch($factureid)) {
			$ref = dol_sanitizeFileName($facfou->ref);
			$upload_dir = $conf->fournisseur->facture->dir_output . '/' . get_exdir($facfou->id, 2, 0, 0, $facfou, 'invoice_supplier') . $ref;
			dol_syslog("  upload dir is " . $upload_dir);

			$justif = scaninvoicesJoinFileToInvoice($facfou, $data->fileName, $upload_dir);

			//Si on a un fichier embarqué dans le json (qui était donc dans le xml du départ par exemple peppol)
			if (isset($data->base64_file)) {
				$tmpfile = tempnam(DOL_DATA_ROOT . '/scaninvoices/temp/', 'scaninvoices');
				$binaryContent = base64_decode($data->base64_file, true);
				file_put_contents($tmpfile, $binaryContent);
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mimeType = finfo_file($finfo, $tmpfile);
				dol_syslog('  base64_file present, fichier ' . $tmpfile . ' généré, mime type = ' . $mimeType);
				//On autorise que les pdf pour l'instant
				if ($mimeType == "application/pdf") {
					scaninvoicesJoinFileToInvoice($facfou, $tmpfile, $upload_dir);
				}
			}
		}
	} else {
		$message .= scaninvoicesMessageErreurAnalyse('FACT-002', $data);
		dol_syslog('  Erreurs ref FACT-002');
	}

	//Si pas d'erreur pour la création de la fact. fournisseur et qu'on a une ref commande fournisseur alors on fait le lien
	if ($nberror == 0) {
		if ($data->order_supplier) {
			$result = $facfou->add_object_linked('order_supplier', $data->order_supplier);
		}
	}

	return array(
		'message' => $message,
		'error' => $nberror,
		'factureurl' => $factureurl,
		'factureid' => $factureid,
		'justif' => $justif
	);
}

function scaninvoicesJoinFileToInvoice($facfou, $fileName, $upload_dir)
{
	global $conf;
	dol_syslog("  scaninvoicesJoinFileToInvoice fileName=$fileName, upload_dir=$upload_dir");

	//by default set to pdf due to some bad webservers without magic mime info
	$extension = ".pdf";

	if (!is_dir($upload_dir)) {
		if (dol_mkdir($upload_dir) < 0) {
			dol_syslog("  error, can't create $upload_dir directory ", LOG_ERR);
		}
	}

	if (is_dir($upload_dir) && ($fileName != '')) {
		if (file_exists($fileName)) {
			$src = $fileName;
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $src);
			if ($mimeType == "application/pdf") {
				$extension = '.pdf';
			} elseif ($mimeType == "text/xml") {
				$extension = '.xml';
			}
		} else {
			dol_syslog("  scaninvoicesJoinFileToInvoice, " . $fileName . " do not exists ... try to find it into now/later");
			$src = DOL_DATA_ROOT . '/scaninvoices/uploads/now/' . basename($fileName);
			if (!file_exists($src)) {
				$src = DOL_DATA_ROOT . '/scaninvoices/uploads/later/' . basename($fileName);
			}
		}
		dol_syslog("  scaninvoicesJoinFileToInvoice, src=" . $src);
		//Second try
		// if ($extension == '.bin') {
		//     $mimeType = mime_content_type($src);
		//     if ($mimeType == "application/pdf") {
		//         $extension='.pdf';
		//     } elseif ($mimeType == "text/xml") {
		//         $extension='.xml';
		//     }
		// }

		//Always prefix with ref (thanks to eldy)
		if (!isset($facfou->ref) || empty($facfou->ref)) {
			dol_syslog("  scaninvoicesJoinFileToInvoice, error ref of invoice is empty or not set !", LOG_ERR);
		}
		$dest_file_name = $facfou->ref . '-';
		//Then, thanks to Franck
		if (!empty(getDolGlobalString('SCANINVOICES_FILE_NAME_PRE'))) {
			$dest_file_name .= getDolGlobalString('SCANINVOICES_FILE_NAME_PRE' . '-');
		}
		if (!empty(getDolGlobalString('SCANINVOICES_FILE_NAME'))) {
			switch (getDolGlobalString('SCANINVOICES_FILE_NAME')) {
				case 1:
					$dest_file_name .= dol_sanitizeFileName($facfou->ref) . $extension;
					break;

				case 2:
					$dest_file_name .= dol_sanitizeFileName($facfou->ref_supplier) . $extension;
					break;

				default:
					$dest_file_name .= dol_sanitizeFileName(basename($fileName, $extension)) . $extension;
			}
		} else {
			$dest_file_name .= dol_sanitizeFileName(basename($fileName, $extension)) . $extension;
		}
		$dest = $upload_dir . '/' . $dest_file_name;

		dol_syslog("  Copy from $src to $dest");

		include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
		if (dol_copy($src, $dest)) {
			$justif = basename($dest);
		} else {
			dol_syslog("  Error copying file $src to $dest");
		}
	} else {
		dol_syslog("  error: upload_dir ($upload_dir) is not a directoryc or fileName ($fileName) is empty");
	}
}

function scaninvoicesMessageErreurAnalyse($code, $data, $dataJson = null)
{
	global $langs;
	$ocrID = "";
	$filenamePDF = "";
	if (isset($dataJson) && isset($dataJson->ocrID)) {
		$ocrID = basename($dataJson->ocrID);
	}

	if (isset($data) && isset($data->fileName)) {
		$filenamePDF = basename($data->fileName);
	}

	$msg = "<li>" . $langs->trans('ERROR_MESSAGE_ANALYSE1', $code) . "</li>";
	$msg .= "<li>" . $langs->transnoentities(
		'ERROR_MESSAGE_ANALYSE2',
		"<a href=\"" . dol_buildpath("/scaninvoices/importinvoice.php", 1) . "?filenamePDF=" . urlencode($filenamePDF) . "&action=create\" target=\"_blank\">",
		"</a>"
	) . "</li>";

	// $msg .= "<pre>" . json_encode($dataJson) . "</pre>";

	return $msg;
}

function scaninvoicesFournisseurIdfromExactName($label)
{
	global $db;

	$id = -1;
	if (empty($label) || trim($label) == '') {
		return $id;
	}

	$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . "societe WHERE (nom = '" . $db->escape(trim($label)) . "' OR name_alias = '" . $db->escape(trim($label)) . "')";
	$sql .= " AND entity IN (" . getEntity('societe') . ")";
	$sql .= " ORDER BY status DESC";	// Take the active company first
	$sql .= " LIMIT 1";					// Take the first one only

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		if ($num > 0) {
			$obj = $db->fetch_object($resql);
			$id = $obj->rowid;
			// dol_syslog("  Recherche d un fournisseur sur le nom (1) $label trouvé : $id");
		} else {
			dol_syslog("  ScanInvoices::Search supplier id from his name not found: $label");
		}
	}

	return $id;
}

function scaninvoicesFournisseurIdfromVAT($vatNumber)
{
	global $db;
	dol_syslog("  call scaninvoicesFournisseurIdfromVAT for " . $vatNumber);
	$id = -1;
	if (is_array($vatNumber)) {
		foreach ($vatNumber as $vat) {
			$id = scaninvoicesFournisseurIdfromOneVAT($vat);
			if ($id !== null) {
				return $id;
			}
		}
	} else {
		return scaninvoicesFournisseurIdfromOneVAT($vatNumber);
	}

	return $id;
}

function scaninvoicesFournisseurIdfromOneVAT($vatNumber)
{
	global $db;
	//clean up input string
	$vatNumber = preg_replace('/[\W]/', '', $vatNumber);

	dol_syslog("  call scaninvoicesFournisseurIdfromVAT for " . $vatNumber);

	$id = -1;
	if (empty($vatNumber) || trim($vatNumber) == '') {
		return $id;
	}

	$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . "societe WHERE tva_intra='" . $db->escape(trim($vatNumber)) . "' AND entity IN (" . getEntity('societe') . ") LIMIT 1";
	$resql = $db->query($sql);

	if ($resql) {
		$num = $db->num_rows($resql);
		if ($num > 0) {
			$obj = $db->fetch_object($resql);
			$id = $obj->rowid;
			dol_syslog("  Recherche d un fournisseur sur numéro de TVA (1) $vatNumber trouvé : $id");
		} else {
			dol_syslog("  Recherche d un fournisseur sur numéro de TVA (2) $vatNumber non trouvé");
		}
	}

	return $id;
}

/**
 * scaninvoicesCreate_supplier : make a new supplier in database
 *
 * @param mixed $f
 *
 * @return int < 0 if error, if > 0 id of supplier
 */
function scaninvoicesCreate_supplier($f)
{
	global $db, $user;
	$result = 0;

	if (trim($f->fournisseur) == "") {
		return false;
	}

	$s = new Societe($db);

	$s->name = $f->fournisseur;
	$s->email = $f->fournisseurMail;
	if (isset($f->fournisseurCountryCode) && $f->fournisseurCountryCode != "") {
		/** @phpstan-ignore-next-line */
		$s->country_id = getCountry($f->fournisseurCountryCode, '3', $db);
		$s->country = $f->fournisseurAddrCountry;
		$s->country_code = $f->fournisseurCountryCode;
	} else {
		/** @phpstan-ignore-next-line */
		$s->country_id = getCountry('', '3', $db, '', 1, $f->fournisseurAddrCountry);
		$s->country = $f->fournisseurAddrCountry;
		/** @phpstan-ignore-next-line */
		$s->country_code = getCountry('', '3', $db, '', 1, $f->fournisseurAddrCountry);
	}
	$s->client = 0;
	$s->tva_assuj = 1;
	$s->fournisseur = 1;

	//automatique -> sauf si le module de calcul de ref retourne "vide" (exemple leopard)
	$s->get_codeclient($s, 0);
	$s->get_codefournisseur($s, 1);
	if ($s->code_client == '') {
		$s->code_fournisseur = strtoupper(substr($s->name, 0, 24));
	}
	if ($s->code_fournisseur == '') {
		$s->code_fournisseur = strtoupper(substr($s->name, 0, 24));
	}

	$s->tva_intra = $f->fournisseurVAT;
	$s->address = $f->fournisseurAddr1 . ' ' . $f->fournisseurAddr2 . ' ' . $f->fournisseurAddr3;
	$s->zip = $f->fournisseurAddrCP;
	$s->town = $f->fournisseurAddrCity;

	//logo from https://siret.cap-rel.fr/logo/$domain
	dol_syslog('scaninvoicesCreate_supplier with : ' . json_encode($s));

	$result = $s->verify();
	dol_syslog('scaninvoicesCreate_supplier verify is : ' . json_encode($result));

	if ($result < 0) {
		setEventMessage($s->errorsToString(), 'errors');
	} else {
		$db->begin();
		$result = $s->create($user);
		if ($result <= 0) {
			$db->rollback();
			dol_syslog('scaninvoicesCreate_supplier Erreur : ' . $s->error);
			setEventMessage($s->error, 'errors');
		} else {
			dol_syslog('scaninvoicesCreate_supplier OK : ' . $result);
			$db->commit();
		}
	}

	return $result;
}

/**
 * convert PDF to JPEG via ocr webservice
 *
 * @param   [type]$src   [$src description]
 * @param   [type]$dst   [$dst description]
 * @param   [type]$rect  [$rect description]
 * @param   null         [ description]
 *
 * @return  array with ocrid and some more data in case of ocr success
 */
function scaninvoicesPdf2jpeg($src, $dst, $rect = null)
{
	global $conf, $langs, $mesg;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');

	//Note si le fichier existe déjà et qu'il est pas plus vieux que le src on le passe tel-quel ?
	//mais quid de l'ocrid en ce cas ? -> pour l'instant on reste comme ça

	$retour = [];
	dol_syslog("scaninvoices: scaninvoicesPdf2jpeg appel au webservice ocr pour convertir $src en JPEG...");

	$url = $scaninvoices_endpoint . '/api/ocrs';
	dol_syslog('scaninvoices: scaninvoicesPdf2jpeg Try to request storeOnly ' . $url . ' with api key ... to get an ocrID');
	$cf = new CURLFile($src);
	$param = [
		'name' => 'pdf',
		'filename' => basename($src),
		'Mime-Type' => 'application/pdf',
		'pdf' => $cf,
		'action' => 'storeOnly',
		'lang' => 'fra',
		'profile' => 'invoice'
	];

	/** @phpstan-ignore-next-line */
	$result = getURLContent($url, 'POST', $param, 1, scanInvoicesApiCommonHeader(true, false), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$json = json_decode($result['content']);
		dol_syslog('ScanInvoices: get ocrID from server :  ' . $json->ocrID);
		// dol_syslog('ScanInvoices: all results :  ' . $json->ocrResults);
		if (isset($json->ocrResults)) {
			$retour = json_decode($json->ocrResults, true);
			$retour['ratioSaved'] = $retour['ratio'];
		}
		if (isset($json->ocrID)) {
			$retour['ocrid'] = $json->ocrID;
		}
		if (isset($json->jpegBase64)) {
			dol_syslog('ScanInvoices: server send jpeg as base64, try to store it in ' . $dst);
			if ($fp = fopen($dst, 'wb')) {
				fwrite($fp, base64_decode($json->jpegBase64));
				fclose($fp);
			} else {
				dol_syslog("ScanInvoices: ERROR : can't save file to (1) $dst");
			}
		} else {
			dol_syslog('ScanInvoices: ERROR server does not send jpeg as base64 as expected');
		}
	} else {
		$mesg = '<div class="error">' . $langs->trans('scaninvoicesPdf2jpegError');
		if (isset($result['content'])) {
			$mesg .= '<br />' . $result['content'];
		}
		if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
			dol_syslog("CURL error message is " . $result['curl_error_msg']);
			$mesg .= '<br />' . $result['curl_error_msg'];
		}
		$mesg .= '</div>';
		$retour['error'] = $mesg;
	}
	dol_syslog("scaninvoices: scaninvoicesPdf2jpeg retour " . json_encode($retour));
	return $retour;
}

/**
 * send a JPEG file to server
 *
 * @param   [type]$src   [$src description]
 * @param   null         [ description]
 *
 * @return  array with ocrid and some more data
 */
function scaninvoicesSendJpeg($src, $dst, $dstPDF)
{
	global $conf, $langs, $mesg;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');

	$retour = [];
	dol_syslog("scaninvoices: scaninvoicesSendJpeg appel au webservice ocr pour stocker le fichier jpeg $src...");

	$url = $scaninvoices_endpoint . '/api/ocrs';

	$cf = new CURLFile($src);
	$param = [
		'name' => 'pdf',
		'filename' => basename($src),
		'Mime-Type' => 'image/jpeg',
		'pdf' => $cf,
		'action' => 'storeOnly',
		'lang' => 'fra',
		'profile' => 'invoice'
	];

	/** @phpstan-ignore-next-line */
	$result = getURLContent($url, 'POST', $param, 1, scanInvoicesApiCommonHeader(true, false), ['http', 'https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$json = json_decode($result['content']);
		dol_syslog('ScanInvoices: get ocrID from server :  ' . $json->ocrID);
		if (isset($json->ocrID)) {
			$retour['ocrid'] = $json->ocrID;
		}
		// dol_syslog('ScanInvoices: all results :  ' . $json->ocrResults);
		if (isset($json->ocrResults)) {
			$retour = json_decode($json->ocrResults, true);
			$retour['ratioSaved'] = $retour['ratio'];
		}
		if (isset($json->ocrID)) {
			$retour['ocrid'] = $json->ocrID;
		}
		if (isset($json->jpegBase64)) {
			dol_syslog('ScanInvoices: server send jpeg as base64, try to store it in ' . $dst);
			if ($fp = fopen($dst, 'wb')) {
				fwrite($fp, base64_decode($json->jpegBase64));
				fclose($fp);
			} else {
				dol_syslog("ScanInvoices: ERROR : can't save file to (2) $dst");
			}
		} else {
			dol_syslog('ScanInvoices: ERROR server does not send jpeg as base64 as expected');
		}
		if (isset($json->pdfBase64)) {
			dol_syslog('ScanInvoices: server send PDF as base64, try to store it in ' . $dstPDF);
			if ($fp = fopen($dstPDF, 'wb')) {
				fwrite($fp, base64_decode($json->pdfBase64));
				fclose($fp);
			} else {
				dol_syslog("ScanInvoices: ERROR : can't save file to (1) $dstPDF");
			}
		} else {
			dol_syslog('ScanInvoices: ERROR server does not send PDF as base64 as expected');
		}
	} else {
		$mesg = '<div class="error">' . $langs->trans('scaninvoicesSendJpegError');
		if (isset($result['content'])) {
			$mesg .= '<br />' . $result['content'];
		}
		if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
			dol_syslog("CURL error message is " . $result['curl_error_msg']);
			$mesg .= '<br />' . $result['curl_error_msg'];
		}
		$mesg .= '</div>';
		$retour['error'] = $mesg;
	}
	dol_syslog("scaninvoices: scaninvoicesSendJpeg retour " . json_encode($retour));
	return $retour;
}


/**
 * save model into supplier scaninvoices settings database
 *
 * @return  [type]  [return description]
 */
function scaninvoicesSaveModel($fournisseurId)
{
	global $db;
	global $user;

	$data = new stdClass();

	dol_syslog("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ scaninvoicesSaveModel fournID=$fournisseurId");
	if (trim($fournisseurId) == "") {
		dol_syslog("scaninvoicesSaveModel : fournisseurId vide !");
		return 0;
	}

	// dol_syslog("filename = $filename");
	$dataToSave = null;
	$fieldsToSave = ['fournisseurRect', 'fournisseurTvaRect', 'ladateRect', 'factureRect', 'totalhtRect', 'totalttcRect', 'ratio'];
	foreach ($fieldsToSave as $f) {
		$dataToSave[$f] = GETPOST($f, 'alpha') ? GETPOST($f, 'alpha') : null;
	}

	//Amelioration dec 2021 : on essaye de pousser ça dans les settings si on a un fournisseur
	//On essaye d'aller piocher le modele
	$object = new Settings($db);
	$sql = "";

	$resultSettings = $object->fetchAll('', '', 0, 0, array('customsql' => "t.fk_soc=" . $fournisseurId));

	//print json_encode($resultAll);
	if ($resultSettings) {
		$object = reset($resultSettings);
		dol_syslog("scaninvoicesSaveModel : scaninvoice On a un SETTINGS qui marche en bdd !");
		$object->manual_import = json_encode($dataToSave);

		$db->begin();
		$result = $object->update($user);
		if ($result <= 0) {
			$db->rollback();
			dol_syslog('scaninvoicesSaveModel : update SETTINGS Erreur : ' . $object->error);
		} else {
			dol_syslog('scaninvoicesSaveModel : update SETTINGS OK : ' . $result);
			$db->commit();
		}
	} else {
		//Creation d'un objet settings a associer a ce fournisseur si fournisseur id existe
		if ($fournisseurId > 0) {
			dol_syslog("scaninvoicesSaveModel : creation d'un objet settings pour ce fournisseur $fournisseurId...");

			$object->fk_soc = $fournisseurId;
			$object->label = "Default settings scaninvoices";
			$object->status = Settings::STATUS_DRAFT;
			$object->manual_import = json_encode($dataToSave);
			$db->begin();
			$result = $object->create($user);
			if ($result <= 0) {
				$db->rollback();
				dol_syslog('scaninvoicesSaveModel : create SETTINGS 2 Erreur : ' . $object->error);
			} else {
				dol_syslog('scaninvoicesSaveModel : create SETTINGS 2 OK : ' . $result);
				$db->commit();
			}
		} else {
			dol_syslog("scaninvoicesSaveModel : pas de fournisseur");
		}
	}
	dol_syslog("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ scaninvoicesSaveModelEnd");

	$data->message = "ok";
	return $data;
}

/**
 * find a file in a path
 *
 * @param   [type]  $file      [$file description]
 * @param   [type]  $rootPath  [$rootPath description]
 *
 * @return  [type]             [return description]
 */
function scaninvoicesFindpathfor($file, $rootPath)
{
	// dol_syslog("scaninvoices scaninvoicesFindpathfor $file in $rootPath");
	$ret = glob($rootPath . "*/" . $file);
	if (count($ret) >= 1) {
		// dol_syslog("scaninvoices scaninvoicesFindpathfor $file in $rootPath, result = " . $ret[0]);
		return dirname($ret[0]) . '/';
	}
	return "";
}


//from https://stackoverflow.com/questions/62266717/image-showing-sideways-with-tcpdf-php-library
function scaninvoicesCorrectImageOrientation($filename)
{
	// dol_syslog("scaninvoices scaninvoicesCorrectImageOrientation for $filename ...");
	if (function_exists('exif_read_data')) {
		// dol_syslog("scaninvoices scaninvoicesCorrectImageOrientation exif_read_data is available");
		$exif = exif_read_data($filename);
		if ($exif && isset($exif['Orientation'])) {
			$orientation = $exif['Orientation'];
			if ($orientation != 1) {
				$img = imagecreatefromjpeg($filename);
				$deg = 0;
				switch ($orientation) {
					case 3:
						$deg = 180;
						break;
					case 6:
						$deg = 270;
						break;
					case 8:
						$deg = 90;
						break;
				}
				if ($deg) {
					$img = imagerotate($img, $deg, 0);
				}
				// then rewrite the rotated image back to the disk as $filename
				imagejpeg($img, $filename, 95);
			} // if there is some rotation necessary
		} // if have the exif orientation info
	} // if function exists
	// dol_syslog("scaninvoices scaninvoicesCorrectImageOrientation for $filename end");
}


/**
 *	Return list of suppliers products - hack from official dolibarr sources, but without a bug (?) or misscomp about product prices table
 * -> remove all sql request about "pfp" product prices
 *
 *  @param	int		$socid   			Id of supplier thirdparty (0 = no filter)
 *  @param   int		$selected       	Product price pre-selected (must be 'id' in product_fournisseur_price or 'idprod_IDPROD')
 *  @param   string	$htmlname       	Name of HTML select
 *  @param	string	$filtertype     	Filter on product type (''=nofilter, 0=product, 1=service)
 *  @param   string	$filtre         	Generic filter. Data must not come from user input.
 *  @param   string	$filterkey      	Filter of produdts
 *  @param   int		$statut         	-1=Return all products, 0=Products not on buy, 1=Products on buy
 *  @param   int		$outputmode     	0=HTML select string, 1=Array
 *  @param   int     $limit          	Limit of line number
 *  @param   int     $alsoproductwithnosupplierprice    1=Add also product without supplier prices
 *  @param	string	$morecss			Add more CSS
 *  @param	int		$showstockinlist	Show stock information (slower).
 *  @param	string	$placeholder		Placeholder
 *
 *  @return  mixed   string or array depends on $outputmode
 */
function scaninvoicesSelect_produits_fournisseurs_list($socid, $selected = '', $htmlname = 'productid', $filtertype = '', $filtre = '', $filterkey = '', $statut = -1, $outputmode = 0, $limit = 100, $alsoproductwithnosupplierprice = 0, $morecss = '', $showstockinlist = 0, $placeholder = '')
{
	// phpcs:enable
	global $langs, $conf, $db, $user;

	$out = '';
	$outarray = array();

	$maxlengtharticle = (empty(getDolGlobalString('PRODUCT_MAX_LENGTH_COMBO')) ? 48 : getDolGlobalString('PRODUCT_MAX_LENGTH_COMBO'));

	$langs->load('stocks');
	// Units
	if (!empty(getDolGlobalString('PRODUCT_USE_UNITS'))) {
		$langs->load('other');
	}

	$sql = "SELECT p.rowid, p.ref, p.label, p.price, p.duration, p.fk_product_type, p.stock,";
	$sql .= " p.description";
	$sql .= " FROM " . MAIN_DB_PREFIX . "product as p";
	if ($socid > 0) {
		$sql .= " AND pfp.fk_soc = " . ((int) $socid);
	}
	$sql .= " WHERE p.entity IN (" . getEntity('product') . ")";
	if ($statut != -1) {
		$sql .= " AND p.tobuy = " . ((int) $statut);
	}
	if (strval($filtertype) != '') {
		$sql .= " AND p.fk_product_type = " . ((int) $filtertype);
	}
	if (!empty($filtre)) {
		$sql .= " " . $filtre;
	}
	$sql .= $db->plimit($limit, 0);

	// Build output string

	dol_syslog("::scaninvoicesSelect_produits_fournisseurs_list", LOG_DEBUG);
	$result = $db->query($sql);
	if ($result) {
		require_once DOL_DOCUMENT_ROOT . '/product/dynamic_price/class/price_parser.class.php';
		require_once DOL_DOCUMENT_ROOT . '/core/lib/product.lib.php';

		$num = $db->num_rows($result);

		//$out.='<select class="flat" id="select'.$htmlname.'" name="'.$htmlname.'">';	// remove select to have id same with combo and ajax
		$out .= '<select class="flat ' . ($morecss ? ' ' . $morecss : '') . '" id="' . $htmlname . '" name="' . $htmlname . '">';
		if (!$selected) {
			$out .= '<option value="-1" selected>' . ($placeholder ? $placeholder : '&nbsp;') . '</option>';
		} else {
			$out .= '<option value="-1">' . ($placeholder ? $placeholder : '&nbsp;') . '</option>';
		}

		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($result);

			$outkey = $objp->idprodfournprice ?? null; // id in table of price
			if (!$outkey && $alsoproductwithnosupplierprice) {
				$outkey = 'idprod_' . $objp->rowid; // id of product
			}

			$outref = $objp->ref;
			$outval = '';
			$outbarcode = $objp->barcode ?? null;
			$outqty = 1;
			$outdiscount = 0;
			$outtype = $objp->fk_product_type;
			$outdurationvalue = $outtype == Product::TYPE_SERVICE ? substr($objp->duration, 0, dol_strlen($objp->duration) - 1) : '';
			$outdurationunit = $outtype == Product::TYPE_SERVICE ? substr($objp->duration, -1) : '';

			// Units
			$outvalUnits = '';
			if (!empty(getDolGlobalString('PRODUCT_USE_UNITS'))) {
				if (!empty($objp->unit_short)) {
					$outvalUnits .= ' - ' . $objp->unit_short;
				}
				if (!empty($objp->weight) && $objp->weight_units !== null) {
					$unitToShow = showDimensionInBestUnit($objp->weight, $objp->weight_units, 'weight', $langs);
					$outvalUnits .= ' - ' . $unitToShow;
				}
				if ((!empty($objp->length) || !empty($objp->width) || !empty($objp->height)) && $objp->length_units !== null) {
					$unitToShow = $objp->length . ' x ' . $objp->width . ' x ' . $objp->height . ' ' . measuringUnitString(0, 'size', $objp->length_units);
					$outvalUnits .= ' - ' . $unitToShow;
				}
				if (!empty($objp->surface) && $objp->surface_units !== null) {
					$unitToShow = showDimensionInBestUnit($objp->surface, $objp->surface_units, 'surface', $langs);
					$outvalUnits .= ' - ' . $unitToShow;
				}
				if (!empty($objp->volume) && $objp->volume_units !== null) {
					$unitToShow = showDimensionInBestUnit($objp->volume, $objp->volume_units, 'volume', $langs);
					$outvalUnits .= ' - ' . $unitToShow;
				}
				if ($outdurationvalue && $outdurationunit) {
					$da = array(
						'h' => $langs->trans('Hour'),
						'd' => $langs->trans('Day'),
						'w' => $langs->trans('Week'),
						'm' => $langs->trans('Month'),
						'y' => $langs->trans('Year')
					);
					if (isset($da[$outdurationunit])) {
						$outvalUnits .= ' - ' . $outdurationvalue . ' ' . $langs->transnoentities($da[$outdurationunit] . ($outdurationvalue > 1 ? 's' : ''));
					}
				}
			}

			$objRef = $objp->ref;
			if ($filterkey && $filterkey != '') {
				$objRef = preg_replace('/(' . preg_quote($filterkey, '/') . ')/i', '<strong>$1</strong>', $objRef, 1);
			}
			$objRefFourn = $objp->ref_fourn ?? null;
			if ($filterkey && $filterkey != '') {
				$objRefFourn = preg_replace('/(' . preg_quote($filterkey, '/') . ')/i', '<strong>$1</strong>', $objRefFourn, 1);
			}
			$label = scaninvoicesClean_label($objp->label);
			if ($filterkey && $filterkey != '') {
				$label = preg_replace('/(' . preg_quote($filterkey, '/') . ')/i', '<strong>$1</strong>', $label, 1);
			}

			$optlabel = $objp->ref;
			if (!empty($objp->idprodfournprice) && ($objp->ref != $objp->ref_fourn)) {
				$optlabel .= ' <span class=\'opacitymedium\'>(' . $objp->ref_fourn . ')</span>';
			}
			if (!empty($conf->barcode->enabled) && !empty($objp->barcode)) {
				$optlabel .= ' (' . $outbarcode . ')';
			}
			$optlabel .= ' - ' . dol_trunc($label, $maxlengtharticle);

			$outvallabel = $objRef;
			if (!empty($objp->idprodfournprice) && ($objp->ref != $objp->ref_fourn)) {
				$outvallabel .= ' (' . $objRefFourn . ')';
			}
			if (!empty($conf->barcode->enabled) && !empty($objp->barcode)) {
				$outvallabel .= ' (' . $outbarcode . ')';
			}
			$outvallabel .= ' - ' . dol_trunc($label, $maxlengtharticle);

			// Units
			$optlabel .= $outvalUnits;
			$outvallabel .= $outvalUnits;

			if (!empty($objp->idprodfournprice)) {
				$outqty = $objp->quantity;
				$outdiscount = $objp->remise_percent;
				if (!empty($conf->dynamicprices->enabled) && !empty($objp->fk_supplier_price_expression)) {
					$prod_supplier = new ProductFournisseur($db);
					$prod_supplier->product_fourn_price_id = $objp->idprodfournprice;
					$prod_supplier->id = $objp->fk_product;
					$prod_supplier->fourn_qty = $objp->quantity;
					$prod_supplier->fourn_tva_tx = $objp->tva_tx;
					$prod_supplier->fk_supplier_price_expression = $objp->fk_supplier_price_expression;
					$priceparser = new PriceParser($db);
					$price_result = $priceparser->parseProductSupplier($prod_supplier);
					if ($price_result >= 0) {
						$objp->fprice = $price_result;
						if ($objp->quantity >= 1) {
							$objp->unitprice = $objp->fprice / $objp->quantity; // Replace dynamically unitprice
						}
					}
				}
				if ($objp->quantity == 1) {
					$optlabel .= ' - ' . price($objp->fprice * (!empty(getDolGlobalString('DISPLAY_DISCOUNTED_SUPPLIER_PRICE')) ? (1 - $objp->remise_percent / 100) : 1), 1, $langs, 0, 0, -1, $conf->currency) . "/";
					$outvallabel .= ' - ' . price($objp->fprice * (!empty(getDolGlobalString('DISPLAY_DISCOUNTED_SUPPLIER_PRICE')) ? (1 - $objp->remise_percent / 100) : 1), 0, $langs, 0, 0, -1, $conf->currency) . "/";
					$optlabel .= $langs->trans("Unit"); // Do not use strtolower because it breaks utf8 encoding
					$outvallabel .= $langs->transnoentities("Unit");
				} else {
					$optlabel .= ' - ' . price($objp->fprice * (!empty(getDolGlobalString('DISPLAY_DISCOUNTED_SUPPLIER_PRICE')) ? (1 - $objp->remise_percent / 100) : 1), 1, $langs, 0, 0, -1, $conf->currency) . "/" . $objp->quantity;
					$outvallabel .= ' - ' . price($objp->fprice * (!empty(getDolGlobalString('DISPLAY_DISCOUNTED_SUPPLIER_PRICE')) ? (1 - $objp->remise_percent / 100) : 1), 0, $langs, 0, 0, -1, $conf->currency) . "/" . $objp->quantity;
					$optlabel .= ' ' . $langs->trans("Units"); // Do not use strtolower because it breaks utf8 encoding
					$outvallabel .= ' ' . $langs->transnoentities("Units");
				}

				if ($objp->quantity > 1) {
					$optlabel .= " (" . price($objp->unitprice * (!empty(getDolGlobalString('DISPLAY_DISCOUNTED_SUPPLIER_PRICE')) ? (1 - $objp->remise_percent / 100) : 1), 1, $langs, 0, 0, -1, $conf->currency) . "/" . $langs->trans("Unit") . ")"; // Do not use strtolower because it breaks utf8 encoding
					$outvallabel .= " (" . price($objp->unitprice * (!empty(getDolGlobalString('DISPLAY_DISCOUNTED_SUPPLIER_PRICE')) ? (1 - $objp->remise_percent / 100) : 1), 0, $langs, 0, 0, -1, $conf->currency) . "/" . $langs->transnoentities("Unit") . ")"; // Do not use strtolower because it breaks utf8 encoding
				}
				if ($objp->remise_percent >= 1) {
					$optlabel .= " - " . $langs->trans("Discount") . " : " . vatrate($objp->remise_percent) . ' %';
					$outvallabel .= " - " . $langs->transnoentities("Discount") . " : " . vatrate($objp->remise_percent) . ' %';
				}
				if ($objp->duration) {
					$optlabel .= " - " . $objp->duration;
					$outvallabel .= " - " . $objp->duration;
				}
				if (!$socid) {
					$optlabel .= " - " . dol_trunc($objp->name, 8);
					$outvallabel .= " - " . dol_trunc($objp->name, 8);
				}
				if ($objp->supplier_reputation) {
					//TODO dictionary
					$reputations = array('' => $langs->trans('Standard'), 'FAVORITE' => $langs->trans('Favorite'), 'NOTTHGOOD' => $langs->trans('NotTheGoodQualitySupplier'), 'DONOTORDER' => $langs->trans('DoNotOrderThisProductToThisSupplier'));

					$optlabel .= " - " . $reputations[$objp->supplier_reputation];
					$outvallabel .= " - " . $reputations[$objp->supplier_reputation];
				}
			} else {
				if (empty($alsoproductwithnosupplierprice)) {     // No supplier price defined for couple product/supplier
					$optlabel .= " - <span class='opacitymedium'>" . $langs->trans("NoPriceDefinedForThisSupplier") . '</span>';
					$outvallabel .= ' - ' . $langs->transnoentities("NoPriceDefinedForThisSupplier");
				} else { // No supplier price defined for product, even on other suppliers
					$optlabel .= " - <span class='opacitymedium'>" . $langs->trans("NoPriceDefinedForThisSupplier") . '</span>';
					$outvallabel .= ' - ' . $langs->transnoentities("NoPriceDefinedForThisSupplier");
				}
			}

			if (!empty($conf->stock->enabled) && $showstockinlist && isset($objp->stock) && ($objp->fk_product_type == Product::TYPE_PRODUCT || !empty(getDolGlobalString('STOCK_SUPPORTS_SERVICES')))) {
				$novirtualstock = ($showstockinlist == 2);

				if (!empty($user->rights->stock->lire)) {
					$outvallabel .= ' - ' . $langs->trans("Stock") . ': ' . price((float) price2num($objp->stock, 'MS'));

					if ($objp->stock > 0) {
						$optlabel .= ' - <span class="product_line_stock_ok">';
					} elseif ($objp->stock <= 0) {
						$optlabel .= ' - <span class="product_line_stock_too_low">';
					}
					$optlabel .= $langs->transnoentities("Stock") . ':' . price((float) price2num($objp->stock, 'MS'));
					$optlabel .= '</span>';
					if (empty($novirtualstock) && !empty(getDolGlobalString('STOCK_SHOW_VIRTUAL_STOCK_IN_PRODUCTS_COMBO'))) {  // Warning, this option may slow down combo list generation
						$langs->load("stocks");

						$tmpproduct = new Product($db);
						$tmpproduct->fetch($objp->rowid, '', '', '', 1, 1, 1); // Load product without lang and prices arrays (we just need to make ->virtual_stock() after)
						$tmpproduct->load_virtual_stock();
						$virtualstock = $tmpproduct->stock_theorique;

						$outvallabel .= ' - ' . $langs->trans("VirtualStock") . ':' . $virtualstock;

						$optlabel .= ' - ' . $langs->transnoentities("VirtualStock") . ':';
						if ($virtualstock > 0) {
							$optlabel .= '<span class="product_line_stock_ok">';
						} elseif ($virtualstock <= 0) {
							$optlabel .= '<span class="product_line_stock_too_low">';
						}
						$optlabel .= $virtualstock;
						$optlabel .= '</span>';

						unset($tmpproduct);
					}
				}
			}

			$opt = '<option value="' . $outkey . '"';
			//erics
			if ($selected && $selected == $outkey) {
				$opt .= ' selected';
			}
			if (empty($objp->idprodfournprice) && empty($alsoproductwithnosupplierprice)) {
				$opt .= ' disabled';
			}
			if (!empty($objp->idprodfournprice) && $objp->idprodfournprice > 0) {
				$opt .= ' data-qty="' . $objp->quantity . '" data-up="' . $objp->unitprice . '" data-discount="' . $outdiscount . '"';
			}
			$opt .= ' data-description="' . dol_escape_htmltag($objp->description, 0, 1) . '"';
			$opt .= ' data-html="' . dol_escape_htmltag($optlabel) . '"';
			$opt .= '>';

			$opt .= $optlabel;
			$outval .= $outvallabel;

			$opt .= "</option>\n";


			// Add new entry
			// "key" value of json key array is used by jQuery automatically as selected value. Example: 'type' = product or service, 'price_ht' = unit price without tax
			// "label" value of json key array is used by jQuery automatically as text for combo box
			$out .= $opt;
			array_push(
				$outarray,
				array(
					'key' => $outkey,
					'value' => $outref,
					'label' => $outval,
					'qty' => $outqty,
					'price_ht' => price2num($objp->unitprice ?? 0, 'MT'),
					'discount' => $outdiscount,
					'type' => $outtype,
					'duration_value' => $outdurationvalue,
					'duration_unit' => $outdurationunit,
					'disabled' => (empty($objp->idprodfournprice) ? true : false),
					'description' => $objp->description
				)
			);
			// Exemple of var_dump $outarray
			// array(1) {[0]=>array(6) {[key"]=>string(1) "2" ["value"]=>string(3) "ppp"
			//           ["label"]=>string(76) "ppp (<strong>f</strong>ff2) - ppp - 20,00 Euros/1unité (20,00 Euros/unité)"
			//      	 ["qty"]=>string(1) "1" ["discount"]=>string(1) "0" ["disabled"]=>bool(false)
			//}
			//var_dump($outval); var_dump(utf8_check($outval)); var_dump(json_encode($outval));
			//$outval=array('label'=>'ppp (<strong>f</strong>ff2) - ppp - 20,00 Euros/ Unité (20,00 Euros/unité)');
			//var_dump($outval); var_dump(utf8_check($outval)); var_dump(json_encode($outval));

			$i++;
		}
		$out .= '</select>';

		$db->free($result);

		include_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';
		$out .= ajax_combobox($htmlname);

		if (empty($outputmode)) {
			return $out;
		}
		return $outarray;
	} else {
		dol_print_error($db);
	}
	return $outarray;
}

function scaninvoicesJpegResizeAndRatio($fichierJPG, &$output)
{
	$maxHeight = $_REQUEST['maxHeight'];

	// if (file_exists($fichierJPG)) {
	list($width, $height, $type, $attr) = getimagesize($fichierJPG);

	dol_syslog("Taille de l'image w=$width ,h=$height, taille max acceptée côté canvas =$maxHeight");
	//Si l'image est trop haute elle ne rentrera pas dans le document il faut donc lui faire un resize
	//et garder en mémoire le ratio pour ensuite faire les calculs de positions inverse pour les découpages
	if ($height > $maxHeight) {
		$ratio = $maxHeight / $height;
		dol_syslog("Image trop haute, (max=$maxHeight) calcul d'un ratio=$ratio");
		$new_w = $width * $ratio;
		$new_h = $height * $ratio;

		$outfile = imagecreatetruecolor($new_w, $new_h);
		$source = imagecreatefromjpeg($fichierJPG);
		// imagecopyresized($outfile, $source, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
		imagecopyresampled($outfile, $source, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
		imagejpeg($outfile, $fichierJPG, 100);
		imagedestroy($outfile);

		$output['ratio'] = $ratio;
		$output['width'] = $new_w;
		$output['height'] = $new_h;
	} else {
		$output['ratio'] = 1;
		$output['width'] = $width;
		$output['height'] = $height;
	}
}


/**
 * 1. transformer https://cloud.cap-rel.fr/index.php/s/qo3kEDgCtP9Nwrb
 *           ou https://cloud.cap-rel.fr/s/qo3kEDgCtP9Nwrb
 *  en          https://cloud.cap-rel.fr/public.php/webdav
 *
 *           ou https://cap-rel.fr/cloud/index.php/s/qo3kEDgCtP9Nwrb
 *           ou https://cap-rel.fr/cloud/s/qo3kEDgCtP9Nwrb
 *  en          https://cap-rel.fr/cloud/public.php/webdav
 *
 */
function scaninvoicesConvertNextcloudURItoSettings()
{
	global $conf;
	$password = $username = null;
	$nextcloud = parse_url(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_URI'));
	$subdir = "";
	//nextcloud is in a subdir
	if (strpos($nextcloud['path'], '/index.php') > 0) {
		$subdir = substr($nextcloud['path'], 0, strpos($nextcloud['path'], '/index.php'));
	} elseif (strpos($nextcloud['path'], '/s') > 0) {
		$subdir = substr($nextcloud['path'], 0, strpos($nextcloud['path'], '/s'));
	}
	if (strpos($nextcloud['path'], 's/') > 0) {
		$username = preg_replace('/.*\/s\/(\w+)/', '$1', $nextcloud['path']);
	}
	$password = getDolGlobalString('SCANINVOICES_IMPORT_SHARE_PASS');

	$portToUse = getDolGlobalString('SCANINVOICES_IMPORT_SHARE_PORT');
	$port = "";
	if (!empty($portToUse)) {
		$port = ":" . $portToUse;
	}

	$settings = [
		'baseUri' => $nextcloud['scheme'] . "://" . $nextcloud['host'] . $port . $subdir . "/public.php/webdav",
		'userName' => $username,
		'password' => $password
	];
	return $settings;
}


/**
 * 1. transformer https://cloud.cap-rel.fr/index.php/s/qo3kEDgCtP9Nwrb
 *           ou https://cloud.cap-rel.fr/s/qo3kEDgCtP9Nwrb
 *  en          https://cloud.cap-rel.fr/public.php/webdav
 *
 *           ou https://cap-rel.fr/cloud/index.php/s/qo3kEDgCtP9Nwrb
 *           ou https://cap-rel.fr/cloud/s/qo3kEDgCtP9Nwrb
 *  en          https://cap-rel.fr/cloud/public.php/webdav
 *
 */
function scaninvoicesConvertSynologyURItoSettings()
{
	global $conf;
	$password = $username = null;
	$davuri = parse_url(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_URI'));
	$subdir = "";
	//davuri is in a subdir
	$subdir = $davuri['path'];
	$username = getDolGlobalString('SCANINVOICES_IMPORT_SHARE_LOGIN');
	$password = getDolGlobalString('SCANINVOICES_IMPORT_SHARE_PASS');
	$portToUse = getDolGlobalString('SCANINVOICES_IMPORT_SHARE_PORT');

	$port = "";
	if (!empty($portToUse)) {
		$port = ":" . $portToUse;
	}

	$settings = [
		'baseUri' => $davuri['scheme'] . "://" . $davuri['host'] . $port . $subdir,
		'userName' => $username,
		'password' => $password,
		'authType' => Sabre\DAV\Client::AUTH_BASIC,
	];
	return $settings;
}


function scaninvoicesSendMail($to, $subject, $cc, $bcc, $html)
{
	global $conf, $user, $langs;
	$error = 0;
	if ($html != "" && !empty(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_MAILREPORT'))) {
		include_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
		$subjecttosend = $langs->trans('[Dolibarr/ScanInvoices] ' . $subject);
		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
		$texttosend = $html;
		$deliveryreceipt = 0;
		$msgishtml = 1;
		$trackid = '';
		$moreinheader = '';
		$mailfile = new CMailFile($subjecttosend, $to, $from, $texttosend, [], [], [], $cc, '', $deliveryreceipt, $msgishtml, '', '', $trackid, $moreinheader);
		$mailfile->sendfile();
	} else {
		if ($html == '') {
			dol_syslog("ScanInvoices html mail is empty, do not send email");
			$error++;
		}
		if (empty(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_MAILREPORT'))) {
			dol_syslog("ScanInvoices html mail can't be send by email : destination mail is empty, please update this module settings");
			$error++;
		}
	}

	return $error;
}

/**
 * get list of taxes returned by docwizon server
 *
 * @return  [type]  [return description]
 */
function scaninvoicesGetTaxesRateFromSrv($json)
{
	return [
		"vat1" => $json->value_tax1 ?? 0,
		"vat2" => $json->value_tax2 ?? 0,
		"vat3" => $json->value_tax3 ?? 0,
		"vat4" => $json->value_tax4 ?? 0,
		"amount1" => $json->amount_tax1 ?? 0,
		"amount2" => $json->amount_tax2 ?? 0,
		"amount3" => $json->amount_tax3 ?? 0,
		"amount4" => $json->amount_tax4 ?? 0,
	];
}

function scaninvoicesDateExtractFormat($d, $null = '')
{
	// check Day -> (0[1-9]|[1-2][0-9]|3[0-1])
	// check Month -> (0[1-9]|1[0-2])
	// check Year -> [0-9]{4} or \d{4}
	$patterns = array(
		'/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.\d{3,8}Z\b/' => 'Y-m-d\TH:i:s.u\Z', // format DATE ISO 8601
		'/\b\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\b/' => 'Y-m-d',
		'/\b\d{4}-(0[1-9]|[1-2][0-9]|3[0-1])-(0[1-9]|1[0-2])\b/' => 'Y-d-m',
		'/\b(0[1-9]|[1-2][0-9]|3[0-1])-(0[1-9]|1[0-2])-\d{4}\b/' => 'd-m-Y',
		'/\b(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])-\d{4}\b/' => 'm-d-Y',

		'/\b\d{4}\/(0[1-9]|[1-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\b/' => 'Y/d/m',
		'/\b\d{4}\/(0[1-9]|1[0-2])\/(0[1-9]|[1-2][0-9]|3[0-1])\b/' => 'Y/m/d',
		'/\b(0[1-9]|[1-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/\d{4}\b/' => 'd/m/Y',
		'/\b(0[1-9]|1[0-2])\/(0[1-9]|[1-2][0-9]|3[0-1])\/\d{4}\b/' => 'm/d/Y',

		'/\b\d{4}\.(0[1-9]|1[0-2])\.(0[1-9]|[1-2][0-9]|3[0-1])\b/' => 'Y.m.d',
		'/\b\d{4}\.(0[1-9]|[1-2][0-9]|3[0-1])\.(0[1-9]|1[0-2])\b/' => 'Y.d.m',
		'/\b(0[1-9]|[1-2][0-9]|3[0-1])\.(0[1-9]|1[0-2])\.\d{4}\b/' => 'd.m.Y',
		'/\b(0[1-9]|1[0-2])\.(0[1-9]|[1-2][0-9]|3[0-1])\.\d{4}\b/' => 'm.d.Y',

		// for 24-hour | hours seconds
		'/\b(?:2[0-3]|[01][0-9]):[0-5][0-9](:[0-5][0-9])\.\d{3,6}\b/' => 'H:i:s.u',
		'/\b(?:2[0-3]|[01][0-9]):[0-5][0-9](:[0-5][0-9])\b/' => 'H:i:s',
		'/\b(?:2[0-3]|[01][0-9]):[0-5][0-9]\b/' => 'H:i',

		// for 12-hour | hours seconds
		'/\b(?:1[012]|0[0-9]):[0-5][0-9](:[0-5][0-9])\.\d{3,6}\b/' => 'h:i:s.u',
		'/\b(?:1[012]|0[0-9]):[0-5][0-9](:[0-5][0-9])\b/' => 'h:i:s',
		'/\b(?:1[012]|0[0-9]):[0-5][0-9]\b/' => 'h:i',

		'/\.\d{3}\b/' => '.v'
	);
	//$d = preg_replace('/\b\d{2}:\d{2}\b/', 'H:i',$d);
	$d = preg_replace(array_keys($patterns), array_values($patterns), $d);

	return preg_match('/\d/', $d) ? $null : $d;
}


function scaninvoicesDateFormating($date, $format = 'd/m/Y H:i', $in_format = false, $f = '')
{
	$isformat = scaninvoicesDateExtractFormat($date);
	$d = DateTime::createFromFormat($isformat, $date);
	$format = $in_format ? $isformat : $format;
	if ($format) {
		if (in_array($format, ['Y-m-d\TH:i:s.u\Z', 'DATE_ISO8601', 'ISO8601'])) {
			$f = $d ? $d->format('Y-m-d\TH:i:s.') . substr($d->format('u'), 0, 3) . 'Z' : '';
		} else {
			$f = $d ? $d->format($format) : '';
		}
	}
	return $f;
} // end function


function scaninvoicesDateConvertFormat($old = '')
{
	$old = trim($old);
	if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $old)) { // MySQL-compatible YYYY-MM-DD format
		$new = $old;
	} elseif (preg_match('/^[0-9]{4}-(0[1-9]|[1-2][0-9]|3[0-1])-(0[1-9]|1[0-2])$/', $old)) { // DD-MM-YYYY format
		$new = substr($old, 0, 4) . '-' . substr($old, 5, 2) . '-' . substr($old, 8, 2);
	} elseif (preg_match('/^(0[1-9]|[1-2][0-9]|3[0-1])-(0[1-9]|1[0-2])-[0-9]{4}$/', $old)) { // DD-MM-YYYY format
		$new = substr($old, 6, 4) . '-' . substr($old, 3, 2) . '-' . substr($old, 0, 2);
	} elseif (preg_match('/^(0[1-9]|[1-2][0-9]|3[0-1])-(0[1-9]|1[0-2])-[0-9]{2}$/', $old)) { // DD-MM-YY format
		$new = substr($old, 6, 4) . '-' . substr($old, 3, 2) . '-20' . substr($old, 0, 2);
	} else { // Any other format. Set it as an empty date.
		$new = '0000-00-00';
	}
	return $new;
}

/**
 * clean up text (remove end spaces and non text char)
 *
 * @param   [type]  $txt  [$txt description]
 *
 * @return  [type]        [return description]
 */
function scaninvoicesClean_label($txt)
{
	return trim($txt, '\s\t\n\r\0: ');
}

/**
 * search product in local database
 *
 * @param   [type]  $ref          [$ref description]
 * @param   [type]  $supplier_id  [$supplier_id description]
 *
 * @return  [type]                [return description]
 */
function scaninvoicesSearchProductID($ref, $supplier_id)
{
	global $db;
	dol_syslog("scaninvoicesSearchProductID ref=$ref supplier_id=$supplier_id");
	if ($ref == '') {
		return -2;
	}

	$found = false;
	$fk_product = -1;
	//search for a product
	$prod = new Product($db);
	//fetch($id = '', $ref = '', $ref_ext = '', $barcode = '', $ignore_expression = 0, $ignore_price_load = 0, $ignore_lang_load = 0)

	//1. recherche sur la référence produit
	$resProd = $prod->fetch('', $ref);
	if ($resProd > 0) {
		dol_syslog("scaninvoicesSearchProductID found case 1");
		$fk_product = $prod->id;
		$found = true;
	}

	//2. recherche sur la reference externe
	if (!$found) {
		$resProd = $prod->fetch('', '', $ref);
		if ($resProd > 0) {
			dol_syslog("scaninvoicesSearchProductID found case 2");
			$fk_product = $prod->id;
			$found = true;
		}
	}

	//3. recherche sur le code barre du produit (barcode)
	if (!$found) {
		$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . "product WHERE barcode='" . $db->escape($ref) . "' AND entity IN (" . getEntity('product') . ") LIMIT 1";
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			dol_syslog("scaninvoicesSearchProductID found case 3");
			$obj = $db->fetch_object($resql);
			$fk_product = $obj->rowid;
			$found = true;
		}
	}

	//4. recherche sur customcode (Nomenclature douanière ou Code SH) du produit
	if (!$found) {
		$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . "product WHERE customcode='" . $db->escape($ref) . "' AND entity IN (" . getEntity('product') . ") LIMIT 1";
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			dol_syslog("scaninvoicesSearchProductID found case 4");
			$obj = $db->fetch_object($resql);
			$fk_product = $obj->rowid;
			$found = true;
		}
	}

	//5. recherche sur les achats, référence fournisseur
	if (!$found) {
		//On cherche si ce produit a déjà été acheté sous cette référence auprès d'un fournisseur
		//en priorité chez le fournisseur courant - voir bug dolibarr #20270
		//https://github.com/Dolibarr/dolibarr/issues/20270
		$resProd = $prod->get_buyprice('', '', '', $ref, $supplier_id);
		if ($resProd > 0) {
			$fk_product = $prod->id;
			$found = true;
			dol_syslog("scaninvoicesSearchProductID found case 5, fk_product=" . $fk_product . "or resProd=$resProd");
		}
	}

	//6. recherche sur les achats, barcode fournisseur, sql direct j'ai pas trouvé comment faire autrement
	if (!$found) {
		$sql = 'SELECT fk_product FROM ' . MAIN_DB_PREFIX . "product_fournisseur_price WHERE barcode='" . $db->escape($ref) . "' AND entity IN (" . getEntity('product') . ") LIMIT 1";
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			dol_syslog("scaninvoicesSearchProductID found case 6");
			$obj = $db->fetch_object($resql);
			$fk_product = $obj->fk_product;
			$found = true;
		}
	}

	//7. cette référence chez n'importe quel fournisseur ?
	if (!$found) {
		$resProd = $prod->get_buyprice('', '', '', $ref);
		if ($resProd > 0) {
			dol_syslog("scaninvoicesSearchProductID found case 7");
			$fk_product = $prod->id;
			$found = true;
		}
	}

	//last try, brutal sql request because of dolibarr internal bug on get_buyprice
	//https://github.com/Dolibarr/dolibarr/issues/20270
	if (!$found) {
		$sql = 'SELECT fk_product FROM ' . MAIN_DB_PREFIX . "product_fournisseur_price WHERE ref_fourn='" . $db->escape($ref) . "' AND entity IN (" . getEntity('product') . ") LIMIT 1";
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			dol_syslog("scaninvoicesSearchProductID found case 8");
			$obj = $db->fetch_object($resql);
			$fk_product = $obj->fk_product;
			$found = true;
		}
	}
	return $fk_product;
}

/**
 * get public ip of that server thanks to cap-rel ip webservice
 *
 * @return  [type]  [return description]
 */
function scaninvoicesGetMyIP()
{
	$ip = file_get_contents('https://bl.cap-rel.fr/ip.php');
	if (empty($ip)) {
		preg_match('/((\d{1,3}\.){3}\d{1,3})/', @file_get_contents("http://www.monip.org/"), $matches);
		if (isset($matches[0])) {
			$ip = $matches[0];
		}
	}
	return $ip;
}


/**
 * encrypt text with key
 *
 * @param   [type]  $plaintext  [$plaintext description]
 * @param   [type]  $key        [$key description]
 *
 * @return  [type]              [return description]
 */
function scaninvoices_lightEncryptText($plaintext)
{
	$key = ip2long($_SERVER['REMOTE_ADDR']);
	$value = ($plaintext ^ $key);
	return base64_encode("data=" . $value);
}


/**
 * prise en compte du firewall global cap-rel / inli
 *
 * @return  [type]  [return description]
 */
function scaninvoiceshandleTimeoutCheckBlacklist($result)
{
	global $langs;
	dol_syslog('scaninvoiceshandleTimeoutCheckBlacklist');

	if (isset($result['curl_error_no']) && $result['curl_error_no'] == 28) {
		$ip = '';
		$result = getURLContent('https://bl.cap-rel.fr/ip.php', 'GET', '', 1, scanInvoicesApiCommonHeader(false, false), ['https'], 0);

		if (is_array($result) && ($result['http_code'] == 200) && isset($result['content'])) {
			$ip = $result['content'];
			dol_syslog('scaninvoiceshandleTimeoutCheckBlacklist this dolibarr public ip is ' . $ip);
			$data = scaninvoices_lightEncryptText(ip2long($ip));
			setEventMessages($langs->trans('BlacklistErrorTitle'), [$langs->trans('BlacklistError', $data)], 'errors');
		} else {
			setEventMessages($langs->trans('BlacklistErrorTitle'), [$langs->trans('BlacklistErrorFull')], 'errors');
		}
	}
}


function scaninvoicesGetJsonValue($json, $key, $default = '')
{
	if (isset($json->{$key})) {
		return $json->{$key};
	}
	return $default;
}




/**
 * Return dolibarr global constant string value
 * @param string $key key to return value, return '' if not set
 * @param string $default value to return
 * @return string
 */
function scaninvoicesgetDolGlobalString($key, $default = '')
{
	if (function_exists('getDolGlobalString')) {
		if (((int) DOL_VERSION) < 15) {
			$res = getDolGlobalString($key);
			if (empty($res)) {
				$res = $default;
			}
			return $res;
		} else {
			/** @phpstan-ignore-next-line */
			return getDolGlobalString($key, $default);
		}
	}
	global $conf;
	// return $conf->global->$key ?? $default;
	return (string) (empty($conf->global->$key) ? $default : $conf->global->$key);
}
