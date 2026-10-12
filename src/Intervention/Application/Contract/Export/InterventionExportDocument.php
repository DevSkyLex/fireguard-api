<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Export;

use Shared\Application\Message\ResultMessage;

/** Original rendered export bytes and attachment metadata captured before the owning main commit. @category Contract */
final readonly class InterventionExportDocument implements ResultMessage
{
  public function __construct(public string $documentBase64, public string $fileName, public string $contentType, public string $organizationId, public ?string $resourceId = null)
  {
  }
}
