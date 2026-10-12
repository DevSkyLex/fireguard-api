<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Facility\Application\Port\Inbound\FreshFacilityScopePort;

use function is_string;

/** Reads Facility-owned current organization facts on the explicitly wired main connection. @category Adapter */
final readonly class FreshFacilityScopeAdapter implements FreshFacilityScopePort
{
  public function __construct(private Connection $connection)
  {
  }

  public function organizationId(string $facilityId): ?string
  {
    $value = $this->connection->fetchOne('SELECT organization_id FROM facilities WHERE id = :id', ['id' => $facilityId]);

    return is_string($value) ? $value : null;
  }
}
