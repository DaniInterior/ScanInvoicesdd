<?php

/**
 * api.php.
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
define('NOTOKENRENEWAL', 1);

require_once __DIR__ . '/functions.php';
dol_include_once('/scaninvoices/middlewares.php');
dol_include_once('/scaninvoices/class/filestoimport.class.php');
$output = "";
$baseVerb = getenv('BASE_VERB');

// Default index page
router('GET', '^/' . $baseVerb . '/$', function () {
	html('<h3>hello world!</h3>');
});

// GET jpeg file
router('GET', 'jpgfile/(?<filename>(.*))&token=.*$', function ($params) {
	$filename = $params['filename'];
	// dol_syslog("recherche de $filename ...");
	$filePath = scaninvoicesFindpathfor($filename, DOL_DATA_ROOT . '/scaninvoices/uploads/');

	// dol_syslog("$filename trouvé dans $filePath");
	$fic = $filePath . str_replace(".pdf", ".jpg", $filename);
	if (file_exists($fic)) {
		dol_syslog('Passage du fichier : ' . $fic);
		header('Content-Description: File Transfer');
		header('Content-Type: image/jpg');
		header('Content-Disposition: attachment; filename="' . basename($fic) . '"');
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Pragma: public');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Cache-Control: private', false);
		header('Content-Length: ' . @filesize($fic));
		readfile($fic);
		exit;
	} else {
		// dol_syslog("Le fichier n'existe pas : " . $fic);
		//On demande au webservice de le générer ?
		$ficpdf = $filePath . $filename;
		if (file_exists($ficpdf)) {
			if ($retPdf2Jpeg = scaninvoicesPdf2jpeg($ficpdf, $fic)) {
				if (isset($retPdf2Jpeg['ocrid'])) {
					$ocrID = $retPdf2Jpeg['ocrid'];
					if (file_exists($fic)) {
						dol_syslog('Passage du fichier : ' . $fic);
						header('Content-Description: File Transfer');
						header('Content-Type: image/jpg');
						header('Content-Disposition: attachment; filename="' . basename($fic) . '"');
						header('Content-Transfer-Encoding: binary');
						header('Expires: 0');
						header('Pragma: public');
						header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						header('Cache-Control: private', false);
						header('Content-Length: ' . @filesize($fic));
						readfile($fic);
						exit;
					}
				}
			}
		}
	}
});

// get data on rect position
router('POST', 'rect', function ($params) {
	global $conf, $mesg, $langs, $db;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
	$ratio = GETPOST('ratio', 'alpha');
	if (!is_numeric($ratio) && !($ratio > 0)) {
		$ratio = 1;
	}
	dol_syslog('ScanInvoices internal API::RECT On a un appel avec ' . json_encode($_POST));
	$output = [];

	$url = $scaninvoices_endpoint . '/api/ocrcuts';
	dol_syslog('ScanInvoices internal API::RECT Try to get ocr data from rect with ' . $url . ' ...');
	$rectIn = GETPOST('rect', 'array');
	$param = [
		'json' => [
			'ocrID' => GETPOST('ocrID', 'alphanohtml'),
			'rect'  => implode(":", is_array($rectIn) ? $rectIn : []),
			'ratio' => $ratio,
			'action' => 'rect',
		]
	];
	$result = getURLContent($url, 'POST', json_encode($param), 1, scanInvoicesApiCommonHeader(), ['http','https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$json = json_decode($result['content']);
		$output['texte'] = trim($json->result->texte);

		$ratio = 1;
		$rect = is_array($rectIn) ? $rectIn : [];
		$posX = round($rect['startX'] / $ratio);
		$posY = round($rect['startY'] / $ratio);
		$largeur = round($rect['w'] / $ratio);
		$hauteur = round($rect['h'] / $ratio);
		$output['x'] = $posX;
		$output['y'] = $posY;
		$output['w'] = $largeur;
		$output['h'] = $hauteur;
	} else {
		$output['ERRcode'] = "1121558a";
	}
	json([$output]);
});

//Demande d'import automatique d'un fichier qui est déjà stocké dans temp/
router('POST', 'importAuto', function ($params) {
	global $db, $user;
	$object = new Filestoimport($db);

	$id = (int) GETPOST('id', 'int');
	$fournID = (int) GETPOST('fournID', 'int');

	// dol_syslog('ScanInvoices: POST importAuto :' . $_POST['id']);
	$retour = array('error' => "");
	if ($id == "") {
		$retour['error'] = "idNotFound " . json_encode($params);
		json($retour);
	}

	$object->fetch($id);
	if (!$object->fullImportSuccess()) {
		$retour = $object->importNow($fournID);
		if (!empty($retour['ocr_unavailable'])) {
			dol_syslog('ScanInvoices::api importAuto: OCR unavailable, returning ocr_unavailable=true to client for id=' . $id, LOG_WARNING);
		}
	} else {
		$retour['message'] = scaninvoicesMessageErreurAnalyse('DUPLICATE-001', $object->fk_supplier, $object->fk_invoice);
		$s = new Societe($db);
		if ($fournID != "") {
			$fourn = $s->fetch($fournID);
			$retour['fourn'] = $s->nom;
		} elseif ($fourn = $s->fetch($object->fk_supplier)) {
			$retour['fourn'] = $s->nom;
		}
		$facfou = new FactureFournisseur($db);
		if ($facfou->fetch($object->fk_invoice)) {
			$retour['fact'] = $facfou->getNomUrl(1, '', '', '', '', 0, 0, 0);
		}
		$retour['justif'] = $object->filename;
	}
	json($retour);
});


//Start OCR stuff
router('POST', 'runocr', function ($params) {
	global $conf, $mesg, $langs, $db;
	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');
	$ratio = GETPOST('ratio', 'alpha');
	if (!is_numeric($ratio) && !($ratio > 0)) {
		$ratio = 1;
	}

	$output = [
		'ratio' => $ratio,
		'error' => ""
	];

	$keys = ['fournisseurRect','fournisseurTvaRect','ladateRect','factureRect','totalhtRect','totalttcRect'];
	$jsonRect = [];
	foreach ($keys as $key) {
		// The frontend (canvascode.js) sends each zone as a "x:y:w:h" string,
		// not an array, so read it as a string. GETPOST(..., 'array') silently
		// drops a scalar and leaves jsonRect empty (false "no new zone" case).
		$val = GETPOST($key, 'alphanohtml');
		if (!empty($val)) {
			$jsonRect[$key] = $val;
		}
	}

	if (count($jsonRect) <= 0) {
		dol_syslog("ScanInvoices:runocr no data extraction zone requests ! short return");
		$mesg = '<div class="message">'.$langs->trans('runocrInfoThereIsNoNewZone') . '</div>';
		$output['error'] = $mesg;
		json([$output]);
		return;
	}

	$url = $scaninvoices_endpoint . '/api/ocrcuts';
	$lang = GETPOST('lang', 'aZ09');
	dol_syslog('ScanInvoices internal API::RECT Try to get ocr data from rect with ' . $url . ' ... and lang=' . $lang);
	$param = [
		'ocrID' => GETPOST('ocrID', 'alphanohtml'),
		'filename' => GETPOST('filenamePDF', 'alphanohtml'),
		'jsonRect' => $jsonRect,
		'ratio' => $ratio,
		'action' => 'multicut',
		'lang' => $lang,
	];
	$result = getURLContent($url, 'POST', json_encode($param), 1, scanInvoicesApiCommonHeader(), ['http','https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		$json = json_decode($result['content']);
		$results = json_decode($json->result);
		foreach ($results as $key => $val) {
			dol_syslog('ScanInvoices internal API::RECT return ' . $key . " => " . $val);
			if ($key == 'totalht' || $key == 'totalttc') {
				$output[$key] = scaninvoicesClean_amount($val);
			} else {
				$output[$key] = $val;
			}
		}
	}
	if (isset($result['curl_error_msg']) && $result['curl_error_msg'] != "") {
		dol_syslog("ScanInvoices:runocr error Curl details " . $result['curl_error_msg']);
		$mesg = '<div class="error">'.$langs->trans('runocrError');
		$mesg .= '<br />'.$result['curl_error_msg'];
		$mesg .= '</div>';
		$output['error'] = $mesg;
	}

	json([$output]);
});

// //on demande la creation du fournisseur a partir du num de tva
// router('POST', 'createSupplier', function ($params) {
//     global $db;
//     $output = new stdClass();

//     $numTva = substr(preg_replace('/\s+/', '', $_POST['fournisseurTva']), 0, 13);
//     $f = scaninvoicesApiGetCompanyDetailsWithVatNumber($numTva);
//     $fournisseurID = scaninvoicesCreate_supplier($f);

//     $sql = 'SELECT rowid, nom FROM '.MAIN_DB_PREFIX."societe WHERE fournisseur = 1 AND tva_intra = '".$db->escape($numTva)."'";
//     $resql = $db->query($sql);
//     if ($resql) {
//         while ($obj = $db->fetch_object($resql)) {
//             $output->data = $obj;
//         }
//     }
//     $output->fournisseurID = $fournisseurID;
//     json($output);
// });

//on demande la creation d'une facture
router('POST', 'importInvoice', function ($params) {
	global $db, $langs, $conf, $user;
	dol_syslog("scaninvoicesApi::importInvoice start");

	$scaninvoices_endpoint = getDolGlobalString('SCANINVOICES_URI');

	$data = new stdClass();
	$data->fournisseurID = GETPOST('fournID', 'int') ? GETPOST('fournID', 'int') : null;
	$data->fournisseur = GETPOST('fournisseur', 'alpha') ? GETPOST('fournisseur', 'alpha') : null;
	$data->fournisseurTva = GETPOST('fournisseurTva', 'alpha') ? GETPOST('fournisseurTva', 'alpha') : null;
	$ladate = GETPOST('ladate', 'alpha') ? GETPOST('ladate', 'alpha') : null;
	$data->ladate = scaninvoicesClean_ladate($ladate);
	$data->label = "";

	//linked to a order_supplier ?
	$data->order_supplier = GETPOST('orderID', 'int') ? GETPOST('orderID', 'int') : null;

	//    $data->label = 'Facture #'.$_POST['facture'].' du '.$_POST['ladate'];
	$data->facture = GETPOST('facture', 'alpha') ? GETPOST('facture', 'alpha') : null;

	$ht = GETPOST('totalht', 'alpha') ? GETPOST('totalht', 'alpha') : null;
	$data->ht = scaninvoicesClean_amount($ht);

	$ttc = GETPOST('totalttc', 'alpha') ? GETPOST('totalttc', 'alpha') : null;
	$data->ttc = scaninvoicesClean_amount($ttc);
	$data->fileName = GETPOST('filenamePDF', 'alpha') ? GETPOST('filenamePDF', 'alpha') : null;

	//Filtrage / pseudo validator 'fournisseurTva',
	$fieldsToCheck = ['fournisseur', 'ladate', 'facture', 'ht', 'ttc'];
	$errMsg = "";
	foreach ($fieldsToCheck as $f) {
		// dol_syslog(" *******************************************************                               verification du champ $f : " . $data->{$f});
		if (trim($data->{$f}) == "") {
			$errMsg .= $langs->trans('ERROR_FIELD_EMPTY', $f);
		}
	}
	if ($errMsg != "") {
		$data->error = 11;
		$data->message = $errMsg;
		json($data);
		return;
	}

	//Confirm/Correct OCR server of "good" values
	$url = $scaninvoices_endpoint . '/api/ocrcuts';
	// dol_syslog('ScanInvoices internal API::RECT Try to get ocr data from rect with ' . $url . ' ...');
	// Zones are sent by the frontend as "x:y:w:h" strings, not arrays.
	$jsonRect = [
		'fournisseurRect' => GETPOST('fournisseurRect', 'alphanohtml'),
		'fournisseurTvaRect' => GETPOST('fournisseurTvaRect', 'alphanohtml'),
		'ladateRect' => GETPOST('ladateRect', 'alphanohtml'),
		'factureRect' => GETPOST('factureRect', 'alphanohtml'),
		'totalhtRect' => GETPOST('totalhtRect', 'alphanohtml'),
		'totalttcRect' => GETPOST('totalttcRect', 'alphanohtml'),
	];
	$jsonConfirmValues = [
		'fournisseur' => GETPOST('fournisseur', 'alphanohtml'),
		'fournisseurTva' => GETPOST('fournisseurTva', 'alphanohtml'),
		'ladate' => GETPOST('ladate', 'alphanohtml'),
		'facture' => GETPOST('facture', 'alphanohtml'),
		'totalht' => GETPOST('totalht', 'alphanohtml'),
		'totalttc' => GETPOST('totalttc', 'alphanohtml'),
	];
	$param = [
		'ocrID' => GETPOST('ocrID', 'alphanohtml'),
		'filename' => GETPOST('filenamePDF', 'alphanohtml'),
		'jsonRect' => $jsonRect,
		'jsonConfirmValues' => $jsonConfirmValues,
		'ratio' => GETPOST('ratio', 'alpha'),
		'action' => 'confirmvalues',
	];

	$result = getURLContent($url, 'POST', json_encode($param), 1, scanInvoicesApiCommonHeader(), ['http','https'], 2);
	scaninvoiceshandleTimeoutCheckBlacklist($result);

	if (is_array($result) && $result['http_code'] == 200 && isset($result['content'])) {
		//nothing to do, confirm is ok
	} else {
		dol_syslog("scaninvoicesApi::importInvoice Bad news, can't confirm to ocr server good values but that's not a fatal error");
	}

	if (is_numeric($data->fournisseurID) && $data->fournisseurID > 0) {
		dol_syslog("scaninvoicesApi::importInvoice fournisseurID is > 0 no need to create it");
	} else {
		dol_syslog("scaninvoicesApi::importInvoice fournisseurID is null/empty/<0, TVA=" . $data->fournisseurTva);
		if ($data->fournisseurTva != '') {
			$data->fournisseurID = scaninvoicesFournisseurIdfromVAT($data->fournisseurTva);
			if ($data->fournisseurID < 0) {
				$f = scaninvoicesApiGetCompanyDetailsWithVatNumber($data->fournisseurTva);
				dol_syslog('scaninvoicesApi::importInvoice données récupérées par scaninvoicesApiGetCompanyDetailsWithVatNumber :: ' . json_encode($f));
				if (isset($f->fournisseur) && $f->fournisseur != "") {
					dol_syslog('scaninvoicesApi::importInvoice Tentative de creation du fournisseur a partir des données récupérées par scaninvoicesApiGetCompanyDetailsWithVatNumber :: ' . json_encode($f));
					$data->fournisseurID = scaninvoicesCreate_supplier($f);
				} else {
					dol_syslog("scaninvoicesApi::importInvoice Fournisseur avec ce numéro de TVA inconnu !");
				}
			}
		}
		if ($data->fournisseurID == '') {
			$data->fournisseurID = scaninvoicesFournisseurIdfromExactName($data->fournisseur);
		}
	}

	if (is_null($data->fournisseurID)) {
		dol_syslog("scaninvoicesApi::importInvoice Fournisseur introuvable et création automatique impossible");
		$data->message = html_entity_decode($langs->trans('MANUAL_IMPORT_ERROR_SUPPLIER', "<a href='" . DOL_URL_ROOT . "/societe/card.php?action=create&leftmenu=&name=" . urlencode($data->fournisseur) . "&type=f' target='_blank'>", "</a>", "<b>".$data->fournisseur."</b>"));
		$data->error = 12;
	} else {
		dol_syslog("Création d'une facture fournisseur (A)"); //for full debug . json_encode($data));
		// dol_syslog("___________________________________________________________________________________________");

		//Le produit si il a été choisi sur le dropdown
		$fournisseurProduct = GETPOST('fournisseurProduct', 'alphanohtml');
		if ($fournisseurProduct !== '' && $fournisseurProduct != -1) {
			dol_syslog(" product choosed from dropdown : id=" . $fournisseurProduct);
			$data->defaultProductID = str_replace("idprod_", "", $fournisseurProduct);
		} else {
			//Le produit par défaut s'il est configuré
			$defaultproduct = new Settings($db);
			// if (floatval(DOL_VERSION) < 20.0) {
			$resultDefProAll = $defaultproduct->fetchAll('', '', 0, 0, array('customsql' => "t.fk_soc=" . $data->fournisseurID));
			// } else {
			// 	$resultDefProAll = $defaultproduct->fetchAll('', '', 0, 0, "t.fk_soc:=:" . (int)$data->fournisseurID);
			// }
			$defaultproduct = reset($resultDefProAll);
			if ($resultDefProAll && !empty($defaultproduct->fk_default_product)) {
				$data->defaultProductID = $defaultproduct->fk_default_product;
				dol_syslog('scaninvoicesApi::importInvoice produit/service par défaut (from supplier) id='.$defaultproduct->fk_default_product);
			} elseif (getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT')) {
				$data->defaultProductID = getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT');
				dol_syslog('scaninvoicesApi::importInvoice produit/service par défaut (from module conf) (a) id='.$data->defaultProductID);
			} else {
				dol_syslog('scaninvoicesApi::importInvoice pas de produit/service par défaut pour ce fournisseur');
			}
		}
		dol_syslog("scaninvoicesApi::importInvoice Création d'une facture fournisseur (2): " . json_encode($data));
		$res = scaninvoicesCreate_fact_fournisseur($data);
		$data->message = $res['message'];
		$data->error = $res['error'];
		$data->factureurl = $res['factureurl'];
		$data->factureid = $res['factureid'];

		//L'entreprise existe on peut sauvegarder le modèle
		scaninvoicesSaveModel($data->fournisseurID);

		//import ok
		$object = new Filestoimport($db);
		$fileID = GETPOST('filestoimportId', 'int');
		// dol_syslog("Résultat obk = $fileID");
		if ($fileID) {
			$object->fetch($fileID);
		} else {
			$resultObjects = $object->fetchAll('', '', 0, 0, array('customsql' => "t.filename='" . $data->fileName . "'"));
			if ($resultObjects && is_array($resultObjects)) {
				$object = reset($resultObjects);
			}
		}
		if ($object) {
			$object->status = Filestoimport::STATUS_CLOSED;
			$object->message = "";
			$object->fk_invoice = $data->factureid;
			$object->fk_supplier = $data->fournisseurID;
			$object->update($user);
		}

		// dol_syslog("Création d'une facture fournisseur : " . json_encode($data));
		// dol_syslog("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++");
	}
	json($data);
});

router('POST', 'scaninvoicesSaveModel', function ($params) {
	$data = scaninvoicesSaveModel('');
	json($data);
});

//
router('POST', 'supplier', function ($params) {
	global $db, $conf;
	$output = "";

	$name = '%';
	$nameRaw = GETPOST('name', 'alphanohtml');
	if (trim($nameRaw) != '') {
		$name .= $db->escape($nameRaw);
		$name .= '%';
	}

	// $output['id']   = "";
	// $output['rect']  = $rect;
	$sql = 'SELECT rowid,nom FROM ' . MAIN_DB_PREFIX . "societe WHERE fournisseur = 1 AND (nom LIKE '" . $name . "' OR name_alias LIKE '" . $name . "') and entity = ".$conf->entity;
	//$output['sql'] = $sql;
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$output[] = $obj;
		}
	}
	json($output);
});

//On passe une image pour calcul du ratio d'affichage
router('POST', 'imageInfo', function ($params) {
	$data = [];
	$ratio = 1;
	$maxHeight = (int) GETPOST('maxHeight', 'int');
	$filenamePDFRaw = GETPOST('filenamePDF', 'alphanohtml');
	$filenamePDF = basename($filenamePDFRaw);
	$filenameJPG = str_replace('.pdf', '.jpg', $filenamePDFRaw);
	$filePath = scaninvoicesFindpathfor($filenamePDF, DOL_DATA_ROOT . '/scaninvoices/uploads/');
	dol_syslog("scaninvoices api imageInfo for $filenamePDF -> $filePath");
	$nomfichierJPG = $filePath . $filenameJPG;
	@unlink($nomfichierJPG);

	if ($retPdf2Jpeg = scaninvoicesPdf2jpeg($filePath  . $filenamePDF, $nomfichierJPG)) {
		if (isset($retPdf2Jpeg['ocrid'])) {
			$data += $retPdf2Jpeg;

			$ocrID = $retPdf2Jpeg['ocrid'];
			list($width, $height, $type, $attr) = getimagesize($nomfichierJPG);

			dol_syslog("api/imageInfo, taille de l'image w=$width ,h=$height, taille max acceptée côté canvas =$maxHeight");
			if ($height > $maxHeight) {
				$ratio =  $maxHeight / $height;

				dol_syslog("api/imageInfo, image trop haute, (max=$maxHeight) calcul d'un ratio=$ratio");
				$new_w = $width * $ratio;
				$new_h = $height * $ratio;

				$outfile = imagecreatetruecolor($new_w, $new_h);
				$source = imagecreatefromjpeg($nomfichierJPG);
				// imagecopyresized($outfile, $source, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
				imagecopyresampled($outfile, $source, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
				imagejpeg($outfile, $nomfichierJPG, 100);
				imagedestroy($outfile);

				$data['ratio'] = $ratio;
				$data['width'] = $new_w;
				$data['height'] = $new_h;
			} else {
				$data['ratio'] = 1;
				$data['width'] = $width;
				$data['height'] = $height;
			}
			$data['ocrID'] = $ocrID;

			dol_syslog("calcul du ratio : le canvas propose maxHeight=" . $maxHeight . " et l'image fait $height ... résultat le ratio=$ratio");
		} else {
			dol_syslog("Erreur de conversion du pdf en jpeg !");
			$data['message'] = "convert scaninvoicesPdf2jpeg error";
		}
	}
	json($data);
});


// Route constructed with the helper
router('GET', entry('hi', '(?<name>(.*))'), function ($params) {
	html("hello {$params['name']}");
});

// In the worst case...
error('404 Not Found (1)');
