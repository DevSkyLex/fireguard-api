<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Outbound\{InterventionEquipmentReplacementPort, InterventionInspectionResultPort};
use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Domain\ValueObject\WorkItemExecutionResult;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Maintenance\Application\Contract\Plan\MaintenanceOperationResult;
use Maintenance\Application\Port\Inbound\MaintenanceOperationResultsPort;

use function in_array;
use function is_string;
use function preg_match;

/**
 * Class InterventionOperationPublicationAdapter
 *
 * Validates real operation facts and acknowledges only their explicit preventive source on main.
 *
 * @category Adapter
 */
final readonly class InterventionOperationPublicationAdapter
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager owning main manager
   * @param InterventionInspectionResultPort $inspections scoped facts from the inspection owner
   * @param MaintenanceOperationResultsPort $maintenance same-transaction occurrence acknowledgments
   * @param InterventionEquipmentReplacementPort $replacements owner-confirmed predecessor and successor links
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager, private InterventionInspectionResultPort $inspections, private MaintenanceOperationResultsPort $maintenance, private InterventionEquipmentReplacementPort $replacements)
  {
  }

  /**
   * Method publish
   *
   * Every fact and cadence acknowledgement rolls back with resource publication if any item fails.
   *
   * @access public
   *
   * @param InterventionRecord $intervention locked intervention after its drafts have been published
   * @param string $organizationId owning organization
   *
   * @return void
   */
  public function publish(InterventionRecord $intervention, string $organizationId): void
  {
    $items = $this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['intervention' => $intervention], ['id' => 'ASC']);
    foreach ($items as $item) {
      if ('completed' !== $item->status) {
        continue;
      }
      if ('inspection' === $item->action && null !== $item->occurrenceId) {
        $this->publishInspection($intervention, $item, $organizationId);
      } elseif (in_array($item->action, ['maintenance', 'repair', 'replacement'], true)) {
        $this->publishEquipmentOperation($intervention, $item, $organizationId);
      } else {
        continue;
      }
      ++$item->revision;
      $item->updatedAt = new DateTimeImmutable();
    }
  }

  /**
   * Method publishInspection
   *
   * A closed adverse control is a performed control; it does not close its anomalies.
   *
   * @access private
   *
   * @param InterventionRecord $intervention owning intervention
   * @param InterventionWorkItemRecord $item source-linked control task
   * @param string $organizationId owning organization
   *
   * @return void
   */
  private function publishInspection(InterventionRecord $intervention, InterventionWorkItemRecord $item, string $organizationId): void
  {
    if (null === $item->resultResource || 1 !== preg_match('#^/api/inspections/([^/]+)$#', $item->resultResource, $match)) {
      throw new InterventionValidationException('A preventive control must identify its inspection result.');
    }
    $result = $this->inspections->find($organizationId, $intervention->id, $match[1]);
    $equipmentId = $this->equipmentId($item);
    if (null === $result || $equipmentId !== $result->equipmentId || 'closed' !== $result->status || 'control' !== $item->operationKind) {
      throw new InterventionValidationException('The control result must be closed and match the occurrence equipment.');
    }
    $fact = new MaintenanceOperationResult($organizationId, $equipmentId, $item->occurrenceId ?? '', $result->id, $intervention->id, 'control', 'pass' === $result->result ? 'passed' : 'failed', $result->performedAt, operationId: $item->operationId);
    $this->maintenance->validateResult($fact);
    $this->maintenance->acknowledgeResult($fact);
    $item->executionResult = ['equipmentId' => $equipmentId, 'performedAt' => $result->performedAt->format('c'), 'outcome' => 'performed', 'inspectionResult' => $result->result, 'workPerformed' => $result->notes ?? '', 'authorId' => $result->authorId, 'operationId' => $item->operationId, 'occurrenceId' => $item->occurrenceId, 'state' => 'validated', 'validatedAt' => new DateTimeImmutable()->format('c')];
  }

  /**
   * Method publishEquipmentOperation
   *
   * Rejects missing facts and prevents unsuccessful repairs from satisfying an occurrence.
   *
   * @access private
   *
   * @param InterventionRecord $intervention owning intervention
   * @param InterventionWorkItemRecord $item completed equipment operation
   * @param string $organizationId owning organization
   *
   * @return void
   */
  private function publishEquipmentOperation(InterventionRecord $intervention, InterventionWorkItemRecord $item, string $organizationId): void
  {
    if (null === $item->executionResult) {
      throw new InterventionValidationException('Equipment operations require a recorded execution result.');
    }
    $result = WorkItemExecutionResult::fromPayload($item->executionResult);
    $result->assertAlreadyPerformed(new DateTimeImmutable());
    $result->assertCompletesAction($item->action, $this->equipmentId($item));
    if (!is_string($item->executionResult['authorId'] ?? null)) {
      throw new InterventionValidationException('The completed operation has no successful scoped execution fact.');
    }
    if (null !== $item->occurrenceId) {
      if ('maintenance' !== $item->action || 'maintenance' !== $item->operationKind) {
        throw new InterventionValidationException('A repair cannot satisfy a preventive maintenance occurrence.');
      }
      $fact = new MaintenanceOperationResult($organizationId, $result->equipmentId, $item->occurrenceId, $item->id, $intervention->id, 'maintenance', 'passed', $result->performedAt, operationId: $item->operationId);
      $this->maintenance->validateResult($fact);
      $this->maintenance->acknowledgeResult($fact);
    }
    $item->executionResult['state'] = 'validated';
    if ('replacement' === $item->action && (null === $item->resultResource || 1 !== preg_match('#^/api/equipment/([^/]+)$#', $item->resultResource, $successor)
      || !$this->replacements->isReplacement($organizationId, $result->equipmentId, $successor[1]))) {
      throw new InterventionValidationException('The equipment owner must confirm the recorded replacement.');
    }
    $item->executionResult['validatedAt'] = new DateTimeImmutable()->format('c');
  }

  /**
   * Method equipmentId
   *
   * @access private
   *
   * @param InterventionWorkItemRecord $item equipment work item
   *
   * @return string stable target equipment identifier
   */
  private function equipmentId(InterventionWorkItemRecord $item): string
  {
    if (null === $item->target || 1 !== preg_match('#^/api/equipment/([^/]+)$#', $item->target, $match)) {
      throw new InterventionValidationException('The operation target must identify its equipment.');
    }

    return $match[1];
  }
}
