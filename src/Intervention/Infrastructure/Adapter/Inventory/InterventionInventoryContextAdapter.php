<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Inventory;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Inventory\InventoryInterventionContext;
use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

use function is_array;
use function is_string;
use function json_decode;
use function preg_match;

/** Class InterventionInventoryContextAdapter. Shares stock/publication locking without exposing persistence records. @category Adapter */
final readonly class InterventionInventoryContextAdapter implements InterventionInventoryContextPort
{
  public function __construct(private EntityManagerInterface $entityManager, private OrganizationAuthorizationPort $authorization, private InterventionResourceGatewayPort $resources, private InterventionMemberPolicy $members)
  {
  }

  public function validate(string $organizationId, string $interventionId, ?string $workItemId, ?string $equipmentId, string $actorId): InventoryInterventionContext
  {
    $decision = $this->authorization->resolveAccess($actorId, $organizationId, 'organization.interventions.execute');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($interventionId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Intervention execution permission is required.');
    }
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw new InterventionValidationException('Physical declarations require the main transaction.');
    }
    $this->entityManager->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'inventory-intervention:' . $organizationId . ':' . $interventionId]);
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId, LockMode::PESSIMISTIC_WRITE);
    if (!$intervention instanceof InterventionRecord || $intervention->organization?->id !== $organizationId) {
      throw InterventionNotFoundException::withId($interventionId);
    }
    $this->entityManager->refresh($intervention, LockMode::PESSIMISTIC_WRITE);
    if (null !== $equipmentId && !$this->resources->resourceBelongsToOrganization(InterventionResourceType::EQUIPMENT, $equipmentId, $organizationId)) {
      throw InterventionNotFoundException::withId($equipmentId);
    }
    if (null !== $workItemId) {
      $item = $this->entityManager->find(InterventionWorkItemRecord::class, $workItemId);
      if (!$item instanceof InterventionWorkItemRecord || $item->intervention?->id !== $interventionId) {
        throw InterventionNotFoundException::withId($workItemId);
      }
      $target = null;
      $targetValue = $item->target ?? '';
      if (1 === preg_match('#^/api/equipment/([^/]+)$#', $targetValue, $match)) {
        $target = $match[1];
      } else {
        $decoded = json_decode($targetValue, true);
        if (is_array($decoded) && is_string($decoded['equipmentId'] ?? null)) {
          $target = $decoded['equipmentId'];
        }
      }
      if (null !== $equipmentId && $target !== $equipmentId) {
        throw new InterventionValidationException('The physical declaration must match the task equipment.');
      }
      $this->members->assertCanExecuteWorkItem($organizationId, $actorId, $intervention->responsibleId, $intervention->participants, $item->assigneeId);
    } else {
      $this->members->assertCanExecuteIntervention($organizationId, $actorId, $intervention->responsibleId, $intervention->participants);
    }

    return new InventoryInterventionContext('published' === $intervention->status);
  }

  public function existsInOrganization(string $organizationId, string $interventionId): bool
  {
    return null !== $this->entityManager->getRepository(InterventionRecord::class)->findOneBy(['id' => $interventionId, 'organization' => $organizationId]);
  }
}
