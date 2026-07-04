<?php
/**
 * step1.php.
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
/** @var string $form */
/** @var string $filename */
?>

<div class="container">
	<form method="post" action="<?php echo $_SERVER["PHP_SELF"] . "?step=2"; ?>" data-submit-once>
		<input type="hidden" name="step" value="2">
		<input type="hidden" name="filenamePDF" value="<?php echo $filename ?>">
		<input type="hidden" name="maxHeight" value="">
		<input type="hidden" name="token" value="<?php echo newToken(); ?>">
		<div class="titre margintoponly marginbottomonly">
			<?php echo $langs->trans("MANUAL_IMPORT_STEP1") ?>
		</div><br>

		<div class="row">
			<div class="col-12 col-sm-12 col-md-8 margintoponly">
				<div class="card">
					<div class="card-body">
						<div class="card-title card-title">
							<?php echo $langs->trans("MANUAL_IMPORT_STEP1_CHOOSE_SUPPLIER").'...' ?>
						</div>
						<div class="info">
						<p class="card-text">
							<?php echo $langs->trans("MANUAL_IMPORT_STEP1_CHOOSE_SUPPLIER_TXT1") ?>
						</p>
						<p class="card-text hideonsmartphone">
							<?php echo $langs->trans("MANUAL_IMPORT_STEP1_CHOOSE_SUPPLIER_TXT2") ?>
						</p>
						<ul class="hideonsmartphone">
							<li>
								<?php echo $langs->trans("MANUAL_IMPORT_STEP1_CHOOSE_SUPPLIER_TXT2a") ?>
							</li>
							<li>
								<?php echo $langs->trans("MANUAL_IMPORT_STEP1_CHOOSE_SUPPLIER_TXT2b") ?>
							</li>
						</ul>
						</div>
						<p class="margintoponly marginbottomonly inline-block">
						<?php
						$fournID = GETPOST('socid', 'int');
						print img_picto('', 'company') . $form->select_company($fournID, 'fournID', 's.fournisseur=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth100 widthcentpercentminusxx maxwidth500');
						print ' <a href="' . DOL_URL_ROOT . '/societe/card.php?action=create&client=0&fournisseur=1&backtopage=' . urlencode($_SERVER["PHP_SELF"] . '?action=create&filenamePDF='. urlencode($filename)) . '"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans("AddThirdParty") . '"></span></a>';
						?>
						</p>
						<button type="submit" class="btn btn-primary inline-block marginleftonly">
							<?php echo $langs->trans("Next") ?>
						</button>
					</div>
					<p class="card-text"><small class="text-muted opacitymedium">
						<?php echo $langs->trans("MANUAL_IMPORT_STEP1_LEGEND") ?>
					</small></p>
				</div>
			</div>
			<div class="col-12 col-sm-12 col-md-4 margintoponly">
				<div id="ocr-server-card" style="padding: 1em; border: 1px solid #888; background: #f8f8f8;">
					<?php print scaninvoicesApiGetInfoAboutWebservice();?>
				</div>
			</div>
		</div>
	</form>
</div>
<script>
$(document).ready(function() {
	// If file name is passed from prev step
	$('#maxHeight').val(window.innerHeight);
});

// Anti-double-click: disable the submit button as soon as the form is submitted (cf. MODULE.md section 12)
document.querySelectorAll('form[data-submit-once]').forEach(function(form) {
	form.addEventListener('submit', function() {
		var btn = form.querySelector('[type="submit"]');
		if (btn) {
			btn.disabled = true;
			btn.dataset.originalText = btn.innerHTML;
			btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> ' + (btn.dataset.loadingText || btn.textContent);
		}
	});
});
</script>
