<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportTemplate;

use Shared\Application\Message\ResultMessage;

/** Result GetImportTemplateResult. UTF-8 CSV bytes generated from the backend's column contract. */
final readonly class GetImportTemplateResult implements ResultMessage
{
  public function __construct(public string $filename, public string $content, public string $mediaType = 'text/csv;charset=utf-8')
  {
  }
}
