<?php
/**
 * RemoteOCRAdapter.class.php
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

/**
 * Class RemoteOCRAdapter
 * Adapter for remote OCR services (existing paid service)
 */
class RemoteOCRAdapter implements OCRInterface
{
	private $endpoint = '';
	private $error = '';

	/**
	 * Constructor
	 *
	 * @param string $endpoint Remote OCR service endpoint URL
	 */
	public function __construct($endpoint = '')
	{
		$this->endpoint = $endpoint ?: getDolGlobalString('SCANINVOICES_URI');
	}

	/**
	 * Check if remote service is available
	 *
	 * @return bool
	 */
	public function isAvailable()
	{
		if (empty($this->endpoint)) {
			$this->error = 'Remote OCR endpoint not configured';
			return false;
		}

		dol_syslog('RemoteOCRAdapter::isAvailable checking ' . $this->endpoint);
		$result = getURLContent($this->endpoint . '/api/ping', 'GET', '', 1, [], ['http', 'https'], 2);

		if (is_array($result) && $result['http_code'] == 200) {
			return true;
		}

		$this->error = 'Remote OCR service unavailable';
		return false;
	}

	/**
	 * Extract text via remote service
	 *
	 * @param string $imagePath Path to image (local path)
	 * @param string $language Language code(s)
	 * @return array|false
	 */
	public function extractText($imagePath, $language = null)
	{
		dol_syslog('RemoteOCRAdapter::extractText delegating to remote service');
		// The remote service handles the extraction
		// This is a placeholder - actual extraction happens in processOcrCuts
		return [
			'text' => '',
			'status' => 'delegated_to_remote'
		];
	}

	/**
	 * Extract from region via remote service
	 *
	 * @param string $imagePath
	 * @param int $startX
	 * @param int $startY
	 * @param int $width
	 * @param int $height
	 * @param string $language
	 * @return array|false
	 */
	public function extractTextFromRegion($imagePath, $startX, $startY, $width, $height, $language = null)
	{
		dol_syslog('RemoteOCRAdapter::extractTextFromRegion delegating to remote service');
		return [
			'text' => '',
			'status' => 'delegated_to_remote'
		];
	}

	/**
	 * Process OCR cuts via remote API
	 *
	 * @param array $params Request parameters
	 * @return array Result from remote service
	 */
	public function processOcrCuts($params)
	{
		dol_syslog('RemoteOCRAdapter::processOcrCuts calling remote endpoint');

		if (empty($this->endpoint)) {
			$this->error = 'Remote endpoint not configured';
			return [
				'error' => $this->error,
				'http_code' => 500
			];
		}

		$url = $this->endpoint . '/api/ocrcuts';
		dol_syslog('RemoteOCRAdapter::processOcrCuts calling ' . $url);

		$result = getURLContent(
			$url,
			'POST',
			json_encode($params),
			1,
			scanInvoicesApiCommonHeader(),
			['http', 'https'],
			2
		);

		if (is_array($result)) {
			if ($result['http_code'] == 200 && isset($result['content'])) {
				return [
					'result' => $result['content'],
					'http_code' => 200
				];
			}
		}

		$this->error = 'Remote service error: ' . ($result['curl_error_msg'] ?? 'Unknown error');
		dol_syslog('RemoteOCRAdapter Error: ' . $this->error, LOG_ERR);

		return [
			'error' => $this->error,
			'http_code' => isset($result['http_code']) ? $result['http_code'] : 500
		];
	}

	/**
	 * Get last error
	 *
	 * @return string
	 */
	public function getError()
	{
		return $this->error;
	}
}
