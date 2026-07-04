<?php
/**
 * TesseractOCRAdapter.class.php
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
 * Class TesseractOCRAdapter
 * Local OCR adapter using Tesseract (free and open-source)
 */
class TesseractOCRAdapter
{
	private $tesseractPath = 'tesseract';
	private $language = 'spa+eng'; // Spanish + English by default
	private $error = '';

	/**
	 * Constructor
	 *
	 * @param string $tesseractPath Path to tesseract executable (default: 'tesseract' in PATH)
	 * @param string $language Language(s) for OCR (default: 'spa+eng')
	 */
	public function __construct($tesseractPath = 'tesseract', $language = 'spa+eng')
	{
		$this->tesseractPath = $tesseractPath;
		$this->language = $language;
	}

	/**
	 * Check if Tesseract is installed and available
	 *
	 * @return bool True if Tesseract is available
	 */
	public function isAvailable()
	{
		$output = [];
		$returnVar = 0;
		@exec($this->tesseractPath . ' --version 2>&1', $output, $returnVar);
		return $returnVar === 0;
	}

	/**
	 * Extract text from image file
	 *
	 * @param string $imagePath Path to image file
	 * @param string $language OCR language(s) - optional override
	 * @return array Array with 'text' and 'confidence' keys, or false on error
	 */
	public function extractText($imagePath, $language = null)
	{
		dol_syslog('TesseractOCRAdapter::extractText from ' . $imagePath);

		if (!file_exists($imagePath)) {
			$this->error = 'Image file not found: ' . $imagePath;
			dol_syslog('TesseractOCRAdapter Error: ' . $this->error, LOG_ERR);
			return false;
		}

		$lang = $language ?: $this->language;
		$tempOutput = tempnam(sys_get_temp_dir(), 'tesseract_');

		// Build Tesseract command
		$command = escapeshellcmd($this->tesseractPath) . ' ' .
			escape shellarg($imagePath) . ' ' .
			escape shellarg($tempOutput) . ' ' .
			'-l ' . escapeshellarg($lang) . ' ' .
			'--oem 1 2>&1';

		dol_syslog('TesseractOCRAdapter::extractText command: ' . $command);

		$output = [];
		$returnVar = 0;
		@exec($command, $output, $returnVar);

		if ($returnVar !== 0) {
			$this->error = 'Tesseract error: ' . implode(' ', $output);
			dol_syslog('TesseractOCRAdapter Error: ' . $this->error, LOG_ERR);
			@unlink($tempOutput . '.txt');
			return false;
		}

		$outputFile = $tempOutput . '.txt';
		if (!file_exists($outputFile)) {
			$this->error = 'Tesseract output file not generated';
			dol_syslog('TesseractOCRAdapter Error: ' . $this->error, LOG_ERR);
			return false;
		}

		$text = file_get_contents($outputFile);
		@unlink($outputFile);
		@unlink($tempOutput);

		if ($text === false) {
			$this->error = 'Failed to read Tesseract output';
			dol_syslog('TesseractOCRAdapter Error: ' . $this->error, LOG_ERR);
			return false;
		}

		dol_syslog('TesseractOCRAdapter::extractText success, text length: ' . strlen($text));

		return [
			'text' => trim($text),
			'confidence' => 0, // Tesseract doesn't provide per-page confidence easily
			'status' => 'success'
		];
	}

	/**
	 * Extract text from specific region/rectangle of image
	 *
	 * @param string $imagePath Path to image file
	 * @param int $startX X coordinate of top-left corner
	 * @param int $startY Y coordinate of top-left corner
	 * @param int $width Width of region
	 * @param int $height Height of region
	 * @param string $language OCR language(s) - optional override
	 * @return array Array with 'text' and metadata
	 */
	public function extractTextFromRegion($imagePath, $startX, $startY, $width, $height, $language = null)
	{
		dol_syslog("TesseractOCRAdapter::extractTextFromRegion: x=$startX, y=$startY, w=$width, h=$height");

		if (!file_exists($imagePath)) {
			$this->error = 'Image file not found: ' . $imagePath;
			return false;
		}

		// Create temporary cropped image
		$croppedImage = $this->cropImage($imagePath, $startX, $startY, $width, $height);
		if ($croppedImage === false) {
			return false;
		}

		// Extract text from cropped image
		$result = $this->extractText($croppedImage, $language);
		@unlink($croppedImage);

		return $result;
	}

	/**
	 * Crop image to region and return path to cropped image
	 *
	 * @param string $imagePath Original image path
	 * @param int $startX X coordinate
	 * @param int $startY Y coordinate
	 * @param int $width Width
	 * @param int $height Height
	 * @return string|false Path to cropped image or false on error
	 */
private function cropImage($imagePath, $startX, $startY, $width, $height)
	{
		try {
			$image = new Imagick($imagePath);
			$image->cropImage($width, $height, $startX, $startY);

			$tempFile = tempnam(sys_get_temp_dir(), 'tesseract_crop_') . '.jpg';
			$image->writeImage($tempFile);
			$image->destroy();

			return $tempFile;
		} catch (Exception $e) {
			$this->error = 'Failed to crop image: ' . $e->getMessage();
			dol_syslog('TesseractOCRAdapter Error: ' . $this->error, LOG_ERR);
			return false;
		}
	}

	/**
	 * Get last error message
	 *
	 * @return string Error message
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * Set OCR language
	 *
	 * @param string $language Language code(s) for Tesseract (e.g., 'spa', 'eng', 'spa+eng')
	 * @return void
	 */
	public function setLanguage($language)
	{
		$this->language = $language;
	}
}
