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
	<div id="formupload">
		<form method="post">
			<p><?php print '<div class="valignmiddle inline-block">'.img_picto('', 'company', 'class="pictofixedwidth"').$langs->trans("ScanInvoicesAutoImportSupplier").':</div>'.$form->select_company('', 'fournID', 's.fournisseur=1', $langs->transnoentities("AUTOMATIC"), 0, 0, null, 0, 'inline-block minwidth175 maxwidth250 widthcentpercentminusxx'); ?>
			<input name="filenamePDF" id="filenamePDF" type="hidden">
			<div class="ScanInvoicesflex">
			<div id="ScanInvoicesMydrop" class="ScanInvoicesflexitem">
				<i class="fas fa-shipping-fast" style="font-size: 6em"></i><br />
				<p><?php echo $langs->transnoentities("AUTOMATIC_IMPORT_DRAG_FILE_HERE") ?></p>
			</div>

			<div id="ScanInvoicesMydropLater" class="ScanInvoicesflexitem">
				<i class="fas fa-truck" style="font-size: 6em"></i><br />
				<p><?php echo $langs->transnoentities("AUTOMATIC_IMPORT_LATER_DRAG_FILE_HERE") ?></p>
			</div>
			</div>
		</form>
	</div>
	<div id="ocr-server-card" class="clearboth" style="min-height: 40px; padding: 2em; border: 1px solid #888; background: #f8f8f8; text-align: left;">
		<?php print $apiInfoFromServer; ?>
	</div>
</div>
<div id="progress"></div>
<div id="logmessage"></div>

<script language="javascript">
var ListeFichiers;

document.querySelector('#ScanInvoicesMydrop')
		.addEventListener('click', (e) => {
			ZeroUpload.chooseFiles({
				maxHeight: window.innerHeight,
				fournID: $('#fournID').val(),
				action: "now"
			},{
				action: "now"
			}
			);
		});

document.querySelector('#ScanInvoicesMydropLater')
		.addEventListener('click', (e) => {
			ZeroUpload.chooseFiles({
				maxHeight: window.innerHeight,
				fournID: $('#fournID').val(),
				action: "later"
			},{
				action: "later"
			}
			);
		});


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

function getJSessionId(){
	var jsId = document.cookie.match(/JSESSIONID=[^;]+/);
	if(jsId != null) {
		if (jsId instanceof Array)
			jsId = jsId[0].substring(11);
		else
			jsId = jsId.substring(11);
	}
	return jsId;
}

//Démarre l'analyse de toutes les factures mais une par une pour ne pas éclater le serveur
function importOneInvoice(nb, id, fournID) {
	if(id == "") {
		$.jnotify("Error id empty !", "error", true);
	}
	return new Promise(function (resolve, reject) {
		$('#fourn'+nb).html(
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
			success: function (data, textStatus, request) {
				console.log(data);
				if(data.error != "") {
					$.jnotify(data.error, "error", true);
					$('#fourn'+nb).html(data.error);
					$('#fact' + nb).html('');
					$('#justif' + nb).html('');
				}
				else {
					$('#fourn' + nb).html(data.fourn);
					$('#fact' + nb).html(data.fact);
					$('#justif' + nb).html(data.justif);
				}
				//Pour le post final
				hideWait();
				resolve();
				console.log(" importOneInvoice ok, try next ?");
				if(((nb + 1) < ListeFichiers.length)) {
					if(ListeFichiers[nb+1].id != "") {
					  importOneInvoice(nb+1,ListeFichiers[nb+1].id, fournID)
					}
				}
			},
			error: function (request, textStatus, error) {
				hideWait();
				if (textStatus === "timeout") {
					alert(' importOneInvoice server timeout (2) - your dolibarr server does not return data as fast as required');
				}
				else {
					let fullErrorMessage = request.status + ': ' + request.statusText
					console.log(" importOneInvoice error (2)" + fullErrorMessage);
					alert('Local API server error (2) - your dolibarr server does not return data as fast as required:' + fullErrorMessage);
				}
				//try next ?
				reject();
				console.log(" importOneInvoice error, try next ?" + nb + ' et ' + ListeFichiers.length);
				if ((nb + 1) < ListeFichiers.length) {
				  if(ListeFichiers[nb+1].id != "") {
					importOneInvoice(nb+1,ListeFichiers[nb+1].id, fournID)
				  }
				}
			}
		});
	});
}

$(document).ready(function () {
	// Upload and interactive import
	// Set our target URL
	ZeroUpload.setURL('uploadauto.php?token=<?php echo currentToken(); ?>');

	// Set the maximum upload size (should match your server-side limit)
	ZeroUpload.setMaxBytes(8 * 1024 * 1024); // 8 MB

	// Now let's define some event listeners...
	ZeroUpload.on('start', function (files, userData) {
		// Upload has started
		// `files` is an array of files queued for upload
		// `userData` is your user data value, if applicable
		ListeFichiers = files;
		$('#formupload').hide();
		$('#ocr-server-card').hide();
		let html = "<div class='div-table-responsive-no-min'><table class='ScanInvoicesStyled-table'>";
		html += "<thead>";
		html += createTableHeader();
		html += "</thead>";
		html += "<tbody>";
		for (let i = 0; i < files.length; i++) {
			console.log(files[i]);
			html += createTableRow(i, files[i].name, "<?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_WAITING") ?>", "<?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_WAITING") ?>", "<?php echo $langs->transnoentities("AUTOMATIC_IMPORT_TABLE_HEADER_WAITING") ?>");
		}
		html += "</tbody>";
		html += "</table></div>";

		$('#logmessage').html(
			html
		);
	});

	ZeroUpload.on('progress', function (progress, userData) {
		// Upload is in progress.
		// `progress.amount` is the upload progress from 0.0 to 1.0
		// `progress.percent` is the textual percentage, e.g. "50%"
		// 'userData' is your user data value, if applicable.
		$('#progress').append(
			'<div><strong>Progress:</strong> ' + progress.percent + ', ' +
			progress.elapsedHuman + ' elapsed</div>' +
			'<div><strong>Sent:</strong> ' + progress.dataSentHuman + ' of ' +
			progress.dataTotalHuman + '(' + progress.dataRateHuman + ')</div>' +
			'<div><strong>Remaining:</strong> ' + progress.remainingTimeHuman + '</div>' +
			'<div><strong>Analysing:</strong> ' + progress.remainingAnalysing + '</div>'
		);
		showWait();
	});

	ZeroUpload.on('complete', function (response, userData) {
		$('#progress').hide();
		hideWait();
		//Pour chaque fichier il faut maintenant lancer l'analyse & l'import
		console.log("Zeroupload complete " + JSON.stringify(response));
		let d = JSON.parse(response.data);

		ListeFichiers = d.row;

		if (d.error != "") {
			$.jnotify(d.error, "error", true);
		}
		else {
			if(d.action == "later") {
				console.log("scaninvoices: files stored, cron will do the job later");
			}
			else {
				console.log("scaninvoices: files stored, import queue is 'now' ... so let me do the job now !");
				globalNumFichier = 0;
				globalNbFichiers = d.nbFiles;
				console.log("scaninvoices: d=" + JSON.stringify(d));
				importOneInvoice(0,ListeFichiers[0].id, $('#fournID').val());
			}
		}
	});

	ZeroUpload.on('error', function (type, msg, userData) {
		// An error occurred!
		// 'type' will be one of the error IDs shown below.
		// 'message' is the error description string.
		// 'userData' is your user data value, if applicable.
		$('#reslogmessageults').html('<span style="font-weight:bold; color:red;">ERROR: [' + type + "]: " + msg + '</span>');
		$('#fourn0').html("Error");
		$('#fact0').html();
		$('#justif0').html();
	});

	// Initialize library
	ZeroUpload.init();

	ZeroUpload.addDropTarget('#ScanInvoicesMydrop');
	ZeroUpload.addDropTarget('#ScanInvoicesMydropLater',{ action: "later" },{ action: "later" });
});
</script>

