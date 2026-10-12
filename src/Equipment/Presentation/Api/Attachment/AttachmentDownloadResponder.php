<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Attachment;

use Shared\Application\Port\Outbound\AttachmentDownloadPort;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service AttachmentDownloadResponder.
 *
 * Converts the shared, framework-independent attachment download into this module's HTTP response.
 *
 * @category Service
 */
final readonly class AttachmentDownloadResponder
{
  /**
   * Constructor.
   *
   * @param AttachmentDownloadPort $downloads shared download header policy
   */
  public function __construct(private AttachmentDownloadPort $downloads)
  {
  }

  /**
   * Method download.
   *
   * @param string $contents authorized attachment bytes
   * @param string $fileName original client file name
   * @param string $mimeType stored MIME type
   *
   * @return Response private attachment-disposition download
   */
  public function download(string $contents, string $fileName, string $mimeType): Response
  {
    $download = $this->downloads->download($contents, $fileName, $mimeType);

    return new Response($download->contents, Response::HTTP_OK, $download->headers);
  }
}
