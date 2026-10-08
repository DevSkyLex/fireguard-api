<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class CreatePurchaseOrderInput
 *
 * Carries creation values and an optional stable UUID for older-client compatibility.
 *
 * @category Input
 */
final class CreatePurchaseOrderInput
{
  // #region Properties
  /**
   * Property name
   */
  #[Groups(['procurement:write'])]
  public ?string $name = null;

  /**
   * Property clientOperationId
   *
   * A transport retry retains this UUID and the original creation values.
   */
  #[Groups(['procurement:write'])]
  #[Assert\Uuid]
  public ?string $clientOperationId = null;

  /**
   * Property supplierId
   */
  #[Groups(['procurement:write'])]
  #[Assert\Uuid]
  public ?string $supplierId = null;

  /**
   * Property lines
   *
   * @var list<array<string,mixed>>|null
   */
  #[Groups(['procurement:write'])]
  public ?array $lines = null;
  // #endregion
}
