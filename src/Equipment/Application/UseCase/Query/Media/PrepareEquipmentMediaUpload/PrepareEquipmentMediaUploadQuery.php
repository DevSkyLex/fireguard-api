<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload;

use Shared\Application\Message\QueryMessage;

/** Requests contextual write scope and idempotent upload metadata. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class PrepareEquipmentMediaUploadQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $equipmentId, public string $userId, public ?string $interventionId = null, public ?string $clientId = null)
  {
  }
}
