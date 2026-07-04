<?php

/**
 * uploadauto.php - called by importauto.php stuff
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

require_once 'functions.php';
dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
dol_include_once('/scaninvoices/class/settings.class.php');
dol_include_once('/scaninvoices/class/filestoimport.class.php');
dol_include_once('/scaninvoices/lib/scaninvoices_settings.lib.php');

require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

$action = (string) GETPOST('action', 'alpha');
$maxHeight = (int) GETPOST('maxHeight', 'int');

$output = [
	'error' => "",
	'message' => 'upload',
	'maxHeight' => $maxHeight,
	'ocrID' => ""
];

$numFile = 0;
$importKey = time();
$socid = 0;
if ($user->socid > 0) {
	$socid = $user->socid;
}

dol_syslog("ScanInvoices import auto, list of files = " . json_encode($_FILES));

foreach ($_FILES as $key => &$file) {
	$object = new Filestoimport($db);

	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mimeType = finfo_file($finfo, $file['tmp_name']);
	dol_syslog("ScanInvoices import mime type = $mimeType");
	finfo_close($finfo);
	$dirupload = tempnam(DOL_DATA_ROOT . '/scaninvoices/temp/', 'scaninvoices');

	//default storage file ext is pdf
	$storageExt = ".pdf";

	//maybe peppol file
	if ($mimeType == "text/xml") {
		$storageExt = ".xml";
	}

	if ($mimeType == "image/jpeg" || $mimeType == "image/png") {
		scaninvoicesCorrectImageOrientation($file['tmp_name']);

		$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
		$formatarray = pdf_getFormat($langs);
		$page_largeur = $formatarray['width'];
		$page_hauteur = $formatarray['height'];
		$format = array($page_largeur, $page_hauteur);
		// Create empty PDF
		/** @phpstan-ignore-next-line */
		$pdf = pdf_getInstance($format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($langs));
		$pdf->SetCompression();
		$pdf->AddPage();
		$pdf->setJPEGQuality(75);
		$pdf->Image($file['tmp_name'], 5, 5, $page_largeur - 10, $page_hauteur - 10, '', '', '', false, 300, '', false, false, 0);
		$tmpfile = tempnam(DOL_DATA_ROOT . '/scaninvoices/temp/', 'scaninvoices');
		$pdf->Output($tmpfile, 'F');
		rename($tmpfile, $file['tmp_name']);
		$file['name'] = str_replace($extension, 'pdf', $file['name']);
		clearstatcache();
	}

	$sha1 = sha1_file($file['tmp_name']);
	//search if file is already here
	$resultAll = $object->fetchAll('', '', 0, 0, array('customsql' => "t.sha1='" . $sha1 . "'"));
	if ($resultAll) {
		$object = reset($resultAll);
		// print json_encode($object);
		$row['id'] = $object->id;
		$row['filename'] = $object->filename;
		$row['message'] = "duplicate";
	} else {
		$db->begin();
		$basefilename = dol_sanitizeFileName(scaninvoicesSlugify(basename($file['name'], $storageExt))) . $storageExt;
		$object->filename = $basefilename;
		$object->date_creation = dol_now();
		$object->import_key = $importKey;
		$object->status = Filestoimport::STATUS_DRAFT;
		if ($action == 'later') {
			$object->queue = Filestoimport::QUEUE_LATER;
			$output['action'] = $action;
			$dirupload = DOL_DATA_ROOT . '/scaninvoices/uploads/later/';
		} else {
			$object->queue = Filestoimport::QUEUE_NOW;
			$dirupload = DOL_DATA_ROOT . '/scaninvoices/uploads/now/';
		}
		$completefilename = $dirupload . $basefilename;

		if (!is_dir($dirupload)) {
			if (dol_mkdir($dirupload) < 0) {
				dol_syslog("  error, can't create $dirupload directory ", LOG_ERR);
			}
		}

		if (dol_move_uploaded_file($file['tmp_name'], $completefilename, 1) <= 0) {
			$output['result'] = 'err';
			$output['error'] = "Failed to save uploaded file '" . $file['name'] . "'. Please check permissions or file size limits.";
			break;
		} else {
			if (!file_exists($completefilename) || !is_readable($completefilename)) {
				$output['result'] = 'error';
				$output['error'] = "Uploaded file was not saved correctly: " . $completefilename;
				dol_syslog("ScanInvoices ERROR uploaded file missing after move: " . $completefilename, LOG_ERR);
				break;
			}
			$row['filename'] = $basefilename;
			$object->sha1 = $sha1;
			$objectid = $object->create($user);
			if ($objectid <= 0) {
				$db->rollback();
				dol_syslog('ScanInvoices create_object Erreur : ' . $object->error);
			} else {
				dol_syslog('ScanInvoices create_object OK : ' . $objectid);
				$db->commit();
			}
			$row['id'] = $objectid;
		}
		// forget that idea, temp dir is excluded from addFileIntoDatabaseIndex
		// dol_syslog('ScanInvoices avant addFileIntoDatabaseIndex : ' . dirname($completefilename) . " et " . basename($completefilename));
		// $addDB = addFileIntoDatabaseIndex(dirname($completefilename), basename($completefilename),'','uploaded',0,$object);
		// dol_syslog('ScanInvoices avant addFileIntoDatabaseIndex result : ' . $addDB);
	}
	$output['row'][$numFile] = $row;
	$numFile++;
}
$output['nbFiles'] = $numFile;

dol_syslog("ScanInvoices import auto, return is = " . json_encode($output));

header('Content-Type: ' . JSON_MIME_TYPE);
echo json_encode($output);
