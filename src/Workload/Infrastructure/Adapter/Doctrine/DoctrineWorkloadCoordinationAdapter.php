<?php

declare(strict_types=1);

namespace Workload\Infrastructure\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Workload\Application\Port\Inbound\WorkloadCoordinationPort;

use function array_unique;
use function sort;

use const SORT_STRING;

/**
 * Adapter DoctrineWorkloadCoordinationAdapter.
 * Shared organization lock coordinates member writes with an exclusive organization
 * capacity edit; sorted member locks serialize all writes affecting their demand.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineWorkloadCoordinationAdapter implements WorkloadCoordinationPort
{
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicitly configured main entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * Acquires transaction-scoped organization and stably ordered member locks.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $memberIds
   * @param bool $organizationWide whether to acquire exclusive organization-wide capacity coordination
   *
   * @return void completes without returning a value
   */
  public function acquire(string $organizationId, array $memberIds, bool $organizationWide = false): void
  {
    $connection = $this->entityManager->getConnection();
    if (!$connection->isTransactionActive()) {
      throw new LogicException('Workload coordination requires the main transaction.');
    }
    $function = $organizationWide ? 'pg_advisory_xact_lock' : 'pg_advisory_xact_lock_shared';
    $connection->executeQuery('SELECT ' . $function . '(hashtextextended(:key, 0))', ['key' => 'workload:organization:' . $organizationId]);
    $members = array_unique($memberIds);
    sort($members, SORT_STRING);
    foreach ($members as $member) {
      $connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'workload:member:' . $organizationId . ':' . $member]);
    }
  }
}
