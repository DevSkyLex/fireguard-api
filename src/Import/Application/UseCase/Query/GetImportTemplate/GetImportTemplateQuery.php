<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportTemplate;

use Shared\Application\Message\QueryMessage;

/** Query GetImportTemplateQuery. Templates follow the selected organization's write capability. */
final readonly class GetImportTemplateQuery implements QueryMessage
{
  public function __construct(public string $userId, public string $organizationId, public string $kind)
  {
  }
}
