<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;

/** Explicit primitive transport values retain exact decimal strings and operation UUIDs. */
final class ChangeSupplierInput
{
  #[Groups(['procurement:write'])]
  public ?string $name = null;

  #[Groups(['procurement:write'])]
  public ?string $code = null;

  #[Groups(['procurement:write'])]
  public ?string $email = null;

  #[Groups(['procurement:write'])]
  public ?string $phone = null;

  /**
   * @var list<array<string,mixed>>|null
   */
  #[Groups(['procurement:write'])]
  public ?array $contacts = null;
}
