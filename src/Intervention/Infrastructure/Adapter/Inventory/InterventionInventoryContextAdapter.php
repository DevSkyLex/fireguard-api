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
  // #region Constructor
  public function __construct(private EntityManagerInterface $entityManager, private OrganizationAuthorizationPort $authorization, private InterventionResourceGatewayPort $resources, private InterventionMemberPolicy $members)
  {
  }
  // #endregion

  // #region Methods
  public function validate(string $organizationId, string $interventionId, ?string $workItemId, ?string $equipmentId, string $actorId): InventoryInterventionContext
  {
    $this->assertExecutionAccess($organizationId, $interventionId, $actorId);
    $intervention = $this->lockIntervention($organizationId, $interventionId);
    if (null !== $equipmentId && !$this->resources->resourceBelongsToOrganization(InterventionResourceType::EQUIPMENT, $equipmentId, $organizationId)) {
      throw InterventionNotFoundException::withId($equipmentId);
    }
    if (null !== $workItemId) {
      $this->assertWorkItemContext($intervention, $organizationId, $workItemId, $equipmentId, $actorId);
    } else {
      $this->members->assertCanExecuteIntervention($organizationId, $actorId, $intervention->responsibleId, $intervention->participants);
    }

    return new InventoryInterventionContext('published' === $intervention->status);
  }

  public function existsInOrganization(string $organizationId, string $interventionId): bool
  {
    return null !== $this->entityManager->getRepository(InterventionRecord::class)->findOneBy(['id' => $interventionId, 'organization' => $organizationId]);
  }

  /**
   * Method assertExecutionAccess
   *
   * Rejects inaccessible work before acquiring either physical-declaration lock.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param string $interventionId hidden work identifier
   * @param string $actorId authenticated actor
   *
   * @return void
   */
  private function assertExecutionAccess(string $organizationId, string $interventionId, string $actorId): void
  {
    $decision = $this->authorization->resolveAccess($actorId, $organizationId, 'organization.interventions.execute');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($interventionId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Intervention execution permission is required.');
    }
  }

  /**
   * Method lockIntervention
   *
   * Takes the physical-declaration fence before the parent row lock, matching publication and deletion.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param string $interventionId requested parent
   *
   * @return InterventionRecord fresh locked parent
   */
  private function lockIntervention(string $organizationId, string $interventionId): InterventionRecord
  {
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw new InterventionValidationException('Physical declarations require the main transaction.');
    }
    $this->entityManager->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'inventory-intervention:' . $organizationId . ':' . $interventionId]);
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId, LockMode::PESSIMISTIC_WRITE);
    if (!$intervention instanceof InterventionRecord || $intervention->organization?->id !== $organizationId) {
      throw InterventionNotFoundException::withId($interventionId);
    }
    $this->entityManager->refresh($intervention, LockMode::PESSIMISTIC_WRITE);

    return $intervention;
  }

  /**
   * Method assertWorkItemContext
   *
   * Keeps the task, equipment and executor within the locked intervention context.
   *
   * @access private
   *
   * @param InterventionRecord $intervention locked parent
   * @param string $organizationId owning organization
   * @param string $workItemId requested task
   * @param ?string $equipmentId declared equipment, when present
   * @param string $actorId authenticated executor
   *
   * @return void
   */
  private function assertWorkItemContext(InterventionRecord $intervention, string $organizationId, string $workItemId, ?string $equipmentId, string $actorId): void
  {
    $item = $this->entityManager->find(InterventionWorkItemRecord::class, $workItemId);
    if (!$item instanceof InterventionWorkItemRecord || $item->intervention?->id !== $intervention->id) {
      throw InterventionNotFoundException::withId($workItemId);
    }
    if (null !== $equipmentId && $this->equipmentTarget($item->target) !== $equipmentId) {
      throw new InterventionValidationException('The physical declaration must match the task equipment.');
    }
    $this->members->assertCanExecuteWorkItem($organizationId, $actorId, $intervention->responsibleId, $intervention->participants, $item->assigneeId);
  }

  /**
   * Method equipmentTarget
   *
   * Recognizes canonical IRIs and retained offline task targets without resolving live identity.
   *
   * @access private
   *
   * @param ?string $target retained task target
   *
   * @return ?string declared asset identifier
   */
  private function equipmentTarget(?string $target): ?string
  {
    $value = $target ?? '';
    if (1 === preg_match('#^/api/equipment/([^/]+)$#', $value, $match)) {
      return $match[1];
    }
    $decoded = json_decode($value, true);

    return is_array($decoded) && is_string($decoded['equipmentId'] ?? null) ? $decoded['equipmentId'] : null;
  }
  // #endregion
}
