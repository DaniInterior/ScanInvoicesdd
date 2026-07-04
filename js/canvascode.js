/**
 * canvascode.js
 *
 * Copyright (c) 2022 Eric Seigne <eric.seigne@cap-rel.fr>
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

var lecanvas = document.getElementById('ScanInvoicesLeCanvas');
var ctx = lecanvas.getContext('2d');
var lecanvasbackground = document.getElementById('ScanInvoicesLeCanvasBackground');
var ctxBack = lecanvasbackground.getContext('2d');
var rect = {};
var imageObj = null;
var imageOpacity = 100;
var field = null;
var fieldRect = null;
var filenamePDF = null;
var ocrID = null;
var ratio = 1;
var orgnx = 0;
var orgny = 0;
var selectBorderColor = "#4C566A";
var mouseX, mouseY;
var closeEnough = 10;
var selectLineWidth = 2;
var drag = false;  // drag object
var dragTL = dragBL = dragTR = dragBR = false; //drag corner point (resize)
var leCanvasState = null;
var canvasMove = { label: "PseudoObjectToHandleCanvasMove" };

// the total area of our drawings, can be very large now

/**
 * reset zoom on canvas
 */
function zoomReset()
{
	ctx.resetTransform();
	ctxBack.resetTransform();
	leCanvasState.valid = false;
	leCanvasState.validBack = false;
}

/**
 * Draws a rounded rectangle using the current state of the canvas.
 * If you omit the last three params, it will draw a rectangle
 * outline with a 5 pixel border radius
 * @param {CanvasRenderingContext2D} ctx
 * @param {Number} x The top left x coordinate
 * @param {Number} y The top left y coordinate
 * @param {Number} width The width of the rectangle
 * @param {Number} height The height of the rectangle
 * @param {Number} [radius = 5] The corner radius; It can also be an object
 *                 to specify different radii for corners
 * @param {Number} [radius.tl = 0] Top left
 * @param {Number} [radius.tr = 0] Top right
 * @param {Number} [radius.br = 0] Bottom right
 * @param {Number} [radius.bl = 0] Bottom left
 * @param {Boolean} [fill = false] Whether to fill the rectangle.
 * @param {Boolean} [stroke = true] Whether to stroke the rectangle.
 */
function roundRect(ctx, x, y, width, height, radius, fill, stroke)
{
	if (typeof stroke === 'undefined') {
		stroke = true;
	}
	if (typeof radius === 'undefined') {
		radius = 5;
	}
	if (typeof radius === 'number') {
		radius = { tl: radius, tr: radius, br: radius, bl: radius };
	} else {
		var defaultRadius = { tl: 0, tr: 0, br: 0, bl: 0 };
		for (var side in defaultRadius) {
			radius[side] = radius[side] || defaultRadius[side];
		}
	}
	ctx.beginPath();
	ctx.moveTo(x + radius.tl, y);
	ctx.lineTo(x + width - radius.tr, y);
	ctx.quadraticCurveTo(x + width, y, x + width, y + radius.tr);
	ctx.lineTo(x + width, y + height - radius.br);
	ctx.quadraticCurveTo(x + width, y + height, x + width - radius.br, y + height);
	ctx.lineTo(x + radius.bl, y + height);
	ctx.quadraticCurveTo(x, y + height, x, y + height - radius.bl);
	ctx.lineTo(x, y + radius.tl);
	ctx.quadraticCurveTo(x, y, x + radius.tl, y);
	ctx.closePath();
	if (fill) {
		ctx.fill();
	}
	if (stroke) {
		ctx.stroke();
	}

}

/**
 * some help during dev time, just call helpDebug on js console :)
 */
function helpDebug()
{
	console.log("Zone d'analyse fournisseurs (champ hidden form) = " + JSON.stringify($('#fournisseurRect').val()));
	let f = leCanvasState.shapes[0];
	console.log("Zone d'analyse fournisseurs (obj javascript) = " + JSON.stringify(f));
}

/**
 *
 * global initialization code when picture is loaded
 *
 * @param d data
 */
function initUniFact(d)
{
	ratio = d.ratio;

	fichier = d.filenameJPG;
	filenamePDF = "tmpFileTemplate-" + Math.random().toString().substr(2, 8);
	if (d.filenamePDF !== 'undefined' && d.filenamePDF != "") {
		filenamePDF = d.filenamePDF
	}
	// console.log("Affectation du ratio = " + ratio);

	$('#filenamePDF').val(filenamePDF);
	$('#ocrID').val(d.ocrID);
	$('#ratio').val(ratio);
	//console.log('on a ocrID: ' + d.ocrID)
	$('#logmessage').html('');


	//janv 2022 au lieu de maximiser en largeur on maximise en hauteur
	// let largeur = $('#ScanInvoicesCanvasDiv').width();
	// let hauteur = d.height * ratio;
	let hauteur = d.height;
	let largeur = d.width;//$('#ScanInvoicesCanvasDiv').width() * ratio;


	// console.log('Changement : ratio = ' + ratio + ' taille ' + largeur + " x " + hauteur);
	//twice size for smallscreen and horizontal scroll
	lecanvas.width = $('#ScanInvoicesCanvasDiv').width() * 2;
	if (lecanvas.width == 0) {
		// lecanvas.width = largeur * 2;
		// if(lecanvas.width == 0) {
		lecanvas.width = 400;
		// }
	}
	lecanvas.height = hauteur * 2;
	//revert initial idea : reuse object instead of delete and recreate them
	if ((leCanvasState === undefined) || (leCanvasState === null)) {
		// console.log("leCanvasState est ..." + leCanvasState);
		var ListeZones = ['fournisseur', 'fournisseurTva', 'ladate', 'facture', 'totalht', 'totalttc'];

		leCanvasState = new CanvasState(lecanvas, lecanvasbackground); //
		imageObj = new Image();
		// imageObj.width = largeur;// * ratio;
		// imageObj.height = hauteur;
		imageObj.posx = 0;
		imageObj.posy = 0;

		//Ajout des zones d'analyse
		let y = 50;
		for (var i = 0; i < ListeZones.length; i++) {
			let etiq = ListeZones[i];
			if (etiq) {
				//check if readonly then in that case do not create etiq
				let zetiq = $("label[for='" + etiq + "']");
				if ($('#' + etiq).is('[readonly]') ) {
					console.log(etiq + " is readonly do not create etiq");
					ListeZones.splice(i, 1);
				} else {
					let zcolor = $('#' + etiq + 'Color').val();
					leCanvasState.addShape(new Shape(etiq, zetiq.html(), 100, y, 100, 30, zcolor));
					zetiq.css("background-color", zcolor);
					y += 55;
				}
			}
		}


		//Si on a des données déjà trouvées ...
		if (d.fournisseur !== undefined) {
			console.log("on a des données comlpémentaires");
			let l = leCanvasState.shapes.length;
			if (l == 0) {
				return;
			}
			for (var i = l - 1; i >= 0; i--) {
				let field = leCanvasState.shapes[i];
				let rectName = field.name + 'Rect';
				if ($('#' + field.name).prop('readOnly') == true) {
					//nothing
				} else {
					$('#' + field.name + 'RectSaved').val(d[rectName].replace(/;/g, ':'));
					console.log("affectation de " + $('#' + field.name + 'RectSaved').val() + " sur " + rectName + 'RectSaved');
					$('#' + field.name).val(d[field.name]);
					console.log("affectation de " + d[field.name] + " sur " + field.name + " read only ? " + $('#' + field.name).prop('readOnly'));
				}
			}
			$('#ratioSaved').val(d['ratioSaved']);
			console.log("affectation du ratioSaved " + d['ratio']);
			let btn = document.getElementById('btnImportInvoice');
			btn.disabled = false;
		}

		//Reset to default pos in case of loading an other file
		imageObj.onload = function () {
			let y = 50;
			for (var i = 0; i < ListeZones.length; i++) {
				let etiq = leCanvasState.shapes[i];
				if (etiq) {
					etiq.x = 100;
					etiq.y = y;
					etiq.w = 100;
					etiq.h = 30;
					y += 55;
				}
			}
			lecanvasbackground.width = lecanvas.width;
			lecanvasbackground.height = lecanvas.height;

			//Si jamais ce fournisseur a déjà des zones d'extraction de données associées
			loadSavedPos();
			leCanvasState.valid = false;
			leCanvasState.validBack = false;
		};
	}
	imageObj.src = ("api.php/jpgfile/" + fichier + '&token=' + $('#token').val());
	//    document.getElementById('ScanInvoicesLeCanvasBackground').style.backgroundImage = "url(api.php/jpgfile/" + fichier + "&token=" + $('#token').val() + ")";

	(function loop()
	{
		if (!leCanvasState.valid) {
			leCanvasState.draw();
		}
		requestAnimationFrame(loop);
	})();

}


/**
 *
 * draw rect on select area corner
 *
 * @param {*} x x position
 * @param {*} y y position
 * @param {*} size width & height
 * @param {*} color color :)
 */
function drawCorner(x, y, size, color)
{
	ctx.fillStyle = color;
	ctx.beginPath();
	ctx.rect(x - (size / 2), y - (size / 2), size, size);
	ctx.fill();
}

/**
 *
 * Hide wait spinner
 *
 * @param from
 */
function hideWait(from)
{
	// console.log("hideWait is called from " + from);
	$('body').removeClass("loading");
}

/**
 * show waint spinner
 */
function showWait()
{
	// console.log("showWait is called");
	$('body').addClass("loading");
}

/**
 * main code : clic on import invoice into dolibarr
 */
function importInvoice()
{
	showWait();
	var testURL = "api.php/importInvoice/";
	//fix bug #21 please take all zones, even if readonly
	var l = leCanvasState.shapes.length;
	for (var i = l - 1; i >= 0; i--) {
		let field = leCanvasState.shapes[i];
		$('#' + field.name + 'Rect').val(field.x + ':' + field.y + ':' + field.w + ':' + field.h);
	}

	var allFormData = $('form').serialize();
	var ajaxRequest = $.ajax({
		url: testURL,
		timeout: 15000,
		type: "POST",
		data: allFormData,
		dataType: "json",
		success: function (data, textStatus, request) {
			// console.log(data);
			// console.log(data.facture);
			if (data.error != undefined && data.error > 0) {
				$.jnotify(data.message, "error", true);
			} else {
				let href = data.factureurl.replace(/<a href="(.*)" title.*/g, '$1');
				document.location.href = href;
			}
			// alert('Import réalisé, veuillez vérifier la facture brouillon ...');
			hideWait('importInvoice');
		},
		error: function (request, textStatus, error) {
			hideWait('importInvoice');
			if (textStatus === "timeout") {
				console.log(' importOneInvoice server timeout (3)');
			} else {
				let fullErrorMessage = request.status + ': ' + request.statusText
				console.log(" importOneInvoice error (3)" + fullErrorMessage);
				// alert('Local API server error (3) :' + fullErrorMessage);
			}
		}
	});
}

/**
 *
 * Load saved position from previous scan
 *
 */
function loadSavedPos()
{
	// console.log("loadSavedPos ...");
	let apply = false;
	let newValue = "";
	let l = leCanvasState.shapes.length;

	if (l == 0) {
		return;
	}
	for (var i = l - 1; i >= 0; i--) {
		let field = leCanvasState.shapes[i];
		let saved = $('#' + field.name + 'RectSaved').val();
		let ratioToApply = ratio / $('#ratioSaved').val();
		if (saved != "") {
			apply = true;
			console.log("On applique sur " + field.name + " saved = " + saved + " qui vaut maintenant " + $('#' + field.name + 'Rect').val() + " avec le ratio " + ratio);
			//Then move shape on canvas ...
			let arrPos = saved.split(":");
			// console.log("  -> on est sur le bon élément, avant " + JSON.stringify(field));
			let targetX = 100;
			let targetY = 100 + (50 * i);
			if (arrPos[0] > 0) {
				// console.log("loadSavePos " + l + " x=" + parseInt(arrPos[0]));
				targetX = Math.round(parseInt(arrPos[0]) * ratioToApply);
			}
			if (arrPos[1] > 0) {
				targetY = Math.round(parseInt(arrPos[1]) * ratioToApply);
			}
			if (arrPos[2] > 0) {
				field.w = Math.round(parseInt(arrPos[2]) * ratioToApply);
			}
			if (arrPos[3] > 0) {
				field.h = Math.round(parseInt(arrPos[3]) * ratioToApply);
			}

			if (targetX + field.w > leCanvasState.width)
				targetX = 100;
			if (targetY + field.h > leCanvasState.height)
				targetY = 100 + (50 * i);

			//Si jamais on avait un modele sur 2 pages et qu'on reviens à une seule ... on essaye de recaller les étiquettes
			console.log("Largeur de l'image : " + imageObj.width + ", position X de l'étiquette : " + targetX);
			if (targetX > imageObj.width) {
				console.log("Etiquette hors zone -> tentative de la positionner comme il faut");
				targetX -= imageObj.width;
			}

			field.x = targetX;
			field.y = targetY;

			$('#' + field.name + 'Rect').val(field.x + ':' + field.y + ':' + field.w + ':' + field.h);
		}
	}
	if (apply) {
		leCanvasState.valid = false;
	}
}

/**
 * main code to start OCR
 */
function runOCR()
{
	showWait();
	var l = leCanvasState.shapes.length;
	for (var i = l - 1; i >= 0; i--) {
		let field = leCanvasState.shapes[i];
		if ($('#' + field.name).prop('readOnly') == true) {
			console.log("Le champ " + field.name + " est en lecture seule, on zappe");
			//nothing
		} else {
			// #20 multicut only if zone changed since previous call OR field still empty.
			// An empty field with a known zone (saved supplier model) must be OCR'd
			// even when the user did not move the label.
			let fieldEmpty = (($('#' + field.name).val() || '').trim() == '');
			if (field.haschanged || fieldEmpty) {
				console.log("Le champ " + field.name + " a été modifié ou est vide ... lancement OCR");
				$('#' + field.name + 'Rect').val(field.x + ':' + field.y + ':' + field.w + ':' + field.h);
				//remise à zéro du flag -> a faire au retour de l'ocr
				//field.haschanged = false;
			} else {
				console.log("Le champ " + field.name + " déjà rempli et inchangé, on zappe l'OCR");
				$('#' + field.name + 'Rect').val('');
			}
		}
	}

	//$("#myForm :input[value!='']").serialize()
	$('#lang').val($('#fournisseurLang').val());
	var allFormData = $("form :input[type=hidden][value!=''][readonly!='readonly']").serialize();
	var testURL = "api.php/runocr/";
	var ajaxRequest = $.ajax({
		url: testURL,
		timeout: 20000,
		type: "POST",
		data: allFormData,
		dataType: "json",
		success: function (data, textStatus, request) {
			if (data[0].error != "") {
				$.jnotify(data[0].error, "error", true);
			} else {
				for (let k in data[0]) {
					// console.log("chargement pour " + k + " :: " + data[0][k]);
					if ($('#' + k)) {
						$('#' + k).val(data[0][k]);
						//TODO hasChanged move here ?
					}
				}
				let btn = document.getElementById('btnImportInvoice');
				btn.disabled = false;
			}
			hideWait('runOCR');
			// $("a[href='#tabs-verifications']").click();
		},
		error: function (request, textStatus, error) {
			hideWait('runOCR 2');
			if (textStatus === "timeout") {
				alert(' runOCR server timeout (3) - your dolibarr server does not return data as fast as required');
			} else {
				let fullErrorMessage = request.status + ': ' + request.statusText
				console.log(" runOCR error (1)" + fullErrorMessage);
				alert('Local API server runOCR error (3) - your dolibarr server does not return data as fast as required :' + fullErrorMessage);
			}

		}
	});

}

// get param from URL
function findGetParameter(parameterName)
{
	var result = null,
		tmp = [];
	location.search
		.substr(1)
		.split("&")
		.forEach(function (item) {
			tmp = item.split("=");
			if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
		});
	return result;
}

$(document).ready(function () {

	// Set our target URL
	ZeroUpload.setURL('upload.php?token=' + $('#token').val() + '&fournID=' + $('#fournID').val());

	// Set the maximum upload size (should match your server-side limit)
	ZeroUpload.setMaxBytes(8 * 1024 * 1024); // 8 MB

	// Now let's define some event listeners...
	ZeroUpload.on('start', function (files, userData) {
		// Upload has started
		// `files` is an array of files queued for upload
		// `userData` is your user data value, if applicable
		$('#logmessage').html("Upload has started!");
		showWait();
	});

	ZeroUpload.on('progress', function (progress, userData) {
		// Upload is in progress.
		// `progress.amount` is the upload progress from 0.0 to 1.0
		// `progress.percent` is the textual percentage, e.g. "50%"
		// 'userData' is your user data value, if applicable.
		$('#logmessage').html(
			'<div><strong>Progress:</strong> ' + progress.percent + ', ' +
			progress.elapsedHuman + ' elapsed</div>' +
			'<div><strong>Sent:</strong> ' + progress.dataSentHuman + ' of ' +
			progress.dataTotalHuman + '(' + progress.dataRateHuman + ')</div>' +
			'<div><strong>Remaining:</strong> ' + progress.remainingTimeHuman + '</div>' +
			'<div><strong>Analysing:</strong> ' + progress.remainingAnalysing + '</div>'
		);
	});

	ZeroUpload.on('complete', function (response, userData) {
		hideWait('ZeroUpload complete');
		// Upload is complete!
		// `response.code` is the HTTP response code, e.g. 200
		// `response.data` is the raw data from the server
		// 'userData' is your user data value, if applicable.
		let d = JSON.parse(response.data);
		$('#logmessage').html(
			'<p>Upload done</p>'
		);

		// let btnLoadSavedPos = document.getElementById('btnLoadSavedPos');
		// if (btnLoadSavedPos != undefined) {
		//     btnLoadSavedPos.disabled = false;
		// }
		// $("a[href='#tabs-extraction']").click();

		// console.log("Retour, erreur ? si erreur =" + d.error);
		if (d.error != "") {
			$.jnotify(d.error, "error", true);
		} else {
			let btn = document.getElementById('btnRunOCR');
			btn.disabled = false;
			initUniFact(d);
		}
	});

	ZeroUpload.on('error', function (type, msg, userData) {
		// An error occurred!
		// 'type' will be one of the error IDs shown below.
		// 'message' is the error description string.
		// 'userData' is your user data value, if applicable.
		$('#logmessage').html('<span style="font-weight:bold; color:red;">ERROR: [' + type + "]: " + msg + '</span>');
	});

	// Initialize library
	ZeroUpload.init();

	//Add some informations
	// ZeroUpload.setFileTypes( "application/pdf" );
	//ZeroUpload.addDropTarget( "#ScanInvoicesLeCanvas", { maxWidth: $(document).height(), maxWidth: "200" } );

	//Si jamais on passe le nom du pdf en GET (on arrive du module précédent)
	if (findGetParameter("filenamePDF") != null || $('#filenamePDF').val() != "") {
		let pdfValue = "";
		if ($('#filenamePDF').val()) {
			pdfValue = $('#filenamePDF').val();
		} else {
			pdfValue = findGetParameter("filenamePDF");
		}

		let localData = {
			"filenameJPG": pdfValue.replace('/.pdf/', '.jpg'),
			"maxHeight": window.innerHeight,
			"ocrID": findGetParameter("ocrID"),
			"filenamePDF": pdfValue
		};

		var testURL = "api.php/imageInfo/?token=" + $('#token').val();
		var ajaxRequest = $.ajax({
			url: testURL,
			timeout: 15000,
			type: "POST",
			data: localData,
			dataType: "json",
			success: function (data, textStatus, request) {
				// alert('succes 18 :' + data.ratio);
				localData.ratio = data.ratio;
				localData.width = data.width;
				localData.height = data.height;
				localData.ocrID = data.ocrID;
				initUniFact(localData);
				hideWait('imageInfo');
			},
			error: function (request, textStatus, error) {
				hideWait('imageInfo2');
				if (textStatus === "timeout") {
					alert(' importOneInvoice server timeout (imageInfo2)');
				} else {
					let fullErrorMessage = request.status + ': ' + request.statusText
					console.log(" importOneInvoice error (imageInfo2)" + fullErrorMessage);
					alert('Local API server error (imageInfo2) :' + fullErrorMessage);
				}
			}
		});
	}
});

// ===============================================================================================================================
// Constructor for Shape objects to hold data for all drawn objects.
// For now they will just be defined as rectangles.
function Shape(name, label, x, y, w, h, fill)
{
	this.name = name;
	this.haschanged = true;
	this.label = label;
	this.x = x || 40;
	this.y = y || 40;
	this.w = w || 100;
	this.h = h || 30;
	this.fill = fill || '#AAAAAA';
}

// Draws this shape to a given context
Shape.prototype.draw = function (ctx) {
	// console.log("draw Shape ... " + this.label);
	ctx.fillStyle = this.fill;

	ctx.fillRect(this.x, this.y, this.w, this.h);

	//handles
	drawCorner(this.x, this.y, closeEnough, this.fill);
	drawCorner(this.x + this.w, this.y, closeEnough, this.fill);
	drawCorner(this.x + this.w, this.y + this.h, closeEnough, this.fill);
	drawCorner(this.x, this.y + this.h, closeEnough, this.fill);

}

Shape.prototype.hideLabel = function (ctx) {

}

Shape.prototype.showLabel = function (ctx) {
	// console.log("Shape.prototype.showLabel show label for " + this.label);
	// add label text - disabled janv 2022 - color is enough
	ctx.font = '12px sans-serif';
	let text = ctx.measureText(this.label);
	ctx.fillStyle = '#4c566a';
	roundRect(ctx, this.x - 4, this.y - 22, text.width + 8, 20, 4, true, false);

	ctx.beginPath();
	ctx.moveTo(this.x + 2, this.y - 2);
	ctx.lineTo(this.x + 10, this.y - 2);
	ctx.lineTo(this.x + 10, this.y + 6);
	ctx.fill();

	// ctx.fillRect(this.x - 4, this.y - 18, text.width + 8, 20);
	ctx.fillStyle = '#ffffff';
	ctx.fillText(this.label, this.x, this.y - 8);
}

// Determine if a point is inside the shape's bounds
Shape.prototype.contains = function (mx, my) {
	// All we have to do is make sure the Mouse X,Y fall in the area between
	// the shape's X and (X + Width) and its Y and (Y + Height)
	return (this.x <= mx) && (this.x + this.w >= mx) &&
		(this.y <= my) && (this.y + this.h >= my);
}

// Determine if a point is inside the shape's TopLeft corner bounds
Shape.prototype.containsTL = function (mx, my) {
	minX = this.x - (closeEnough / 2);
	maxX = this.x + (closeEnough / 2);
	minY = this.y - (closeEnough / 2);
	maxY = this.y + (closeEnough / 2);
	return (minX <= mx) && (maxX >= mx) &&
		(minY <= my) && (maxY >= my);
}

// Determine if a point is inside the shape's TopRight corner bounds
Shape.prototype.containsTR = function (mx, my) {
	minX = this.x + this.w - (closeEnough / 2);
	maxX = this.x + this.w + (closeEnough / 2);
	minY = this.y - (closeEnough / 2);
	maxY = this.y + (closeEnough / 2);
	return (minX <= mx) && (maxX >= mx) &&
		(minY <= my) && (maxY >= my);
}

// Determine if a point is inside the shape's BottomLeft corner bounds
Shape.prototype.containsBL = function (mx, my) {
	minX = this.x - (closeEnough / 2);
	maxX = this.x + (closeEnough / 2);
	minY = this.y + this.h - (closeEnough / 2);
	maxY = this.y + this.h + (closeEnough / 2);
	return (minX <= mx) && (maxX >= mx) &&
		(minY <= my) && (maxY >= my);
}

// Determine if a point is inside the shape's BottomRight corner bounds
Shape.prototype.containsBR = function (mx, my) {
	minX = this.x + this.w - (closeEnough / 2);
	maxX = this.x + this.w + (closeEnough / 2);
	minY = this.y + this.h - (closeEnough / 2);
	maxY = this.y + this.h + (closeEnough / 2);
	return (minX <= mx) && (maxX >= mx) &&
		(minY <= my) && (maxY >= my);
}

function CanvasState(canvas, canvasBack)
{
	//
	// **** First some setup! ****
	this.canvas = canvas;
	this.ctx = canvas.getContext('2d');

	this.width = canvas.width;
	this.height = canvas.height;

	this.canvasBack = canvasBack;
	this.ctxBack = canvasBack.getContext('2d');

	// This complicates things a little but but fixes mouse co-ordinate problems
	// when there's a border or padding. See getMouse for more detail
	var stylePaddingLeft, stylePaddingTop, styleBorderLeft, styleBorderTop;
	if (document.defaultView && document.defaultView.getComputedStyle) {
		this.stylePaddingLeft = parseInt(document.defaultView.getComputedStyle(canvas, null)['paddingLeft'], 0) || 0;
		this.stylePaddingTop = parseInt(document.defaultView.getComputedStyle(canvas, null)['paddingTop'], 0) || 0;
		this.styleBorderLeft = parseInt(document.defaultView.getComputedStyle(canvas, null)['borderLeftWidth'], 0) || 0;
		this.styleBorderTop = parseInt(document.defaultView.getComputedStyle(canvas, null)['borderTopWidth'], 0) || 0;
	}
	// Some pages have fixed-position bars (like the stumbleupon bar) at the top or left of the page
	// They will mess up mouse coordinates and this fixes that
	var html = document.body.parentNode;
	this.htmlTop = html.offsetTop;
	this.htmlLeft = html.offsetLeft;

	// **** Keep track of state! ****

	this.valid = false; // when set to false, the canvas will redraw everything
	this.shapes = [];  // the collection of things to be drawn
	this.dragging = false; // Keep track of when we are dragging
	// the current selected object. In the future we could turn this into an array for multiple selection
	this.selection = null;
	this.dragoffx = 0; // See mousedown and mousemove events for explanation
	this.dragoffy = 0;

	// **** Then events! ****

	// This is an example of a closure!
	// Right here "this" means the CanvasState. But we are making events on the Canvas itself,
	// and when the events are fired on the canvas the variable "this" is going to mean the canvas!
	// Since we still want to use this particular CanvasState in the events we have to save a reference to it.
	// This is our reference!
	var myState = this;


	// ************************** mouse events
	this.canvasMouseDown = function (e) {
		e.preventDefault();
		var mouse = myState.getMouse(e);
		var mx = mouse.x;
		let my = mouse.y;
		var myt = mouse.y - imageObj.posy;
		let actionOK = false;
		var shapes = myState.shapes;
		var l = shapes.length;
		console.log("canvasMouseDown x=" + mx + ", y=" + my);

		for (var i = l - 1; i >= 0; i--) {
			if (shapes[i].contains(mx, my)) {
				// console.log("clic sur une étiquette ...");
				actionOK = true;
				// console.log("Vérification dans le for : actionOK=" + actionOK);
				$('#ScanInvoicesCanvasDiv').css('cursor', 'grab');
				var mySel = shapes[i];
				// Keep track of where in the object we clicked
				// so we can move it smoothly (see mousemove)
				myState.dragoffx = mx - mySel.x;
				myState.dragoffy = my - mySel.y;
				myState.dragging = true;
				myState.selection = mySel;
				myState.valid = false;
				mySel.haschanged = true;
				return;
			} else if (shapes[i].containsTL(mx, my)) {
				actionOK = true;
				// console.log("clic sur la poignée haut gauche d'une étiquette ...");
				$('#ScanInvoicesCanvasDiv').css('cursor', 'nw-resize');
				myState.dragTL = true;
				var mySel = shapes[i];
				myState.dragoffx = mx - mySel.x;
				myState.dragoffy = my - mySel.y;
				myState.dragging = true;
				myState.selection = mySel;
				myState.valid = false;
				mySel.haschanged = true;
				return;
			} else if (shapes[i].containsTR(mx, my)) {
				actionOK = true;
				// console.log("clic sur la poignée haut droite d'une étiquette ...");
				$('#ScanInvoicesCanvasDiv').css('cursor', 'ne-resize');
				myState.dragTR = true;
				var mySel = shapes[i];
				myState.dragoffx = mx - mySel.x;
				myState.dragoffy = my - mySel.y;
				myState.dragging = true;
				myState.selection = mySel;
				myState.valid = false;
				mySel.haschanged = true;
				return;
			} else if (shapes[i].containsBL(mx, my)) {
				actionOK = true;
				// console.log("clic sur la poignée bas gauche d'une étiquette ...");
				$('#ScanInvoicesCanvasDiv').css('cursor', 'sw-resize');
				myState.dragBL = true;
				var mySel = shapes[i];
				myState.dragoffx = mx - mySel.x;
				myState.dragoffy = my - mySel.y;
				myState.dragging = true;
				myState.selection = mySel;
				myState.valid = false;
				mySel.haschanged = true;
				return;
			} else if (shapes[i].containsBR(mx, my)) {
				actionOK = true;
				// console.log("clic sur la poignée bas droite d'une étiquette ...");
				$('#ScanInvoicesCanvasDiv').css('cursor', 'se-resize');
				myState.dragBR = true;
				var mySel = shapes[i];
				myState.dragoffx = mx - mySel.x;
				myState.dragoffy = my - mySel.y;
				myState.dragging = true;
				myState.selection = mySel;
				myState.valid = false;
				mySel.haschanged = true;
				return;
			}
		}
		// console.log("Vérification après for : actionOK=" + actionOK);
		if (!actionOK) {
			// console.log("Captation !actionOK -> deplacement du canvas");
			myState.dragoffx = mx;
			myState.dragoffy = my;
			myState.dragging = true;
			myState.valid = false;
			myState.selection = canvasMove;
		} else {
			// havent returned means we have failed to select anything.
			// If there was an object selected, we deselect it
			if (myState.selection) {
				myState.selection = null;
				myState.valid = false; // Need to clear the old selection border
			}
		}
	}

	this.canvasMouseMove = function (e) {
		var mouse = myState.getMouse(e);
		var mx = mouse.x;
		var my = mouse.y;
		if (myState.dragging) {
			// console.log("Drag en cours ..." + JSON.stringify(myState));
			if (myState.dragTL) {
				// console.log("Capture le bug : " + JSON.stringify(mouse) + " :: " + JSON.stringify(myState.selection));
				myState.selection.w += myState.selection.x - mx;
				myState.selection.h += myState.selection.y - my;
				myState.selection.x = mx;
				myState.selection.y = my;
				myState.valid = false; // Something's dragging so we must redraw
			} else if (myState.dragTR) {
				myState.selection.w = Math.abs(myState.selection.x - mx);
				myState.selection.h += myState.selection.y - my;
				myState.selection.y = my;
				myState.valid = false; // Something's dragging so we must redraw
			} else if (myState.dragBL) {
				myState.selection.w += myState.selection.x - mx;
				myState.selection.h = Math.abs(myState.selection.y - my);
				myState.selection.x = mx;
				myState.valid = false; // Something's dragging so we must redraw
			} else if (myState.dragBR) {
				myState.selection.w = Math.abs(myState.selection.x - mx);
				myState.selection.h = Math.abs(myState.selection.y - my);
				myState.valid = false; // Something's dragging so we must redraw
			} else {
				//move object or move canvas ?
				if (myState.selection == canvasMove) {
					$('#ScanInvoicesCanvasDiv').css('cursor', 'grab');

					ctx.translate(e.movementX, e.movementY);
					ctxBack.translate(e.movementX, e.movementY);
					myState.valid = false; // Something's dragging so we must redraw
					myState.validBack = false;
				} else {
					// console.log("DEPLACEMENT d'une étiquette");
					$('#ScanInvoicesCanvasDiv').css('cursor', 'grabbing');
					// We don't want to drag the object by its top-left corner, we want to drag it
					// from where we clicked. Thats why we saved the offset and use it here
					myState.selection.x = mx - myState.dragoffx;
					myState.selection.y = my - myState.dragoffy;
					myState.valid = false; // Something's dragging so we must redraw
				}
			}
		} else {
			$('#ScanInvoicesCanvasDiv').css('cursor', 'default');
			var shapes = myState.shapes;
			var l = shapes.length;
			for (var i = l - 1; i >= 0; i--) {
				if (shapes[i].contains(mx, my)) {
					$('#ScanInvoicesCanvasDiv').css('cursor', 'grab');
					shapes[i].showLabel(ctx);
				} else if (shapes[i].containsTL(mx, my)) {
					$('#ScanInvoicesCanvasDiv').css('cursor', 'nw-resize');
				} else if (shapes[i].containsTR(mx, my)) {
					$('#ScanInvoicesCanvasDiv').css('cursor', 'ne-resize');
				} else if (shapes[i].containsBL(mx, my)) {
					$('#ScanInvoicesCanvasDiv').css('cursor', 'sw-resize');
				} else if (shapes[i].containsBR(mx, my)) {
					$('#ScanInvoicesCanvasDiv').css('cursor', 'se-resize');
				}
			}
		}
	}

	this.canvasMouseUp = function (e) {
		myState.dragging = false;
		myState.dragTL = myState.dragTR = myState.dragBL = myState.dragBR = false;
		myState.selection = null;
		// console.log("Mouse UP");
	}

	this.canvasMouseLeave = function (e) {
		myState.dragging = false;
		myState.dragTL = myState.dragTR = myState.dragBL = myState.dragBR = false;
		myState.selection = null;
	}

	this.canvasMouseWheel = function (e) {
		//Zoom on Ctrl+Mouse Wheel
		if (e.ctrlKey == true) {
			// console.log("Ctrl+Wheel :" + e.deltaY);
			e.preventDefault();

			let inverseScale = 1.02;
			let localScale = 0.9;
			if (e.deltaY < 0) {
				localScale = 1.1
				inverseScale = 0.98;
			}

			let x = e.clientX - canvas.offsetLeft;
			let y = e.clientY - canvas.offsetTop;

			ctx.translate(orgnx, orgny);
			ctxBack.translate(orgnx, orgny);

			orgnx -= x / localScale - x;
			orgny -= y / localScale - y;

			ctx.scale(localScale, localScale);
			ctx.translate(-orgnx, -orgny);

			ctxBack.scale(localScale, localScale);
			ctxBack.translate(-orgnx, -orgny);

			//Zoom avant -> poignées de selection à réduire
			closeEnough = closeEnough * inverseScale;
			selectLineWidth = selectLineWidth * inverseScale;

			leCanvasState.valid = false;
			leCanvasState.validBack = false;
			return false;
		}
	}

	// ============= touch listener
	canvas.addEventListener('touchstart', 	this.canvasMouseDown.bind(this), false);
	canvas.addEventListener('touchend', 	this.canvasMouseUp.bind(this), false);
	canvas.addEventListener('touchleave', 	this.canvasMouseLeave.bind(this), false);
	canvas.addEventListener('touchmove', 	this.canvasMouseMove.bind(this), false);

	// ============= mouse listener
	//fixes a problem where double clicking causes text to get selected on the canvas
	canvas.addEventListener('selectstart', 	function (e) {
		e.preventDefault(); return false; }, false);
	canvas.addEventListener('mousedown', 	this.canvasMouseDown.bind(this), true); //Start clic
	canvas.addEventListener('mousemove', 	this.canvasMouseMove.bind(this), true); //Move mouse
	canvas.addEventListener('mouseup', 		this.canvasMouseUp.bind(this), true); //Stop clic
	canvas.addEventListener('mouseleave', 	this.canvasMouseLeave.bind(this), true); //Leave
	canvas.addEventListener("wheel", 		this.canvasMouseWheel.bind(this), false); //zoom with Ctrl+Wheel

	this.selectionColor = selectBorderColor;
}

CanvasState.prototype.addShape = function (shape) {
	this.shapes.push(shape);
	this.valid = false;
}

CanvasState.prototype.removeShape = function (shape) {
	for (var i = 0; i < this.shapes.length; i++) {
		if (this.shapes[i] === shape) {
			this.shapes.splice(i, 1);
			i--;
		}
	}
}


CanvasState.prototype.clear = function () {
	// Clear in the untransformed coordinate space so the whole visible canvas is
	// wiped even after translate()/scale() (pan/zoom). Clearing in the current
	// transformed space left stale pixels (color trails) outside the cleared box.
	this.ctx.save();
	this.ctx.setTransform(1, 0, 0, 1, 0, 0);
	this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
	this.ctx.restore();
}

// While draw is called as often as the INTERVAL variable demands,
// It only ever does something if the canvas gets invalidated by our code
CanvasState.prototype.draw = function () {
	// if our state is invalid, redraw and validate!
	if (!this.valid) {
		var ctx = this.ctx;
		var ctxBack = this.ctxBack;

		var shapes = this.shapes;
		// ctx.clearRect(0, 0, lecanvas.width, lecanvas.height);
		this.clear();

		//force white background to remove gltches when scroll over top or over bottom of picture
		// ctx.fillStyle = "#ffffff";
		// ctx.fillRect(0, 0, lecanvas.width, lecanvas.height);

		if (!this.validBack) {
			// Clear the whole back canvas in untransformed space (avoids image
			// ghosting at pan/zoom), then redraw the image in the current transform.
			ctxBack.save();
			ctxBack.setTransform(1, 0, 0, 1, 0, 0);
			ctxBack.clearRect(0, 0, this.canvasBack.width, this.canvasBack.height);
			ctxBack.restore();

			// ctxBack.fillStyle = "#ffffff";
			// ctxBack.fillRect(0, 0, lecanvas.width, lecanvas.height);

			// console.log("Déplacement de l'image ? : " + imageObj.posx + " et " + imageObj.posy);
			ctxBack.drawImage(imageObj, imageObj.posx, imageObj.posy);
			ctxBack.filter = 'opacity(' + imageOpacity + '%)';
			this.validBack = true;
		}
		// ctx.filter = 'opacity(100%)';

		// draw all shapes
		var l = shapes.length;
		for (var i = 0; i < l; i++) {
			var shape = shapes[i];
			// We can skip the drawing of elements that have moved off the screen:
			// note : disabled due to zoom & pan
			// if (shape.x > this.width || shape.y > this.height ||
			//     shape.x + shape.w < 0 || shape.y + shape.h < 0) continue;

			//nettoyage autour de l'étiquette
			shapes[i].draw(ctx);
		}

		// draw selection
		// right now this is just a stroke along the edge of the selected Shape
		if (this.selection != null) {
			ctx.strokeStyle = this.selectionColor;
			ctx.lineWidth = selectLineWidth;
			var mySel = this.selection;
			ctx.strokeRect(mySel.x, mySel.y, mySel.w, mySel.h);
		}

		// ** Add stuff you want drawn on top all the time here **
		this.valid = true;
	}
	return true;
}

// Creates an object with x and y defined, set to the mouse position relative to the state's canvas
CanvasState.prototype.getMouse = function (e) {
	// console.log("getMouse...");
	var rect = lecanvas.getBoundingClientRect(), // abs. size of element
		scaleX = lecanvas.width / rect.width,    // relationship bitmap vs. element for X
		scaleY = lecanvas.height / rect.height;  // relationship bitmap vs. element for Y

	let xt = 0;
	let yt = 0;
	//Smartphone touch events or mouse clic ... here it is
	if (e.changedTouches) {
		// console.log("Smartphone, touches = " + JSON.stringify(touches));
		xt = (e.changedTouches[0].clientX - rect.left) * scaleX;
		yt = (e.changedTouches[0].clientY - rect.top) * scaleY;
	} else {
		xt = (e.clientX - rect.left) * scaleX;
		yt = (e.clientY - rect.top) * scaleY;
	}
	let matrix = ctx.getTransform();
	var imatrix = matrix.invertSelf();
	let x = xt * imatrix.a + yt * imatrix.c + imatrix.e;
	let y = xt * imatrix.b + yt * imatrix.d + imatrix.f;

	return {
		x: x,
		y: y
	};
}
