<?php
/**
 * OCRFactory.class.php
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

require_once 'OCRInterface.class.php';
require_once 'TesseractOCRAdapter.class.php';
require_once 'RemoteOCRAdapter.class.php';

/**
 * Class OCRFactory
 * Factory for creating OCR adapters based on configuration
 */
class OCRFactory
{
	const TYPE_TESSERACT = 'tesseract';
	const TYPE_REMOTE = 'remote';

	/**
	 * Get OCR adapter based on configuration
	 *
	 * @return OCRInterface
	 */
	public static function getAdapter()
	{
		static $adapter = null;

		if ($adapter !== null) {
			return $adapter;
		}

		global $conf;
		$ocrType = getDolGlobalString('SCANINVOICES_OCR_TYPE', self::TYPE_REMOTE);

		dol_syslog('OCRFactory::getAdapter creating OCR adapter type: ' . $ocrType);

		switch ($ocrType) {
			case self::TYPE_TESSERACT:
				$tesseractPath = getDolGlobalString('SCANINVOICES_TESSERACT_PATH', 'tesseract');
				$language = getDolGlobalString('SCANINVOICES_OCR_LANGUAGE', 'spa+eng');
				$adapter = new TesseractOCRAdapter($tesseractPath, $language);
				break;

			case self::TYPE_REMOTE:
			default:
				$endpoint = getDolGlobalString('SCANINVOICES_URI');
				$adapter = new RemoteOCRAdapter($endpoint);
				break;
		}

		return $adapter;
	}

	/**
	 * Get available OCR types
	 *
	 * @return array Array of available types
	 */
	public static function getAvailableTypes()
	{
		return [
			self::TYPE_TESSERACT => 'Tesseract (Local, Free)',
			self::TYPE_REMOTE => 'Remote Service (Paid)'
		];
	}
}
