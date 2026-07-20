<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Attachment;

use Symfony\Component\HttpFoundation\{HeaderUtils, Response};

use function preg_replace;
use function str_replace;
use function strlen;
use function trim;

/**
 * Service AttachmentDownloadResponder.
 *
 * Builds the HTTP {@see Response} that serves a stored attachment's raw bytes
 * as a browser download. The generalized transport half of every module's
 * attachment-download endpoint — the counterpart of {@see MultipartAttachmentGuard}
 * on the upload side — so Messaging, Inspection, Intervention, Facility and
 * Equipment can all serve their attachments with one consistent, safe header
 * policy instead of hand-rolling `Content-Disposition` each time.
 *
 * Security posture (deliberate, and the reason this is a single shared helper
 * rather than an inline `new Response`): the bytes are USER-uploaded, so the
 * response ALWAYS forces `Content-Disposition: attachment` (never `inline`)
 * and `X-Content-Type-Options: nosniff`, which together stop a browser from
 * rendering a malicious `text/html`/SVG upload in the app's origin (stored
 * XSS) — the file is downloaded, never executed. `Cache-Control: private`
 * keeps an authorized private file out of shared caches.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AttachmentDownloadResponder
{
  // #region Constants
  /**
   * Constant FALLBACK_CONTENT_TYPE.
   *
   * Served when the stored MIME type is empty or unknown — an opaque binary
   * blob the browser will download rather than try to interpret.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string FALLBACK_CONTENT_TYPE = 'application/octet-stream';
  // #endregion

  // #region Methods
  /**
   * Method download.
   *
   * Builds an attachment-disposition download response for the given bytes.
   *
   * @since 1.0.0
   *
   * @param string $contents the raw file contents
   * @param string $fileName the original client file name, used for the download prompt
   * @param string $mimeType the stored MIME type; falls back to application/octet-stream when blank
   *
   * @return Response the download response
   */
  public function download(string $contents, string $fileName, string $mimeType): Response
  {
    $safeName = '' !== trim($fileName) ? $fileName : 'download';
    $contentType = '' !== trim($mimeType) ? $mimeType : self::FALLBACK_CONTENT_TYPE;

    $disposition = HeaderUtils::makeDisposition(
      HeaderUtils::DISPOSITION_ATTACHMENT,
      $safeName,
      $this->asciiFallback($safeName),
    );

    return new Response($contents, Response::HTTP_OK, [
      'Content-Type' => $contentType,
      'Content-Length' => (string) strlen($contents),
      'Content-Disposition' => $disposition,
      'X-Content-Type-Options' => 'nosniff',
      'Cache-Control' => 'private, no-store',
    ]);
  }

  /**
   * Method asciiFallback.
   *
   * Derives the RFC 6266 `filename` fallback token required by
   * {@see HeaderUtils::makeDisposition()}: pure US-ASCII with no path
   * separators, percent, or quote characters (any of which the helper
   * rejects). Non-ASCII code points are preserved via the `filename*`
   * parameter the helper adds; this fallback only serves ancient clients.
   *
   * @since 1.0.0
   *
   * @param string $fileName the original file name
   *
   * @return string a safe ASCII fallback file name
   */
  private function asciiFallback(string $fileName): string
  {
    $ascii = preg_replace('/[^\x20-\x7e]/', '_', $fileName) ?? '';
    $ascii = str_replace(['%', '/', '\\', '"'], '_', $ascii);
    $ascii = trim($ascii);

    return '' === $ascii ? 'download' : $ascii;
  }
  // #endregion
}
