<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Attachment;

use Shared\Domain\Attachment\{AttachmentConstraints, InvalidAttachmentException};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnprocessableEntityHttpException};

use function in_array;
use function is_string;
use function sprintf;

/**
 * Service MultipartAttachmentGuard.
 *
 * Extracts and validates the uploaded file of a multipart attachment
 * request, enforcing {@see AttachmentConstraints} BEFORE the file contents
 * are read into memory (size is read from filesystem metadata via
 * `UploadedFile::getSize()`, not by consuming the stream). Reused by every
 * module's `<Module>MediaProcessor` (Inspection, Intervention, Facility).
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MultipartAttachmentGuard
{
  // #region Methods
  /**
   * Method fromRequest.
   *
   * Extracts and validates the uploaded file carried by the given request.
   *
   * @since 1.0.0
   *
   * @param Request $request the current HTTP request
   * @param string $fileField the multipart field holding the file
   * @param string $labelField the multipart field holding the optional label
   * @param ?list<string> $allowedMimeTypes when given, replaces the shared MIME
   *                                        allow-list for this call (size cap still
   *                                        applies) — for a kind-specific policy
   *                                        narrower or wider than {@see AttachmentConstraints::allowedMimeTypes()}
   *
   * @throws BadRequestHttpException when no valid file is present
   * @throws UnprocessableEntityHttpException when the file violates the MIME/size policy
   *
   * @return UploadedAttachment the validated upload
   */
  public function fromRequest(Request $request, string $fileField = 'file', string $labelField = 'label', ?array $allowedMimeTypes = null): UploadedAttachment
  {
    $file = $request->files->get($fileField);

    if (!$file instanceof UploadedFile || !$file->isValid()) {
      throw new BadRequestHttpException(sprintf('Multipart field "%s" must be a valid uploaded file.', $fileField));
    }

    $rawSize = $file->getSize();
    $size = false === $rawSize ? 0 : $rawSize;
    $mimeType = $file->getMimeType() ?? 'application/octet-stream';

    try {
      if (null !== $allowedMimeTypes) {
        AttachmentConstraints::validateSize($size);
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
          throw InvalidAttachmentException::forMimeType($mimeType);
        }
      } else {
        AttachmentConstraints::validate($mimeType, $size);
      }
    } catch (InvalidAttachmentException $exception) {
      throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
    }

    $label = $request->request->get($labelField);

    return new UploadedAttachment(
      fileName: $file->getClientOriginalName(),
      contents: $file->getContent(),
      mimeType: $mimeType,
      size: $size,
      label: is_string($label) && '' !== $label ? $label : null,
    );
  }
  // #endregion
}
