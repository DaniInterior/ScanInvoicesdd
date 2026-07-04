<?php
/**
 * upload.php - called by import.php (manual one)
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

// if (!defined('NOCSRFCHECK')) {
//     define('NOCSRFCHECK', '1');
// } // Do not check CSRF attack (test on referer + on token).

include 'functions.php';
dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/scaninvoices/class/settings.class.php');
dol_include_once('/scaninvoices/class/filestoimport.class.php');
dol_include_once('/scaninvoices/lib/scaninvoices_settings.lib.php');

$maxHeight = $_REQUEST['maxHeight'];
$fournID   = $_REQUEST['fournID'];
$output = [
	'error' => "",
	'message' => 'upload',
	'maxHeight' => $maxHeight,
	'ocrID' => ""
];

foreach ($_FILES as $key => &$file) {
	$object = new Filestoimport($db);

	$sha1 = sha1_file($file['tmp_name']);
	//search if file is already here
	$resultAll = $object->fetchAll('', '', 0, 0, array('customsql'=>"t.sha1='" . $sha1 . "'"));
	if ($resultAll) {
		dol_syslog('ScanInvoices that file was already imported ');
		$object = reset($resultAll);
		// print json_encode($object);
		$row['id'] = $object->id;
		$row['filename'] = $object->filename;
		$row['message'] = "duplicate";
		$completefilename = $object->fullFilename();
	} else {
		dol_syslog('ScanInvoices that is a new file');
		$db->begin();

		$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
		$basefilename = dol_sanitizeFileName(scaninvoicesSlugify(basename($file['name'], $extension))).'.pdf';
		if ($extension == "jpg" || $extension == "jpeg") {
			$basefilename = dol_sanitizeFileName(scaninvoicesSlugify(basename($file['name'], $extension))).'.jpg';
		} elseif ($extension == "png") {
			$basefilename = dol_sanitizeFileName(scaninvoicesSlugify(basename($file['name'], $extension))).'.png';
		}

		$object->fk_supplier = $fournID;
		$object->filename = $basefilename;
		$object->date_creation = dol_now();
		$object->import_key = time();
		$object->status = Filestoimport::STATUS_DRAFT;
		$object->queue = Filestoimport::QUEUE_MANUAL;
		$dirupload = DOL_DATA_ROOT.'/scaninvoices/uploads/now/';
		$completefilename = $dirupload . $basefilename;
		dol_syslog('ScanInvoices final destination will be ' . $completefilename);
		if (!is_dir($dirupload)) {
			dol_syslog('ScanInvoices directory does not exists, make it ' . $dirupload);
			if (dol_mkdir($dirupload) < 0) {
				dol_syslog("ScanInvoices can't create directory $dirupload", LOG_ERR);
				$output['result'] = 'err';
				$output['error'] = "Can't create storage dir $dirupload";
				break;
			}
		}
		if (dol_move_uploaded_file($file['tmp_name'], $completefilename, 1) <= 0) {
			dol_syslog('ScanInvoices error moving file to ' . $completefilename);
			$output['result'] = 'err';
			$output['error'] = "Failed to save uploaded file '".$file['name']."'. Please check permissions or file size limits.";
			break;
		} else {
			dol_syslog('ScanInvoices uploaded file moved to ' . $completefilename);
			$row['filename'] = $basefilename;
			$object->sha1 = $sha1;
			$objectid = $object->create($user);
			if ($objectid <= 0) {
				$db->rollback();
				dol_syslog('ScanInvoices create_object Erreur : '.$object->error);
			} else {
				dol_syslog('ScanInvoices create_object OK : '.$objectid);
				$db->commit();
			}
			$row['id'] = $objectid;
		}
	}
	$extension = strtolower(pathinfo($completefilename, PATHINFO_EXTENSION));
	if (!file_exists($completefilename)) {
		$output['result'] = 'err';
		$output['error'] = "error uploading file, please check logs and/or store path";
	} else {
		if ($extension == "pdf") {
			$nomfichierJPG = str_replace(".pdf", ".jpg", $completefilename);
			//factorisation, scaninvoicesPdf2jpeg maintenant fait appel au serveur et retourne un ID
			$retPdf2Jpeg = scaninvoicesPdf2jpeg($completefilename, $nomfichierJPG);
			if (isset($retPdf2Jpeg['ocrid'])) {
				//test
				$output += $retPdf2Jpeg;
				$ocrID = $retPdf2Jpeg['ocrid'];
				scaninvoicesJpegResizeAndRatio($nomfichierJPG, $output);
				$output['ocrID'] = $ocrID;
				$output['result'] = 'ok';
				$output['filenamePDF'] = basename($completefilename);
				$output['filenameJPG'] = basename($nomfichierJPG);
			} else {
				$output['result'] = 'err';
			}
		} elseif ($extension == "jpg" || $extension == "jpeg") {
			$nomfichierPDF = str_replace("." . $extension, ".pdf", $completefilename);
			$nomfichierJPG = str_replace("." . $extension, ".jpg", $completefilename); //in case of .jpeg or .jpg -> all .jpg
			$retJpeg = scaninvoicesSendJpeg($completefilename, $nomfichierJPG, $nomfichierPDF);
			$output += $retJpeg;
			$ocrID = $retJpeg['ocrid'];
			scaninvoicesJpegResizeAndRatio($nomfichierJPG, $output);
			$output['ocrID'] = $ocrID;
			$output['result'] = 'ok';
			$output['filenamePDF'] = basename($nomfichierPDF);
			$output['filenameJPG'] = basename($nomfichierJPG);
		} else {
			$output['result'] = 'err';
			$output['error'] = "that file is nor pdf or jpeg ...";
		}
	}
}
// dol_syslog("upload.php returns data : " . json_encode($output));
header('Content-Type: '.JSON_MIME_TYPE);
echo json_encode($output);
