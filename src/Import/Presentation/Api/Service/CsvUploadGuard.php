<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnprocessableEntityHttpException};

use function in_array;
use function is_string;
use function sprintf;
use function str_ends_with;
use function strtolower;

/**
 * Service CsvUploadGuard.
 *
 * Extracts and validates the uploaded CSV file of a multipart
 * `POST /imports` request, mirroring
 * `Shared\Presentation\Api\Attachment\MultipartAttachmentGuard` — the shared
 * attachment kernel's MIME allow-list is images/PDF only, so bulk CSV
 * imports need their own policy rather than reusing it. Size is read from
 * filesystem metadata (`UploadedFile::getSize()`) before the file contents
 * are read into memory.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CsvUploadGuard
{
  // #region Constants
  /**
   * Constant MAX_SIZE_BYTES.
   *
   * The maximum accepted CSV upload size, in bytes.
   *
   * @since 1.0.0
   *
   * @var int MAX_SIZE_BYTES
   */
  public const int MAX_SIZE_BYTES = 5 * 1024 * 1024;

  /**
   * Constant ALLOWED_MIME_TYPES.
   *
   * @since 1.0.0
   *
   * @var list<string> ALLOWED_MIME_TYPES
   */
  private const array ALLOWED_MIME_TYPES = [
    'text/csv',
    'text/plain',
    'application/csv',
    'application/vnd.ms-excel',
    'application/octet-stream',
  ];
  // #endregion

  // #region Methods
  /**
   * Method fromRequest.
   *
   * @since 1.0.0
   *
   * @param Request $request the current HTTP request
   * @param string $fileField the multipart field holding the file
   * @param string $kindField the multipart field holding the import kind
   *
   * @throws BadRequestHttpException when no valid file/kind is present
   * @throws UnprocessableEntityHttpException when the file violates the MIME/size/extension policy
   *
   * @return UploadedCsv the validated upload
   */
  public function fromRequest(Request $request, string $fileField = 'file', string $kindField = 'kind'): UploadedCsv
  {
    $kind = $request->request->get($kindField);
    if (!is_string($kind) || '' === $kind) {
      throw new BadRequestHttpException(sprintf('Multipart field "%s" is required.', $kindField));
    }

    $file = $request->files->get($fileField);
    if (!$file instanceof UploadedFile || !$file->isValid()) {
      throw new BadRequestHttpException(sprintf('Multipart field "%s" must be a valid uploaded file.', $fileField));
    }

    $fileName = $file->getClientOriginalName();
    if (!str_ends_with(strtolower($fileName), '.csv')) {
      throw new UnprocessableEntityHttpException('The uploaded file must have a ".csv" extension.');
    }

    $rawSize = $file->getSize();
    $size = false === $rawSize ? 0 : $rawSize;
    if ($size > self::MAX_SIZE_BYTES) {
      throw new UnprocessableEntityHttpException(sprintf(
        'The uploaded file exceeds the maximum size of %d bytes.',
        self::MAX_SIZE_BYTES,
      ));
    }

    $mimeType = $file->getMimeType() ?? 'application/octet-stream';
    if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
      throw new UnprocessableEntityHttpException(sprintf('MIME type "%s" is not accepted for a CSV import.', $mimeType));
    }

    return new UploadedCsv(
      kind: $kind,
      fileName: $fileName,
      contents: $file->getContent(),
      mimeType: $mimeType,
      size: $size,
    );
  }
  // #endregion
}
