<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

use Onboarding\Application\Contract\Setup\{OrganizationSetupConflict, OrganizationSetupContext, OrganizationSetupOperation, OrganizationSetupSession};
use Onboarding\Application\Port\Inbound\OrganizationSetupPort;
use Onboarding\Application\Port\Outbound\OrganizationSetupRepositoryPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;

use function array_diff;
use function array_filter;
use function array_is_list;
use function array_keys;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function ksort;
use function preg_match;
use function sort;
use function strlen;
use function strtolower;
use function trim;

/**
 * Durable organization setup recovery.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSetupService implements OrganizationSetupPort
{
  private const array FIELDS = [
    'create_organization' => ['name' => '', 'slug' => null],
    'invite_members' => ['email' => '', 'roleIds' => []],
    'create_first_facility' => ['type' => '', 'name' => '', 'address' => null, 'latitude' => null, 'longitude' => null, 'code' => null, 'parentFacilityId' => null, 'metadata' => [], 'levelIndex' => null],
    'create_first_equipment' => ['type' => '', 'subType' => null, 'brand' => null, 'model' => null, 'serialNumber' => null, 'locationLabel' => null, 'facility' => null],
  ];

  /**
   * @since 1.0.0
   */
  public function __construct(private OrganizationSetupRepositoryPort $repository, private TransactionManagerPort $transactionManager)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param list<mixed> $items bounded batch
   */
  public function prepare(string $userId, string $sessionId, string $stepKey, array $items): void
  {
    $this->transactionManager->transactional(function () use ($userId, $sessionId, $stepKey, $items): void {
      $session = $this->requireSession(new OrganizationSetupContext($userId, $sessionId, ''));
      if (!isset(self::FIELDS[$stepKey]) || [] === $items || count($items) > 5) {
        throw OrganizationSetupConflict::because('Invalid setup batch.');
      }
      $submittedKeys = [];
      foreach ($items as $item) {
        if (!is_array($item)) {
          throw OrganizationSetupConflict::because('Invalid batch item.');
        }
        $submittedKeys[] = $item['itemKey'] ?? null;
      }
      $operations = array_values(array_filter($session->operations, static fn (OrganizationSetupOperation $operation): bool => $operation->stepKey !== $stepKey || null !== $operation->resourceId || in_array($operation->itemKey, $submittedKeys, true)));
      foreach ($items as $item) {
        if (!is_array($item) || !is_string($item['itemKey'] ?? null) || !is_array($item['payload'] ?? null) || 1 !== preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $item['itemKey'])) {
          throw OrganizationSetupConflict::because('Invalid item key or input.');
        }
        $payload = $this->normalize($stepKey, $item['payload']);
        $existing = $this->findOperation($operations, $stepKey, $item['itemKey']);
        if (null !== $existing) {
          if ($this->normalize($stepKey, $existing->payload) !== $payload) {
            throw OrganizationSetupConflict::because('An operation key cannot be reused with different input.');
          }

          continue;
        }
        if ($session->nextStep !== $stepKey || 'in_progress' !== $session->state) {
          throw OrganizationSetupConflict::because('This step is not available.');
        }
        $operations[] = new OrganizationSetupOperation($stepKey, $item['itemKey'], $payload);
      }
      $stepCount = 0;
      foreach ($operations as $operation) {
        if ($stepKey === $operation->stepKey) {
          ++$stepCount;
        }
      }
      $limit = in_array($stepKey, ['create_organization', 'create_first_equipment'], true) ? 1 : 5;
      if ($stepCount > $limit) {
        throw OrganizationSetupConflict::because('The setup item limit has been reached.');
      }
      $this->repository->saveOperations($sessionId, $operations);
    });
  }

  /**
   * @since 1.0.0
   *
   * @param array<string,mixed> $payload owner-command input
   */
  public function begin(OrganizationSetupContext $context, string $stepKey, ?string $organizationId, array $payload): OrganizationSetupOperation
  {
    $session = $this->requireSession($context);
    if ('create_organization' !== $stepKey && (null === $organizationId || $session->organizationId !== $organizationId)) {
      throw OrganizationSetupConflict::because('The operation does not belong to this organization.');
    }
    $operation = $this->findOperation($session->operations, $stepKey, $context->itemKey);
    if (null === $operation || $this->normalize($stepKey, $operation->payload) !== $this->normalize($stepKey, $payload)) {
      throw OrganizationSetupConflict::because('Prepare this exact operation before creating the resource.');
    }
    if (null === $operation->resourceId && ($session->nextStep !== $stepKey || 'in_progress' !== $session->state)) {
      throw OrganizationSetupConflict::because('This step is not available.');
    }

    return $operation;
  }

  /**
   * @since 1.0.0
   *
   * @param array<string,string> $resultIds replay identifiers
   */
  public function complete(OrganizationSetupContext $context, string $stepKey, string $resourceId, array $resultIds = []): void
  {
    $session = $this->requireSession($context);
    $operations = $session->operations;
    foreach ($operations as $index => $operation) {
      if ($operation->stepKey === $stepKey && $operation->itemKey === $context->itemKey) {
        if (null !== $operation->resourceId && $operation->resourceId !== $resourceId) {
          throw OrganizationSetupConflict::because('This operation already created another resource.');
        }
        $operations[$index] = new OrganizationSetupOperation($stepKey, $context->itemKey, $operation->payload, $resourceId, $resultIds);
        $this->repository->saveOperations($context->sessionId, $operations);

        return;
      }
    }

    throw OrganizationSetupConflict::because('The prepared operation is missing.');
  }

  /**
   * @since 1.0.0
   */
  private function requireSession(OrganizationSetupContext $context): OrganizationSetupSession
  {
    $session = $this->repository->findSession($context->userId, true);
    if (null === $session || $session->id !== $context->sessionId || !$session->creationIntent || 'blocked' === $session->state) {
      throw OrganizationSetupConflict::because('The creation session is unavailable.');
    }

    return $session;
  }

  /**
   * @since 1.0.0
   *
   * @param list<OrganizationSetupOperation> $operations journal
   */
  private function findOperation(array $operations, string $stepKey, string $itemKey): ?OrganizationSetupOperation
  {
    foreach ($operations as $operation) {
      if ($operation->stepKey === $stepKey && $operation->itemKey === $itemKey) {
        return $operation;
      }
    }

    return null;
  }

  /**
   * @since 1.0.0
   *
   * @param array<array-key,mixed> $payload wire input
   *
   * @return array<string,mixed> canonical input
   */
  private function normalize(string $stepKey, array $payload): array
  {
    $defaults = self::FIELDS[$stepKey] ?? null;
    if (null === $defaults || [] !== array_diff(array_keys($payload), array_keys($defaults)) || strlen((string) json_encode($payload)) > 12000) {
      throw OrganizationSetupConflict::because('Unsupported setup input.');
    }
    $normalized = [...$defaults, ...$payload];
    if ('invite_members' === $stepKey) {
      $normalized['roleIds'] ??= [];
      if (!is_string($normalized['email']) || !is_array($normalized['roleIds'])) {
        throw OrganizationSetupConflict::because('Invalid invitation input.');
      }
      $normalized['email'] = strtolower(trim($normalized['email']));
      sort($normalized['roleIds']);
    }

    foreach (['latitude', 'longitude'] as $coordinate) {
      if (isset($normalized[$coordinate]) && is_numeric($normalized[$coordinate])) {
        $normalized[$coordinate] = (float) $normalized[$coordinate];
      }
    }
    /** @var array<string,mixed> $canonical */
    $canonical = $this->canonicalize($normalized);

    return $canonical;
  }

  /**
   * @since 1.0.0 Canonicalize JSON object order without changing list order.
   */
  private function canonicalize(mixed $value): mixed
  {
    if (!is_array($value)) {
      return $value;
    }
    if (!array_is_list($value)) {
      ksort($value);
    }
    foreach ($value as $key => $item) {
      $value[$key] = $this->canonicalize($item);
    }

    return $value;
  }
}
