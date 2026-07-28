<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Lock;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Organization\Infrastructure\Persistence\Doctrine\Lock\PostgresOrganizationQuotaLockAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PostgresOrganizationQuotaLockAdapter.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PostgresOrganizationQuotaLockAdapter::class)]
final class PostgresOrganizationQuotaLockAdapterTest extends TestCase
{
  #[Test]
  public function testAcquireTakesATransactionScopedAdvisoryLock(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('executeQuery')->willReturnCallback(
      function (string $sql, array $params = []) use (&$executed): mixed {
        $executed = [$sql, $params];

        return $this->createStub(\Doctrine\DBAL\Result::class);
      },
    );

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);

    new PostgresOrganizationQuotaLockAdapter($entityManager)
      ->acquire('org-1', OrganizationQuotaResource::MEMBERS);

    self::assertIsArray($executed);
    self::assertStringContainsString('pg_advisory_xact_lock', $executed[0]);
    self::assertSame(['organizationId' => 'org-1', 'resource' => 'members'], $executed[1]);
  }
}
