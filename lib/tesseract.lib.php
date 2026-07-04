<?php
/**
 * tesseract.lib.php
 *
 * Copyright (c) 2026 ScanInvoices Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../class/TesseractOCRAdapter.class.php';

/**
 * Get or create Tesseract OCR adapter instance
 *
 * @return TesseractOCRAdapter
 */
function scaninvoicesGetTesseractAdapter()
{
	static $adapter = null;

	if ($adapter === null) {
		global $conf;
		$tesseractPath = getDolGlobalString('SCANINVOICES_TESSERACT_PATH', 'tesseract');
		$language = getDolGlobalString('SCANINVOICES_OCR_LANGUAGE', 'spa+eng');
		$adapter = new TesseractOCRAdapter($tesseractPath, $language);
	}

	return $adapter;
}

/**
 * Extract text from invoice image using Tesseract
 *
 * @param string $imagePath Path to the image file
 * @param string $ocrID OCR session ID (for compatibility)
 * @return array Result array with text and metadata
 */
function scaninvoicesExtractTextTesseract($imagePath, $ocrID = '')
{
	dol_syslog('scaninvoicesExtractTextTesseract: Processing ' . $imagePath);

	$adapter = scaninvoicesGetTesseractAdapter();

	if (!$adapter->isAvailable()) {
		dol_syslog('Tesseract OCR not available', LOG_ERR);
		return [
			'error' => 'Tesseract OCR not installed or not in PATH',
			'ocr_unavailable' => true
		];
	}

	$result = $adapter->extractText($imagePath);

	if ($result === false) {
		return [
			'error' => $adapter->getError(),
		];
	}

	return [
		'texte' => $result['text'],
		'status' => 'success',
		'ocrID' => $ocrID
	];
}

/**
 * Extract text from region of invoice image
 *
 * @param string $imagePath Path to image file
 * @param int $startX X coordinate
 * @param int $startY Y coordinate
 * @param int $width Width of region
 * @param int $height Height of region
 * @return array Result with extracted text
 */
function scaninvoicesExtractTextFromRegionTesseract($imagePath, $startX, $startY, $width, $height)
{
	dol_syslog("scaninvoicesExtractTextFromRegionTesseract: region x=$startX, y=$startY, w=$width, h=$height");

	$adapter = scaninvoicesGetTesseractAdapter();

	if (!$adapter->isAvailable()) {
		return [
			'error' => 'Tesseract OCR not available',
			'texte' => ''
		];
	}

	$result = $adapter->extractTextFromRegion($imagePath, $startX, $startY, $width, $height);

	if ($result === false) {
		return [
			'error' => $adapter->getError(),
			'texte' => ''
		];
	}

	return [
		'texte' => $result['text'],
		'status' => 'success'
	];
}

/**
 * Process OCR cuts request locally (replaces remote API call)
 * This function mimics the behavior of the remote OCR service
 *
 * @param array $params Request parameters
 * @return array Result with OCR data
 */
function scaninvoicesProcessOcrCuts($params)
{
	dol_syslog('scaninvoicesProcessOcrCuts: Processing OCR request');

	// Extract parameters
	$ocrID = $params['ocrID'] ?? '';
	$filename = $params['filename'] ?? '';
	$jsonRect = $params['jsonRect'] ?? [];
	$action = $params['action'] ?? '';
	$lang = $params['lang'] ?? 'spa+eng';

	// Find image file
	$imagePath = scaninvoicesFindpathfor(str_replace('.pdf', '.jpg', $filename), DOL_DATA_ROOT . '/scaninvoices/uploads/');

	if (!file_exists($imagePath . str_replace('.pdf', '.jpg', $filename))) {
		dol_syslog('Image file not found: ' . $imagePath . str_replace('.pdf', '.jpg', $filename), LOG_ERR);
		return [
			'result' => json_encode(['error' => 'Image not found']),
			'http_code' => 404
		];
	}

	$imageFullPath = $imagePath . str_replace('.pdf', '.jpg', $filename);
	$adapter = scaninvoicesGetTesseractAdapter();
	$adapter->setLanguage($lang);

	if (!$adapter->isAvailable()) {
		return [
			'result' => json_encode(['error' => 'OCR engine not available']),
			'http_code' => 503
		];
	}

	$results = new stdClass();

	if ($action === 'multicut' && !empty($jsonRect)) {
		// Process multiple regions
		foreach ($jsonRect as $key => $rect) {
			if (empty($rect)) {
				continue;
			}

			// Parse rect format: "x:y:w:h"
			$coords = explode(':', $rect);
			if (count($coords) === 4) {
				$startX = (int)$coords[0];
				$startY = (int)$coords[1];
				$width = (int)$coords[2];
				$height = (int)$coords[3];

				$regionResult = $adapter->extractTextFromRegion(
					$imageFullPath,
					$startX,
					$startY,
					$width,
					$height,
					$lang
				);

				if ($regionResult) {
					// Extract field name from key (e.g., 'fournisseurRect' -> 'fournisseur')
					$fieldName = str_replace('Rect', '', $key);
					$results->$fieldName = trim($regionResult['text']);
				}
			}
		}
	} elseif ($action === 'rect') {
		// Single rectangle extraction
		$rect = $params['rect'] ?? '';
		if (!empty($rect)) {
			$coords = explode(':', $rect);
			if (count($coords) === 4) {
				$startX = (int)$coords[0];
				$startY = (int)$coords[1];
				$width = (int)$coords[2];
				$height = (int)$coords[3];

				$regionResult = $adapter->extractTextFromRegion(
					$imageFullPath,
					$startX,
					$startY,
					$width,
					$height,
					$lang
				);

				if ($regionResult) {
					$results->texte = trim($regionResult['text']);
				}
			}
		}
	}

	dol_syslog('scaninvoicesProcessOcrCuts: Result: ' . json_encode($results));

	return [
		'result' => json_encode(['result' => $results]),
		'http_code' => 200
	];
}
