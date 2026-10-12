<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Inbound\FreshEquipmentScopePort;

use function is_string;

/** Reads Equipment-owned current organization facts on the explicitly wired main connection. @category Adapter */
final readonly class FreshEquipmentScopeAdapter implements FreshEquipmentScopePort
{
  public function __construct(private Connection $connection)
  {
  }

  public function organizationId(string $equipmentId): ?string
  {
    $value = $this->connection->fetchOne('SELECT organization_id FROM equipment WHERE id = :id', ['id' => $equipmentId]);

    return is_string($value) ? $value : null;
  }
}
