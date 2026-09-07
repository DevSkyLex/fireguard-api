<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Onboarding\Application\Contract\Setup\{OrganizationSetupOperation, OrganizationSetupSession};
use Onboarding\Application\Port\Outbound\OrganizationSetupRepositoryPort;

use function array_map;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Durable organization setup recovery.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSetupRepository implements OrganizationSetupRepositoryPort
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * @since 1.0.0
   */
  public function findSession(string $userId, bool $lock = false): ?OrganizationSetupSession
  {
    $connection = $this->entityManager->getConnection();
    if ($lock && !$connection->isTransactionActive()) {
      throw new LogicException('Setup writes require the owner main transaction.');
    }
    /** @var array{id:string,user_id:string,state:string,next_step:?string,target_organization_id:?string,creation_intent:bool,setup_operations:string}|false $row */
    $row = $connection->fetchAssociative('SELECT id, user_id, state, next_step, target_organization_id, creation_intent, setup_operations FROM organization_onboarding_sessions WHERE user_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$userId]);
    if (false === $row) {
      return null;
    }

    return new OrganizationSetupSession($row['id'], $row['user_id'], $row['state'], $row['next_step'], $row['target_organization_id'], (bool) $row['creation_intent'], $this->decode($row['setup_operations']));
  }

  /**
   * @since 1.0.0
   *
   * @param list<OrganizationSetupOperation> $operations journal
   */
  public function saveOperations(string $sessionId, array $operations): void
  {
    $connection = $this->entityManager->getConnection();
    if (!$connection->isTransactionActive()) {
      throw new LogicException('Setup writes require the owner main transaction.');
    }
    $connection->executeStatement('UPDATE organization_onboarding_sessions SET setup_operations = ? WHERE id = ?', [json_encode(['version' => 1, 'operations' => $operations], JSON_THROW_ON_ERROR), $sessionId]);
  }

  /**
   * @since 1.0.0
   *
   * @return list<OrganizationSetupOperation> journal
   */
  public function listOperations(string $sessionId): array
  {
    /** @var string|false $value */
    $value = $this->entityManager->getConnection()->fetchOne('SELECT setup_operations FROM organization_onboarding_sessions WHERE id = ?', [$sessionId]);

    return false === $value ? [] : $this->decode($value);
  }

  /**
   * @since 1.0.0 Preserve durable mode when a completed creator flow is rolled back.
   */
  public function hasJournal(string $sessionId): bool
  {
    return (bool) $this->entityManager->getConnection()->fetchOne("SELECT setup_operations <> '[]'::jsonb FROM organization_onboarding_sessions WHERE id = ?", [$sessionId]);
  }

  /**
   * @since 1.0.0
   *
   * @return list<OrganizationSetupOperation> journal
   */
  private function decode(string $json): array
  {
    /** @var array<array-key,mixed> $document */
    $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    // Accept existing array journals; an envelope keeps durable mode after clear.
    /** @var list<array{stepKey:string,itemKey:string,payload:array<string,mixed>,resourceId:?string,resultIds?:array<string,string>}> $rows */
    $rows = $document['operations'] ?? $document;

    return array_map(static fn (array $row): OrganizationSetupOperation => new OrganizationSetupOperation($row['stepKey'], $row['itemKey'], $row['payload'], $row['resourceId'], $row['resultIds'] ?? []), $rows);
  }
}
