<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ResumeImportJob;

use Shared\Application\Message\CommandMessage;

final readonly class ResumeImportJobCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $importJobId)
  {
  }
}
