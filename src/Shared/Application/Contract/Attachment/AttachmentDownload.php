<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Attachment;

/**
 * Contract AttachmentDownload.
 *
 * Carries authorized file bytes and safe download headers independently of an HTTP framework.
 *
 * @category Contract
 */
final readonly class AttachmentDownload
{
  /**
   * Constructor.
   *
   * @param string $contents original attachment bytes
   * @param array<string, string> $headers private download headers
   */
  public function __construct(
    public string $contents,
    public array $headers,
  ) {
  }
}
