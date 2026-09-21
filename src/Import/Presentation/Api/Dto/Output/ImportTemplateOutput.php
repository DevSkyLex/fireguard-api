<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/** Output ImportTemplateOutput. JSON transports a downloadable CSV without exposing a storage URL. */
final readonly class ImportTemplateOutput
{
  public function __construct(
    #[ApiProperty(identifier: true)]
    public string $id,
    public string $filename,
    public string $content,
    public string $mediaType,
  ) {
  }
}
