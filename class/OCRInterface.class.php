<?php
/**
 * OCRInterface.class.php
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

/**
 * Interface OCRInterface
 * Standard interface for OCR adapters (local or remote)
 */
interface OCRInterface
{
	/**
	 * Check if OCR service is available
	 *
	 * @return bool True if service is available
	 */
	public function isAvailable();

	/**
	 * Extract text from image
	 *
	 * @param string $imagePath Path to image file
	 * @param string $language Language code(s)
	 * @return array Result array with 'text' key
	 */
	public function extractText($imagePath, $language = null);

	/**
	 * Extract text from specific region of image
	 *
	 * @param string $imagePath Path to image file
	 * @param int $startX X coordinate
	 * @param int $startY Y coordinate
	 * @param int $width Width of region
	 * @param int $height Height of region
	 * @param string $language Language code(s)
	 * @return array Result array with 'text' key
	 */
	public function extractTextFromRegion($imagePath, $startX, $startY, $width, $height, $language = null);

	/**
	 * Process OCR cuts request (handle multiple regions)
	 *
	 * @param array $params Request parameters
	 * @return array Result array
	 */
	public function processOcrCuts($params);

	/**
	 * Get last error message
	 *
	 * @return string Error message
	 */
	public function getError();
}
