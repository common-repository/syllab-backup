<?php
/*
The plugin code, syllab-backup, limited to this Wordpress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/

*/

// https://stackoverflow.com/questions/157318/resumable-downloads-when-using-php-to-send-the-file
// User: DaveRandom

if (!class_exists('SyllabPlus_NonExistentFileException')):
class SyllabPlus_NonExistentFileException extends RuntimeException {}
endif;

if (!class_exists('SyllabPlus_UnreadableFileException')):
class SyllabPlus_UnreadableFileException extends RuntimeException {}
endif;

if (!class_exists('SyllabPlus_UnsatisfiableRangeException')):
class SyllabPlus_UnsatisfiableRangeException extends RuntimeException {}
endif;

if (!class_exists('SyllabPlus_InvalidRangeHeaderException')):
class SyllabPlus_InvalidRangeHeaderException extends RuntimeException {}
endif;

class SyllabPlus_RangeHeader
{
	/**
	 * The first byte in the file to send (0-indexed), a null value indicates the last
	 * $end bytes
	 *
	 * @var int|null
	 */
	private $firstByte;

	/**
	 * The last byte in the file to send (0-indexed), a null value indicates $start to
	 * EOF
	 *
	 * @var int|null
	 */
	private $lastByte;

	/**
	 * Create a new instance from a Range header string
	 *
	 * @param string $header
	 * @return SyllabPlus_RangeHeader
	 */
	public static function createFromHeaderString($header)
	{
		if ($header === null) {
			return null;
		}
		if (!preg_match('/^\s*([A-Za-z]+)\s*=\s*(\d*)\s*-\s*(\d*)\s*(?:,|$)/', $header, $info)) {
			throw new SyllabPlus_InvalidRangeHeaderException('Invalid header format');
		} else if (strtolower($info[1]) !== 'bytes') {
			throw new SyllabPlus_InvalidRangeHeaderException('Unknown range unit: ' . $info[1]);
		}

		return new self(
			$info[2] === '' ? null : $info[2],
			$info[3] === '' ? null : $info[3]
		);
	}

	/**
	 * @param int|null $firstByte
	 * @param int|null $lastByte
	 * @throws SyllabPlus_InvalidRangeHeaderException
	 */
	public function __construct($firstByte, $lastByte)
	{
		$this->firstByte = $firstByte === null ? $firstByte : (int)$firstByte;
		$this->lastByte = $lastByte === null ? $lastByte : (int)$lastByte;

		if ($this->firstByte === null && $this->lastByte === null) {
			throw new SyllabPlus_InvalidRangeHeaderException(
				'Both start and end position specifiers empty'
			);
		} else if ($this->firstByte < 0 || $this->lastByte < 0) {
			throw new SyllabPlus_InvalidRangeHeaderException(
				'Position specifiers cannot be negative'
			);
		} else if ($this->lastByte !== null && $this->lastByte < $this->firstByte) {
			throw new SyllabPlus_InvalidRangeHeaderException(
				'Last byte cannot be less than first byte'
			);
		}
	}

	/**
	 * Get the start position when this range is applied to a file of the specified size
	 *
	 * @param int $fileSize
	 * @return int
	 * @throws SyllabPlus_UnsatisfiableRangeException
	 */
	public function getStartPosition($fileSize)
	{
		$size = (int)$fileSize;

		if ($this->firstByte === null) {
			return ($size - 1) - $this->lastByte;
		}

		if ($size <= $this->firstByte) {
			throw new SyllabPlus_UnsatisfiableRangeException(
				'Start position is after the end of the file'
			);
		}

		return $this->firstByte;
	}

	/**
	 * Get the end position when this range is applied to a file of the specified size
	 *
	 * @param int $fileSize
	 * @return int
	 * @throws SyllabPlus_UnsatisfiableRangeException
	 */
	public function getEndPosition($fileSize)
	{
		$size = (int)$fileSize;

		if ($this->lastByte === null) {
			return $size - 1;
		}

		if ($size <= $this->lastByte) {
			throw new SyllabPlus_UnsatisfiableRangeException(
				'End position is after the end of the file'
			);
		}

		return $this->lastByte;
	}

	/**
	 * Get the length when this range is applied to a file of the specified size
	 *
	 * @param int $fileSize
	 * @return int
	 * @throws SyllabPlus_UnsatisfiableRangeException
	 */
	public function getLength($fileSize)
	{
		$size = (int)$fileSize;

		return $this->getEndPosition($size) - $this->getStartPosition($size) + 1;
	}

	/**
	 * Get a Content-Range header corresponding to this Range and the specified file
	 * size
	 *
	 * @param int $fileSize
	 * @return string
	 */
	public function getContentRangeHeader($fileSize)
	{
		return 'bytes ' . $this->getStartPosition($fileSize) . '-'
			 . $this->getEndPosition($fileSize) . '/' . $fileSize;
	}
}

class SyllabPlus_PartialFileServlet
{
	/**
	 * The range header on which the data transmission will be based
	 *
	 * @var SyllabPlus_RangeHeader|null
	 */
	private $range;

	/**
	 * @param SyllabPlus_RangeHeader $range Range header on which the transmission will be based
	 */
	public function __construct($range = null)
	{
		$this->range = $range;
	}

	/**
	 * Send part of the data in a seekable stream resource to the output buffer
	 *
	 * @param resource $fp Stream resource to read data from
	 * @param int $start Position in the stream to start reading
	 * @param int $length Number of bytes to read
	 * @param int $chunkSize Maximum bytes to read from the file in a single operation
	 */
	private function sendDataRange($fp, $start, $length, $chunkSize = 2097152)
	{
		if ($start > 0) {
			fseek($fp, $start, SEEK_SET);
		}

		while ($length) {
			$read = ($length > $chunkSize) ? $chunkSize : $length;
			$length -= $read;
			echo fread($fp, $read);
		}
	}

	/**
	 * Send the headers that are included regardless of whether a range was requested
	 *
	 * @param string $fileName
	 * @param int $contentLength
	 * @param string $contentType
	 */
	private function sendDownloadHeaders($fileName, $contentLength, $contentType)
	{
		header('Content-Type: ' . $contentType);
		header('Content-Length: ' . $contentLength);
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header('Accept-Ranges: bytes');
	}

	/**
	 * Send data from a file based on the current Range header
	 *
	 * @param string $path Local file system path to serve
	 * @param string $contentType MIME type of the data stream
	 */
	public function sendFile($path, $contentType = 'application/octet-stream')
	{
		// Make sure the file exists and is a file, otherwise we are wasting our time
		$localPath = realpath($path);
		if ($localPath === false || !is_file($localPath)) {
			throw new SyllabPlus_NonExistentFileException(
				$path . ' does not exist or is not a file'
			);
		}

		// Make sure we can open the file for reading
		if (!$fp = fopen($localPath, 'r')) {
			throw new SyllabPlus_UnreadableFileException(
				'Failed to open ' . $localPath . ' for reading'
			);
		}

		$fileSize = filesize($localPath);

		if ($this->range == null) {
			// No range requested, just send the whole file
			header('HTTP/1.1 200 OK');
			$this->sendDownloadHeaders(basename($localPath), $fileSize, $contentType);

			fpassthru($fp);
		} else {
			// Send the request range
			header('HTTP/1.1 206 Partial Content');
			header('Content-Range: ' . $this->range->getContentRangeHeader($fileSize));
			$this->sendDownloadHeaders(
				basename($localPath),
				$this->range->getLength($fileSize),
				$contentType
			);

			$this->sendDataRange(
				$fp,
				$this->range->getStartPosition($fileSize),
				$this->range->getLength($fileSize)
			);
		}

		fclose($fp);
	}
}
