<?php

/**
 * importauto-form.php -> included by importauto.php -- this is a html "only" file
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

/** @var Form $form */

dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');


$apiInfoFromServer = scaninvoicesApiGetInfoAboutWebservice();

if(!isset($localFileName)) {
	$localFileName = '';
}

$module = basename($_GET['module']);
$fullFileName = "";
if($module == "peppol") {
	$iref = basename($_GET['iref']);
	$fullFileName = DOL_DATA_ROOT . '/peppol/' . $iref . "/" . $localFileName;
}

$localFileID = "";

$storageExt = "." . pathinfo($localFileName, PATHINFO_EXTENSION);

$ficim = new Filestoimport($db);
$dirupload = tempnam(DOL_DATA_ROOT . '/scaninvoices/temp/', 'scaninvoices');
$sha1 = sha1_file($fullFileName);
//search if file is already here
$resultAll = $ficim->fetchAll('', '', 0, 0, array('customsql' => "t.sha1='" . $sha1 . "'"));
if ($resultAll) {
	$ficim = reset($resultAll);
	// print json_encode(ficim);
	$localFileID = $ficim->id;
	$row['filename'] = $ficim->filename;
	$row['message'] = "duplicate";
} else {
	$db->begin();
	$basefilename = dol_sanitizeFileName(scaninvoicesSlugify(basename($localFileName, $storageExt))) . $storageExt;
	$ficim->filename = $basefilename;
	$ficim->date_creation = dol_now();

	$ficim->status = Filestoimport::STATUS_DRAFT;
	$ficim->queue = Filestoimport::QUEUE_NOW;
	$dirupload = DOL_DATA_ROOT . '/scaninvoices/uploads/now/';
	$completefilename = $dirupload . $basefilename;

	if (!is_dir($dirupload)) {
		if (dol_mkdir($dirupload) < 0) {
			dol_syslog("  error, can't create $dirupload directory ", LOG_ERR);
		}
	}

	if (dol_copy($fullFileName, $completefilename) <= 0) {
		$output['result'] = 'err';
		$output['error'] = "Failed to save uploaded file. Please check permissions or file size limits.";
		exit;
	} else {
		$ficim->sha1 = $sha1;
		$localFileID = $ficim->create($user);
		if ($localFileID <= 0) {
			$db->rollback();
			dol_syslog('ScanInvoices create_object Erreur : ' . $ficim->error);
		} else {
			dol_syslog('ScanInvoices create_object OK : ' . $localFileID);
			$db->commit();
		}
	}
}

?>

<div id="ScanInvoicesWaitModal" class="ScanInvoicesWaitModal"></div>

<?php if (getDolGlobalString('SCANINVOICES_PROTOCOL_MISSMATCH')) {
	print '<div id="ocr-server-card" style="float:left; max-width: 350px; min-height: 40px; padding: 2em; border: 1px solid #888; background: #f8f8f8; text-align: left; margin-right: 20px;">';
	print $apiInfoFromServer;
	print '</div>';
	return;
}
?>

<div>
</div>
<div id="progress"></div>
<div id="logmessage"></div>

<script language="javascript">
	var ListeFichiers = [];
	var files = [];

	function hideWait() {
		$('body').removeClass("loading");
	}

	function showWait() {
		$('body').addClass("loading");
	}

	function createTableHeader() {
		let h = "<tr>";
		h += "<th><?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_NUMBER") ?></th>";
		h += "<th><?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_FILE") ?></th>";
		h += "<th><?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_SUPPLIER") ?></th>";
		h += "<th><?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_INVOICE") ?></th>";
		h += "<th><?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_DOCUMENT") ?></th>";
		h += "</tr>";
		return h;
	}

	function createTableRow(nb, filename, fourn, fact, justif) {
		let h = "<tr>";
		h += "<td>" + nb + "</td>";
		h += "<td>" + filename + "</td>";
		h += "<td id='fourn" + nb + "'>" + fourn + "</td>";
		h += "<td id='fact" + nb + "'>" + fact + "</td>";
		h += "<td id='justif" + nb + "'>" + justif + "</td>";
		h += "</tr>";
		return h;
	}

	function getJSessionId() {
		var jsId = document.cookie.match(/JSESSIONID=[^;]+/);
		if (jsId != null) {
			if (jsId instanceof Array)
				jsId = jsId[0].substring(11);
			else
				jsId = jsId.substring(11);
		}
		return jsId;
	}

	//Démarre l'analyse de toutes les factures mais une par une pour ne pas éclater le serveur
	function importOneInvoice(nb, id, fournID) {
		if (id == "") {
			$.jnotify("Error id empty !", "error", true);
		}
		return new Promise(function(resolve, reject) {
			$('#fourn' + nb).html(
				"<img src='img/loading-bar.gif'>"
			);

			console.log("Lancement de importOneInvoice... pour " + fournID);
			// showWait();
			var testURL = "api.php/importAuto/";
			var ajaxRequest = $.ajax({
				url: testURL,
				timeout: 60000,
				type: "POST",
				data: {
					id: id,
					fournID: fournID,
					token: "<?php echo currentToken(); ?>"
				},
				dataType: "json",
				success: function(data, textStatus, request) {
					console.log(data);
					if (data.error != "") {
						$.jnotify(data.error, "error", true);
						$('#fourn' + nb).html(data.error);
						$('#fact' + nb).html('');
						$('#justif' + nb).html('');
					} else {
						$('#fourn' + nb).html(data.fourn);
						$('#fact' + nb).html(data.fact);
						$('#justif' + nb).html(data.justif);
					}
					//Pour le post final
					hideWait();
					resolve();
				},
				error: function(request, textStatus, error) {
					hideWait();
					if (textStatus === "timeout") {
						alert(' importOneInvoice server timeout (2) - your dolibarr server does not return data as fast as required');
					} else {
						let fullErrorMessage = request.status + ': ' + request.statusText
						console.log(" importOneInvoice error (2)" + fullErrorMessage);
						alert('Local API server error (2) - your dolibarr server does not return data as fast as required:' + fullErrorMessage);
					}
					//try next ?
					reject();
				}
			});
		});
	}

	$(document).ready(function() {
		let html = "<div class='div-table-responsive-no-min'><table class='ScanInvoicesStyled-table'>";
		html += "<thead>";
		html += createTableHeader();
		html += "</thead>";
		html += "<tbody>";
		html += createTableRow(0, "<?php echo basename($localFileName); ?>", "<?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_WAITING") ?>", "<?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_WAITING") ?>", "<?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_WAITING") ?>");
		html += "</tbody>";
		html += "</table></div>";

		$('#logmessage').html(
			html
		);

		globalNumFichier = 0;
		globalNbFichiers = 1;
		importOneInvoice(0, '<?php echo $localFileID ?>', $('#fournID').val());

	});
</script>
