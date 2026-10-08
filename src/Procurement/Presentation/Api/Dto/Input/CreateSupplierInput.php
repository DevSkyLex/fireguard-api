<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class CreateSupplierInput
 *
 * Carries creation values and an optional stable UUID for older-client compatibility.
 *
 * @category Input
 */
final class CreateSupplierInput
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
   * Property code
   */
  #[Groups(['procurement:write'])]
  public ?string $code = null;

  /**
   * Property email
   */
  #[Groups(['procurement:write'])]
  public ?string $email = null;

  /**
   * Property phone
   */
  #[Groups(['procurement:write'])]
  public ?string $phone = null;

  /**
   * Property contacts
   *
   * @var list<array<string,mixed>>|null
   */
  #[Groups(['procurement:write'])]
  public ?array $contacts = null;
  // #endregion
}
