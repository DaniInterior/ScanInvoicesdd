<?php
/**
 * ocr.lib.php
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

require_once __DIR__ . '/../class/OCRFactory.class.php';
require_once __DIR__ . '/tesseract.lib.php';

/**
 * Get OCR adapter based on current configuration
 *
 * @return OCRInterface
 */
function scaninvoicesGetOCRAdapter()
{
	return OCRFactory::getAdapter();
}

/**
 * Process OCR cuts request (multi-region extraction)
 * Routes to appropriate backend (local Tesseract or remote service)
 *
 * @param array $params Request parameters
 * @return array Result array
 */
function scaninvoicesProcessOcrCuts($params)
{
	dol_syslog('scaninvoicesProcessOcrCuts: Processing OCR request');

	$adapter = scaninvoicesGetOCRAdapter();

	if (!$adapter->isAvailable()) {
		dol_syslog('OCR service not available', LOG_ERR);
		return [
			'error' => 'OCR service not available',
			'content' => json_encode(['error' => 'OCR service not available']),
			'http_code' => 503,
			'curl_error_msg' => $adapter->getError()
		];
	}

	$result = $adapter->processOcrCuts($params);

	if (isset($result['error'])) {
		return [
			'error' => $result['error'],
			'content' => json_encode(['error' => $result['error']]),
			'http_code' => $result['http_code'] ?? 500
		];
	}

	// Ensure result is properly formatted
	if (isset($result['result']) && !is_string($result['result'])) {
		$result['result'] = json_encode($result['result']);
	}

	return [
		'content' => $result['result'] ?? json_encode(['error' => 'No result']),
		'http_code' => $result['http_code'] ?? 200
	];
}

/**
 * Get configured OCR type
 *
 * @return string
 */
function scaninvoicesGetOCRType()
{
	return getDolGlobalString('SCANINVOICES_OCR_TYPE', OCRFactory::TYPE_REMOTE);
}

/**
 * Check if OCR is configured and available
 *
 * @return bool
 */
function scaninvoicesIsOCRAvailable()
{
	$adapter = scaninvoicesGetOCRAdapter();
	return $adapter->isAvailable();
}
