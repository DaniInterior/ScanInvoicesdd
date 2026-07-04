<?php

/**
 * step2.php.
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
dol_include_once('/scaninvoices/class/settings.class.php');

$filenamePDF = GETPOST('filenamePDF', 'alpha') ? GETPOST('filenamePDF', 'alpha') : "";
$maxHeight = GETPOST('maxHeight', 'alpha') ? GETPOST('maxHeight', 'alpha') : "";
$ocrID = GETPOST('ocrID', 'alpha') ? GETPOST('ocrID', 'alpha') : "";
$origin = GETPOST('origin', 'alpha') ? GETPOST('origin', 'alpha') : "";
$orderID = GETPOST('orderID', 'int') ? GETPOST('orderID', 'int') : null;
$fournID = GETPOST('fournID', 'int') ? GETPOST('fournID', 'int') : null;
if ($fournID == -1) {
	$fournID = null;
}
$fourName = "";
$fournVAT = "";
$inputReadOnly = "";

$fournisseurRectSaved = $fournisseurTvaRectSaved = $ladateRectSaved = $factureRectSaved = $totalhtRectSaved = $totalttcRectSaved = $ratioSaved = null;
$fournName = null;


//maybe there is already informations about data to extract for this supplier
$objectSettings = new Settings($db);
$fournisseurProductSaved = '';
if ($fournID !== null) {
	//Get soc data
	$societe = new Societe($db);
	if ($socresult = $societe->fetch($fournID)) {
		dol_syslog("Ce fournisseur a dejà des données (1)");
		$fournVAT = $societe->tva_intra;
		if (empty($fournVAT)) {
			$fournVAT = $langs->trans("PleaseAddItOnThirdpartCard");
		}
		$fournName = trim($societe->name);
		if ($fournName == "") {
			$fournName = $societe->nom;
		}

		$resultSettings = $objectSettings->fetchAll('', '', 0, 0, array('customsql' => "t.fk_soc=$fournID"));
		if ($resultSettings) {
			$objectSettings = reset($resultSettings);
			// dol_syslog("Settings : " . json_encode($objectSettings));
			$manual_import = json_decode($objectSettings->manual_import);
			$fournisseurRectSaved = $manual_import->fournisseurRect;
			$fournisseurTvaRectSaved = $manual_import->fournisseurTvaRect;
			$ladateRectSaved = $manual_import->ladateRect;
			$factureRectSaved = $manual_import->factureRect;
			$totalhtRectSaved = $manual_import->totalhtRect;
			$totalttcRectSaved = $manual_import->totalttcRect;
			$ratioSaved = $manual_import->ratio;
			$fournisseurProductSaved = 'idprod_' . $objectSettings->fk_default_product;
			dol_syslog("Ce fournisseur a dejà des données (2) [$fournisseurProductSaved]: " . $objectSettings->manual_import);
		}

		if ($totalttcRectSaved == "") {
			dol_syslog("Ce fournisseur a dejà des données mais pas assez ... vérification sur le serveur s'il en a plus ...");
			$objectSettings = null;
			//ask server to get informations about that supplier (imagine there is an other ocr user who has the same supplier !)
			$apiResult = scaninvoicesApiGetInvoicesZonesForSupplier($fournVAT, $fournName);
			if ($apiResult['error'] == "") {
				$ocrData = $apiResult['result'];
				dol_syslog("ocr webservice got some data for that supplier ... " . $ocrData->fournisseurRect);
				$fournisseurRectSaved = $ocrData->fournisseurRect;
				$fournisseurTvaRectSaved = $ocrData->fournisseurTvaRect;
				$ladateRectSaved = $ocrData->ladateRect;
				$factureRectSaved = $ocrData->factureRect;
				$totalhtRectSaved = $ocrData->totalhtRect;
				$totalttcRectSaved = $ocrData->totalttcRect;
				$ratioSaved = $ocrData->ratio;
			} else {
				dol_syslog("No data for that supplier");
			}
		}

		// Ne verrouiller les champs que si on a réellement des zones d'extraction
		// enregistrées pour ce fournisseur (modèle déjà entraîné). Sinon, laisser
		// les champs éditables pour permettre le calibrage manuel et l'exécution de l'OCR.
		if ($totalttcRectSaved != "") {
			$inputReadOnly = " readonly";
		} else {
			dol_syslog("ScanInvoices step2: supplier $fournID known but no extraction zones, fields stay editable for manual OCR calibration");
		}
	}
}

if ($fournisseurProductSaved == '' && !empty(getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT'))) {
	$fournisseurProductSaved = getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT');
	if (substr($fournisseurProductSaved, 0, 7) != 'idprod_') {
		$fournisseurProductSaved = 'idprod_' . getDolGlobalString('SCANINVOICES_DEFAULT_PRODUCT');
	}
}

?>

<form method="post" name="form" id="form">
	<input type="hidden" id="token" name="token" value="<?php echo currentToken(); ?>">
	<input name="fournID" id="fournID" type="hidden" value="<?php echo $fournID; ?>">
	<input name="step" id="step" type="hidden" value="2">
	<input name="filenamePDF" id="filenamePDF" type="hidden" value="<?php echo $filenamePDF; ?>">
	<input name="ocrID" id="ocrID" type="hidden" value="<?php echo $ocrID; ?>">
	<input name="origin" id="origin" type="hidden" value="<?php echo $origin; ?>">
	<input name="orderID" id="" type="hidden" value="<?php echo $orderID; ?>">
	<input name="ratio" id="ratio" type="hidden">
	<input name="ratioSaved" id="ratioSaved" type="hidden" value="<?php echo $ratioSaved; ?>" <?php echo $inputReadOnly; ?>>

	<div id="ScanInvoicesWaitModal" class="ScanInvoicesWaitModal"></div>
	<div id="message" class="message"></div>

	<div id="ScanInvoicesGlobal">
		<div id="ScanInvoicesMenuGauche">
			<div id="id-left">
				<div class="left-top">
					<div class="col">
						<div class="p-3">
							<img src="<?php echo dol_buildpath('/scaninvoices/img/scaninvoices-fond_blanc300.png', 1) ?>" class="centpercent">
						</div>
					</div>


					<?php
					if ($conf->browser->layout == 'phone') {
					?>
						<input type="file" class="hideonsmartphone" name="photoToUpload" id="photoToUpload" onchange="fileSelected();" accept="image/*" capture="camera" />
						<div class="col">
							<div class="p-1 d-grid gap-2">
								<button id="btnTakePicture" type="button" class="btn btn-primary mb-2 centpercent marginbottomonly butAction nomarginleft nomarginright"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_TAKE_PICTURE") ?></button>
							</div>
						</div>
						<div class="col">
							<div class="p-1 d-grid gap-2">
								<button id="btnChooseFile" type="button" class="btn btn-primary mb-2 centpercent marginbottomonly butAction nomarginleft nomarginright"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_OPEN_FILE") ?></button>
							</div>
						</div>
					<?php
					} else {
					?>
						<div class="col">
							<div class="p-1 d-grid gap-2">
								<button id="btnChooseFile" type="button" class="btn btn-primary mb-2 centpercent"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_OPEN_FILE") ?></button>
							</div>
						</div>
					<?php
					}
					?>

					<div id="inputFields">
						<div class="col">
							<div class="ScanInvoicesForm-label-group form-control">
								<label style="position:unset"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_DOCUMENT_LANG") ?></label>
								<input name="lang" id="lang" type="hidden" value="">
								<div style="display:flex">
									<select name="fournisseurLang" id="fournisseurLang" style="max-width:70%">
										<?php
										$base64langs = "W3siaWQiOjksImNvZGUiOiJhZiIsImxhbmciOiJhZnIiLCJuYW1lIjoiQWZyaWthYW5zIn0seyJpZCI6MTcsImNvZGUiOiJhbSIsImxhbmciOiJhbWgiLCJuYW1lIjoiQW1oYXJpYyJ9LHsiaWQiOjIxLCJjb2RlIjoiYXIiLCJsYW5nIjoiYXJhIiwibmFtZSI6IkFyYWJpYyJ9LHsiaWQiOjI5LCJjb2RlIjoiYXMiLCJsYW5nIjoiYXNtIiwibmFtZSI6IkFzc2FtZXNlIn0seyJpZCI6MzcsImNvZGUiOiJheiIsImxhbmciOiJhemUiLCJuYW1lIjoiQXplcmJhaWphbmkifSx7ImlkIjo0OCwiY29kZSI6ImJlIiwibGFuZyI6ImJlbCIsIm5hbWUiOiJCZWxhcnVzaWFuIn0seyJpZCI6NjUsImNvZGUiOiJiZyIsImxhbmciOiJidWwiLCJuYW1lIjoiQnVsZ2FyaWFuIn0seyJpZCI6NTAsImNvZGUiOiJibiIsImxhbmciOiJiZW4iLCJuYW1lIjoiQmVuZ2FsaSJ9LHsiaWQiOjQyNiwiY29kZSI6ImJvIiwibGFuZyI6ImJvZCIsIm5hbWUiOiJUaWJldGFuIn0seyJpZCI6NTksImNvZGUiOiJicyIsImxhbmciOiJib3MiLCJuYW1lIjoiQm9zbmlhbiJ9LHsiaWQiOjcxLCJjb2RlIjoiY2EiLCJsYW5nIjoiY2F0IiwibmFtZSI6IkNhdGFsYW47IFZhbGVuY2lhbiJ9LHsiaWQiOjEwMSwiY29kZSI6ImNzIiwibGFuZyI6ImNlcyIsIm5hbWUiOiJDemVjaCJ9LHsiaWQiOjQ2NSwiY29kZSI6ImN5IiwibGFuZyI6ImN5bSIsIm5hbWUiOiJXZWxzaCJ9LHsiaWQiOjEwMywiY29kZSI6ImRhIiwibGFuZyI6ImRhbiIsIm5hbWUiOiJEYW5pc2gifSx7ImlkIjoxNTAsImNvZGUiOiJkZSIsImxhbmciOiJkZXUiLCJuYW1lIjoiR2VybWFuIn0seyJpZCI6MTE4LCJjb2RlIjoiZHoiLCJsYW5nIjoiZHpvIiwibmFtZSI6IkR6b25na2hhIn0seyJpZCI6MTY0LCJjb2RlIjoiZWwiLCJsYW5nIjoiZWxsIiwibmFtZSI6IkdyZWVrLCBNb2Rlcm4gKDE0NTMtKSJ9LHsiaWQiOjEyMywiY29kZSI6ImVuIiwibGFuZyI6ImVuZyIsIm5hbWUiOiJFbmdsaXNoIn0seyJpZCI6MTI1LCJjb2RlIjoiZW8iLCJsYW5nIjoiZXBvIiwibmFtZSI6IkVzcGVyYW50byJ9LHsiaWQiOjQwMCwiY29kZSI6ImVzIiwibGFuZyI6InNwYSIsIm5hbWUiOiJTcGFuaXNoOyBDYXN0aWxpYW4ifSx7ImlkIjoxMjYsImNvZGUiOiJldCIsImxhbmciOiJlc3QiLCJuYW1lIjoiRXN0b25pYW4ifSx7ImlkIjo0NCwiY29kZSI6ImV1IiwibGFuZyI6ImV1cyIsIm5hbWUiOiJCYXNxdWUifSx7ImlkIjozNDIsImNvZGUiOiJmYSIsImxhbmciOiJmYXMiLCJuYW1lIjoiUGVyc2lhbiJ9LHsiaWQiOjEzNCwiY29kZSI6ImZpIiwibGFuZyI6ImZpbiIsIm5hbWUiOiJGaW5uaXNoIn0seyJpZCI6MTM3LCJjb2RlIjoiZnIiLCJsYW5nIjoiZnJhIiwibmFtZSI6IkZyZW5jaCJ9LHsiaWQiOjE1NCwiY29kZSI6ImdhIiwibGFuZyI6ImdsZSIsIm5hbWUiOiJJcmlzaCJ9LHsiaWQiOjE1NSwiY29kZSI6ImdsIiwibGFuZyI6ImdsZyIsIm5hbWUiOiJHYWxpY2lhbiJ9LHsiaWQiOjE2NywiY29kZSI6Imd1IiwibGFuZyI6Imd1aiIsIm5hbWUiOiJHdWphcmF0aSJ9LHsiaWQiOjE3MywiY29kZSI6ImhlIiwibGFuZyI6ImhlYiIsIm5hbWUiOiJIZWJyZXcifSx7ImlkIjoxNzcsImNvZGUiOiJoaSIsImxhbmciOiJoaW4iLCJuYW1lIjoiSGluZGkifSx7ImlkIjoxODEsImNvZGUiOiJociIsImxhbmciOiJocnYiLCJuYW1lIjoiQ3JvYXRpYW4ifSx7ImlkIjoxNzAsImNvZGUiOiJodCIsImxhbmciOiJoYXQiLCJuYW1lIjoiSGFpdGlhbjsgSGFpdGlhbiBDcmVvbGUifSx7ImlkIjoxODMsImNvZGUiOiJodSIsImxhbmciOiJodW4iLCJuYW1lIjoiSHVuZ2FyaWFuIn0seyJpZCI6MTk2LCJjb2RlIjoiaWQiLCJsYW5nIjoiaW5kIiwibmFtZSI6IkluZG9uZXNpYW4ifSx7ImlkIjoxODcsImNvZGUiOiJpcyIsImxhbmciOiJpc2wiLCJuYW1lIjoiSWNlbGFuZGljIn0seyJpZCI6MjAyLCJjb2RlIjoiaXQiLCJsYW5nIjoiaXRhIiwibmFtZSI6Ikl0YWxpYW4ifSx7ImlkIjoxOTEsImNvZGUiOiJpdSIsImxhbmciOiJpa3UiLCJuYW1lIjoiSW51a3RpdHV0In0seyJpZCI6MjA1LCJjb2RlIjoiamEiLCJsYW5nIjoianBuIiwibmFtZSI6IkphcGFuZXNlIn0seyJpZCI6MjAzLCJjb2RlIjoianYiLCJsYW5nIjoiamF2IiwibmFtZSI6IkphdmFuZXNlIn0seyJpZCI6MTQ5LCJjb2RlIjoia2EiLCJsYW5nIjoia2F0IiwibmFtZSI6Ikdlb3JnaWFuIn0seyJpZCI6MjE4LCJjb2RlIjoia2siLCJsYW5nIjoia2F6IiwibmFtZSI6IkthemFraCJ9LHsiaWQiOjIyMiwiY29kZSI6ImttIiwibGFuZyI6ImtobSIsIm5hbWUiOiJDZW50cmFsIEtobWVyIn0seyJpZCI6MjEzLCJjb2RlIjoia24iLCJsYW5nIjoia2FuIiwibmFtZSI6Ikthbm5hZGEifSx7ImlkIjoyMzEsImNvZGUiOiJrbyIsImxhbmciOiJrb3IiLCJuYW1lIjoiS29yZWFuIn0seyJpZCI6MjQwLCJjb2RlIjoia3UiLCJsYW5nIjoia3VyIiwibmFtZSI6Ikt1cmRpc2gifSx7ImlkIjoyMjYsImNvZGUiOiJreSIsImxhbmciOiJraXIiLCJuYW1lIjoiS2lyZ2hpejsgS3lyZ3l6In0seyJpZCI6MjQ2LCJjb2RlIjoibGEiLCJsYW5nIjoibGF0IiwibmFtZSI6IkxhdGluIn0seyJpZCI6MjQ1LCJjb2RlIjoibG8iLCJsYW5nIjoibGFvIiwibmFtZSI6IkxhbyJ9LHsiaWQiOjI1MSwiY29kZSI6Imx0IiwibGFuZyI6ImxpdCIsIm5hbWUiOiJMaXRodWFuaWFuIn0seyJpZCI6MjQ3LCJjb2RlIjoibHYiLCJsYW5nIjoibGF2IiwibmFtZSI6IkxhdHZpYW4ifSx7ImlkIjoyNjIsImNvZGUiOiJtayIsImxhbmciOiJta2QiLCJuYW1lIjoiTWFjZWRvbmlhbiJ9LHsiaWQiOjI2OCwiY29kZSI6Im1sIiwibGFuZyI6Im1hbCIsIm5hbWUiOiJNYWxheWFsYW0ifSx7ImlkIjoyNzIsImNvZGUiOiJtciIsImxhbmciOiJtYXIiLCJuYW1lIjoiTWFyYXRoaSJ9LHsiaWQiOjI3NCwiY29kZSI6Im1zIiwibGFuZyI6Im1zYSIsIm5hbWUiOiJNYWxheSJ9LHsiaWQiOjI4NCwiY29kZSI6Im10IiwibGFuZyI6Im1sdCIsIm5hbWUiOiJNYWx0ZXNlIn0seyJpZCI6NjYsImNvZGUiOiJteSIsImxhbmciOiJteWEiLCJuYW1lIjoiQnVybWVzZSJ9LHsiaWQiOjMwNywiY29kZSI6Im5lIiwibGFuZyI6Im5lcCIsIm5hbWUiOiJOZXBhbGkifSx7ImlkIjoxMTYsImNvZGUiOiJubCIsImxhbmciOiJubGQiLCJuYW1lIjoiRHV0Y2g7IEZsZW1pc2gifSx7ImlkIjozMTYsImNvZGUiOiJubyIsImxhbmciOiJub3IiLCJuYW1lIjoiTm9yd2VnaWFuIn0seyJpZCI6MzI4LCJjb2RlIjoib3IiLCJsYW5nIjoib3JpIiwibmFtZSI6Ik9yaXlhIn0seyJpZCI6MzM4LCJjb2RlIjoicGEiLCJsYW5nIjoicGFuIiwibmFtZSI6IlBhbmphYmk7IFB1bmphYmkifSx7ImlkIjozNDYsImNvZGUiOiJwbCIsImxhbmciOiJwb2wiLCJuYW1lIjoiUG9saXNoIn0seyJpZCI6MzUxLCJjb2RlIjoicHMiLCJsYW5nIjoicHVzIiwibmFtZSI6IlB1c2h0bzsgUGFzaHRvIn0seyJpZCI6MzQ4LCJjb2RlIjoicHQiLCJsYW5nIjoicG9yIiwibmFtZSI6IlBvcnR1Z3Vlc2UifSx7ImlkIjozNTksImNvZGUiOiJybyIsImxhbmciOiJyb24iLCJuYW1lIjoiUm9tYW5pYW47IE1vbGRhdmlhbjsgTW9sZG92YW4ifSx7ImlkIjozNjIsImNvZGUiOiJydSIsImxhbmciOiJydXMiLCJuYW1lIjoiUnVzc2lhbiJ9LHsiaWQiOjM2OSwiY29kZSI6InNhIiwibGFuZyI6InNhbiIsIm5hbWUiOiJTYW5za3JpdCJ9LHsiaWQiOjM4MCwiY29kZSI6InNpIiwibGFuZyI6InNpbiIsIm5hbWUiOiJTaW5oYWxhOyBTaW5oYWxlc2UifSx7ImlkIjozODQsImNvZGUiOiJzayIsImxhbmciOiJzbGsiLCJuYW1lIjoiU2xvdmFrIn0seyJpZCI6Mzg1LCJjb2RlIjoic2wiLCJsYW5nIjoic2x2IiwibmFtZSI6IlNsb3ZlbmlhbiJ9LHsiaWQiOjEzLCJjb2RlIjoic3EiLCJsYW5nIjoic3FpIiwibmFtZSI6IkFsYmFuaWFuIn0seyJpZCI6NDAzLCJjb2RlIjoic3IiLCJsYW5nIjoic3JwIiwibmFtZSI6IlNlcmJpYW4ifSx7ImlkIjo0MTIsImNvZGUiOiJzdiIsImxhbmciOiJzd2UiLCJuYW1lIjoiU3dlZGlzaCJ9LHsiaWQiOjQxMSwiY29kZSI6InN3IiwibGFuZyI6InN3YSIsIm5hbWUiOiJTd2FoaWxpIn0seyJpZCI6NDE3LCJjb2RlIjoidGEiLCJsYW5nIjoidGFtIiwibmFtZSI6IlRhbWlsIn0seyJpZCI6NDE5LCJjb2RlIjoidGUiLCJsYW5nIjoidGVsIiwibmFtZSI6IlRlbHVndSJ9LHsiaWQiOjQyMywiY29kZSI6InRnIiwibGFuZyI6InRnayIsIm5hbWUiOiJUYWppayJ9LHsiaWQiOjQyNSwiY29kZSI6InRoIiwibGFuZyI6InRoYSIsIm5hbWUiOiJUaGFpIn0seyJpZCI6NDI4LCJjb2RlIjoidGkiLCJsYW5nIjoidGlyIiwibmFtZSI6IlRpZ3JpbnlhIn0seyJpZCI6NDI0LCJjb2RlIjoidGwiLCJsYW5nIjoidGdsIiwibmFtZSI6IlRhZ2Fsb2cifSx7ImlkIjo0NDMsImNvZGUiOiJ0ciIsImxhbmciOiJ0dXIiLCJuYW1lIjoiVHVya2lzaCJ9LHsiaWQiOjQ1MCwiY29kZSI6InVnIiwibGFuZyI6InVpZyIsIm5hbWUiOiJVaWdodXI7IFV5Z2h1ciJ9LHsiaWQiOjQ1MSwiY29kZSI6InVrIiwibGFuZyI6InVrciIsIm5hbWUiOiJVa3JhaW5pYW4ifSx7ImlkIjo0NTQsImNvZGUiOiJ1ciIsImxhbmciOiJ1cmQiLCJuYW1lIjoiVXJkdSJ9LHsiaWQiOjQ1NSwiY29kZSI6InV6IiwibGFuZyI6InV6YiIsIm5hbWUiOiJVemJlayJ9LHsiaWQiOjQ1OCwiY29kZSI6InZpIiwibGFuZyI6InZpZSIsIm5hbWUiOiJWaWV0bmFtZXNlIn0seyJpZCI6NDczLCJjb2RlIjoieWkiLCJsYW5nIjoieWlkIiwibmFtZSI6IllpZGRpc2gifSx7ImlkIjo3OSwiY29kZSI6InpoIiwibGFuZyI6ImNoaV9zaW0iLCJuYW1lIjoiQ2hpbmVzZSJ9XQ==";
										if (isset($_POST['srvlangs'])) {
											$base64langs = $_POST['srvlangs'];
										}

										$srvlangs = base64_decode($base64langs);
										$arrLangs = json_decode($srvlangs);

										foreach ($arrLangs as $k => $v) {
											$selected = "";
											if ($langs->shortlang == $v->code) {
												$selected = " selected";
											}
											print "<option value=\"" . $v->lang . "\"$selected>" . $v->name . "</option>\n";
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="col">
							<div class="ScanInvoicesForm-label-group form-control">
								<label for="fournisseurProduct"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_DOCUMENT_PRODUCT"); ?></label>
								<input name="product" id="product" type="hidden" value="<?php echo $fournisseurProductSaved; ?>">
								<div class="ScanInvoicesStyled-select">
									<?php
									/** @phpstan-ignore-next-line */
									print scaninvoicesSelect_produits_fournisseurs_list(0, $fournisseurProductSaved, "fournisseurProduct", '', '', '', 1, 0, 0, 1, '', 0, '');
									?>
								</div>
							</div>
						</div>
						<div class="col">
							<div class="ScanInvoicesForm-label-group">
								<?php if ($inputReadOnly != "") { ?>
									<p><?php echo $langs->trans("MANUAL_IMPORT_STEP2_SUPPLIER") ?> <?php echo $fournName; ?></p>
									<input name="fournisseur" id="fournisseur" type="hidden" value="<?php echo $fournName; ?>" <?php echo $inputReadOnly; ?>>
								<?php } else { ?>
									<input required name="fournisseur" id="fournisseur" type="text" placeholder="Fournisseur" class="form-control" value="<?php echo $fournName ?>" <?php echo $inputReadOnly; ?>>
									<label for="fournisseur"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_SUPPLIER") ?></label>
								<?php } ?>
								<input name="fournisseurRect" id="fournisseurRect" type="hidden" <?php echo $inputReadOnly; ?>>
								<input name="fournisseurRectSaved" id="fournisseurRectSaved" type="hidden" value="<?php echo $fournisseurRectSaved; ?>" <?php echo $inputReadOnly; ?>>
								<input name="fournisseurColor" id="fournisseurColor" type="hidden" value="rgba(245, 222, 179, .7)">
							</div>
						</div>
						<div class="col">
							<div class="ScanInvoicesForm-label-group">
								<?php if ($inputReadOnly != "") { ?>
									<p><?php echo $langs->trans("MANUAL_IMPORT_STEP2_VAT_NUMBER") ?> <?php echo $fournVAT; ?></p>
									<input name="fournisseurTva" id="fournisseurTva" type="hidden" value="<?php echo $fournVAT; ?>" <?php echo $inputReadOnly; ?>>
								<?php } else { ?>
									<input required name="fournisseurTva" id="fournisseurTva" type="text" placeholder="Num TVA fournisseur" class="form-control" value="<?php echo $fournVAT ?>" <?php echo $inputReadOnly; ?>>
									<label for="fournisseurTva"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_VAT_NUMBER") ?></label>
								<?php } ?>
								<input name="fournisseurTvaRect" id="fournisseurTvaRect" type="hidden" <?php echo $inputReadOnly; ?>>
								<input name="fournisseurTvaRectSaved" id="fournisseurTvaRectSaved" type="hidden" value="<?php echo $fournisseurTvaRectSaved; ?>" <?php echo $inputReadOnly; ?>>
								<input name="fournisseurTvaColor" id="fournisseurTvaColor" type="hidden" value="rgba(245, 150, 179, .7)">
							</div>
						</div>
						<div class="col">
							<div class="ScanInvoicesForm-label-group">
								<input required name="ladate" id="ladate" type="text" placeholder="Date" class="form-control">
								<label for="ladate"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_DATE"); ?></label> <label style="text-align:right"><a href="#" onclick="datenow(); return false;" class="small"><?php echo $langs->trans("Now") ?></a></label>
							</div>
							<input name="ladateRect" id="ladateRect" type="hidden">
							<input name="ladateRectSaved" id="ladateRectSaved" type="hidden" value="<?php echo $ladateRectSaved; ?>" <?php echo $inputReadOnly; ?>>
							<input name="ladateColor" id="ladateColor" type="hidden" value="rgba(205, 120, 209, .7)">
						</div>
						<div class="col">
							<div class="ScanInvoicesForm-label-group">
								<input required name="facture" id="facture" type="text" placeholder="N° de facture" class="form-control">
								<label for="facture"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_INVOICE_NUMBER") ?></label>
							</div>
							<input name="factureRect" id="factureRect" type="hidden">
							<input name="factureRectSaved" id="factureRectSaved" type="hidden" value="<?php echo $factureRectSaved; ?>" <?php echo $inputReadOnly; ?>>
							<input name="factureColor" id="factureColor" type="hidden" value="rgba(145, 200, 179, .7)">
						</div>
						<div class="col">
							<div class="ScanInvoicesForm-label-group">
								<input required name="totalht" id="totalht" type="text" placeholder="Total HT" class="form-control">
								<label for="totalht"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_AMOUNT_UNTAXED") ?></label>
							</div>
							<input name="totalhtRect" id="totalhtRect" type="hidden">
							<input name="totalhtRectSaved" id="totalhtRectSaved" type="hidden" value="<?php echo $totalhtRectSaved; ?>" <?php echo $inputReadOnly; ?>>
							<input name="totalhtColor" id="totalhtColor" type="hidden" value="rgba(45, 200, 179, .7)">
						</div>
						<div class="col">
							<div class="ScanInvoicesForm-label-group">
								<input required name="totalttc" id="totalttc" type="text" placeholder="Total TTC" class="form-control">
								<label for="totalttc"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_AMOUNT") ?></label>
							</div>
							<input name="totalttcRect" id="totalttcRect" type="hidden">
							<input name="totalttcRectSaved" id="totalttcRectSaved" type="hidden" value="<?php echo $totalttcRectSaved; ?>" <?php echo $inputReadOnly; ?>>
							<input name="totalttcColor" id="totalttcColor" type="hidden" value="rgba(40, 240, 240, .7)">
						</div>
					</div>
					<div class="col">
						<div class="p-1 d-grid gap-2">
							<button id="btnRunOCR" disabled type="button" class="btn btn-primary centpercent margintoponly marginbottomonly nomarginleft nomarginright"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_START_OCR") ?></button>
						</div>
					</div>
					<div class="col">
						<div class="p-1 d-grid gap-2">
							<button id="btnImportInvoice" disabled type="button" class="btn btn-primary centpercent marginbottomonly nomarginleft nomarginright"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_START_IMPORT_INTO_DOLIBARR") ?></button>
						</div>
					</div>
					<div class="col ScanInvoicesflexnomargin">
						<div class="p-1 d-grid gap-2 hideonsmartphone ScanInvoicesflexitemnomargin center centpercent">
							<button id="btnAide" type="button" class="btn btn-primary mb-2 centpercent"><?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP") ?></button>
						</div>
						<div class="p-1 d-grid gap-2 ScanInvoicesflexitemnomargin center centpercent">
							<button id="btnResetZoom" type="button" class="btn btn-primary mb-2 centpercent">Zoom 1:1</button>
						</div>
					</div>
				</div>
			</div>
			<div id="left-bottom">
				<div id="logmessage"></div>
			</div>
		</div>
		<div id="id-right">
			<div class="fiche">
				<div class="tabBar" id="ScanInvoicesCanvasDiv">
					<canvas class="ScanInvoicesLeCanvas" id="ScanInvoicesLeCanvas"></canvas>
					<canvas class="ScanInvoicesLeCanvasBackground" id="ScanInvoicesLeCanvasBackground"></canvas>
				</div>
			</div>
		</div>
	</div>
</form>
<script language="javascript">
	function datenow() {
		console.log('Click on now');
		jQuery('#ladate').val('<?php echo dol_print_date(dol_now(), 'day', 'tzuserrel'); ?>');

		return false;
	};

	document.querySelector('#btnAide')
		.addEventListener('click', (e) => {
			e.preventDefault();
			e.stopPropagation();
			scanInvoicesDriver.start();
		});
	document.querySelector('#btnResetZoom')
		.addEventListener('click', (e) => {
			zoomReset();
		});
	document.querySelector('#btnChooseFile')
		.addEventListener('click', (e) => {
			ZeroUpload.chooseFiles({
				maxHeight: window.innerHeight
			});
		});
	if (null !== document.querySelector('#btnTakePicture')) {
		document.querySelector('#btnTakePicture')
			.addEventListener('click', (e) => {
				document.getElementById("photoToUpload").click();
			});
	}
	document.querySelector('#btnRunOCR')
		.addEventListener('click', (e) => {
			runOCR();
		});
	document.querySelector('#btnImportInvoice')
		.addEventListener('click', (e) => {
			importInvoice();
		});

	const scanInvoicesDriver = new Driver({
		doneBtnText: '<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_DONE") ?>',
		closeBtnText: '<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_CLOSE") ?>',
		nextBtnText: '<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_NEXT") ?>',
		prevBtnText: '<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_PREV") ?>',
		showButtons: true,
	});

	function delayDisplayLabel(shapeNb) {
		// console.log("Demande d'afficher le shape " + shapeNb);
		if (null !== leCanvasState) {
			leCanvasState.shapes[shapeNb].showLabel(leCanvas.getContext('2d'));
		}
	}

	scanInvoicesDriver.defineSteps([{
			element: "#btnChooseFile",
			popover: {
				position: 'right',
				title: "<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_1_TITLE") ?>",
				description: "<?php echo $langs->transnoentities("MANUAL_IMPORT_STEP2_HELP_1_TXT") ?>"
			}
		},
		{
			element: "#ScanInvoicesCanvasDiv",
			popover: {
				position: 'mid-center',
				title: "<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_2_TITLE") ?>",
				description: "<?php echo $langs->transnoentities("MANUAL_IMPORT_STEP2_HELP_2_TXT") ?>",
			},
			onHighlighted: (Element) => {
				if (null !== leCanvasState) {
					// imageOpacity = 10;
					// leCanvasState.validBack = false;
					leCanvasState.valid = false;
					var l = leCanvasState.shapes.length;
					for (var i = l - 1; i >= 0; i--) {
						setTimeout(delayDisplayLabel, 800 * (i + 1), i);
					}
				}
			},
			onDeselected: (Element) => {
				if (null !== leCanvasState) {
					// imageOpacity = 100;
					// leCanvasState.validBack = false;
					leCanvasState.valid = false;
				}
			}
		},
		{
			element: "#btnRunOCR",
			popover: {
				position: 'right-bottom',
				title: "<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_3_TITLE") ?>",
				description: "<?php echo $langs->transnoentities("MANUAL_IMPORT_STEP2_HELP_3_TXT") ?>",
			}
		},
		{
			element: "#inputFields",
			popover: {
				position: 'right-center',
				title: "<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_4_TITLE") ?>",
				description: "<?php echo $langs->transnoentities("MANUAL_IMPORT_STEP2_HELP_4_TXT") ?>",
			}
		},
		{
			element: "#btnImportInvoice",
			popover: {
				position: 'right-bottom',
				title: "<?php echo $langs->trans("MANUAL_IMPORT_STEP2_HELP_5_TITLE") ?>",
				description: "<?php echo $langs->transnoentities("MANUAL_IMPORT_STEP2_HELP_5_TXT") ?>",
			}
		}
	]);

	function onglet(target) {
		var t = document.querySelector("a[href='#" + target + "']");
		t.click();
	}


	function fileSelected() {
		var count = document.getElementById('photoToUpload').files.length;
		ZeroUpload.upload(document.getElementById('photoToUpload').files, null, {
			maxHeight: window.innerHeight,
			fournID: $('#fournID').val()
		});
		console.log("Apres le zeroupload.upload ...");
	}

	$(document).ready(function() {
		// If file name is passed from prev step
		if ($('#filenamePDF').val() != "") {
			let btn = document.getElementById('btnRunOCR');
			btn.disabled = false;
			showWait();
		}
		ZeroUpload.addDropTarget('#ScanInvoicesGlobal', {
			maxHeight: window.innerHeight
		}, {
			maxHeight: window.innerHeight
		});

	});


	<?php include 'js/canvascode.js'; ?>
</script>
