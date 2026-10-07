<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Command\ManageInventoryReference;

use Shared\Application\Message\CommandMessage;

/** @category UseCase */
final readonly class ManageInventoryReferenceCommand implements CommandMessage
{
  public function __construct(public string $organizationId, public string $type, public ?string $id = null, public ?string $code = null, public ?string $label = null, public ?string $unit = null, public ?string $kind = null, public ?bool $archived = null)
  {
  }
}
