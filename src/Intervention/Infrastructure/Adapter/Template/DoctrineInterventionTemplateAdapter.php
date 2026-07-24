<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Template;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Template\{InterventionTemplateItemView, InterventionTemplatePage, InterventionTemplateView};
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionTemplateItemRecord, InterventionTemplateRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;
use Throwable;

use function array_map;
use function max;
use function min;
use function str_contains;
use function str_replace;
use function strtolower;
use function usort;

/**
 * Adapter DoctrineInterventionTemplateAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionTemplateAdapter implements InterventionTemplatePort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DoctrineInterventionTemplateAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
  ) {
  }

  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $name the name value
   * @param ?string $description the description value
   * @param string $type the intervention type value
   * @param string $priority the intervention priority value
   * @param ?string $defaultSiteId the default site id value
   * @param ?string $defaultResponsibleId the default responsible member id value
   * @param ?string $duration the ISO-8601 duration string value
   * @param list<string> $labelIds the organization label ids value
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the template items, in position order
   *
   * @return InterventionTemplateView the created template view
   */
  public function create(
    string $organizationId,
    string $name,
    ?string $description,
    string $type,
    string $priority,
    ?string $defaultSiteId,
    ?string $defaultResponsibleId,
    ?string $duration,
    array $labelIds,
    array $items,
  ): InterventionTemplateView {
    $organization = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw InterventionNotFoundException::withId($organizationId);
    }

    $now = new DateTimeImmutable();
    $record = new InterventionTemplateRecord();
    $record->id = $this->uuidFactory->generateRaw();
    $record->organization = $organization;
    $record->name = $name;
    $record->description = $description;
    $record->type = $type;
    $record->priority = $priority;
    $record->defaultSiteId = $defaultSiteId;
    $record->defaultResponsibleId = $defaultResponsibleId;
    $record->duration = $duration;
    $record->labelIds = $labelIds;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);
    $this->replaceItems($record, $items);
    $this->flush($record);

    return $this->view($record);
  }

  /**
   * Method update.
   *
   * @since 1.0.0
   *
   * @param string $id the template id value
   * @param ?string $name the name value, only applied when `$hasName` is true
   * @param ?string $description the description value, only applied when `$hasDescription` is true
   * @param ?string $type the intervention type value, only applied when `$hasType` is true
   * @param ?string $priority the intervention priority value, only applied when `$hasPriority` is true
   * @param ?string $defaultSiteId the default site id value, only applied when `$hasDefaultSiteId` is true
   * @param ?string $defaultResponsibleId the default responsible member id value, only applied when `$hasDefaultResponsibleId` is true
   * @param ?string $duration the ISO-8601 duration string value, only applied when `$hasDuration` is true
   * @param ?list<string> $labelIds the organization label ids value, only applied when `$hasLabelIds` is true
   * @param ?list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the template items, only applied when `$hasItems` is true
   * @param bool $hasName whether the name field was present in the merge-patch request
   * @param bool $hasDescription whether the description field was present in the merge-patch request
   * @param bool $hasType whether the type field was present in the merge-patch request
   * @param bool $hasPriority whether the priority field was present in the merge-patch request
   * @param bool $hasDefaultSiteId whether the defaultSiteId field was present in the merge-patch request
   * @param bool $hasDefaultResponsibleId whether the defaultResponsibleId field was present in the merge-patch request
   * @param bool $hasDuration whether the duration field was present in the merge-patch request
   * @param bool $hasLabelIds whether the labelIds field was present in the merge-patch request
   * @param bool $hasItems whether the items field was present in the merge-patch request
   *
   * @return InterventionTemplateView the updated template view
   */
  public function update(
    string $id,
    ?string $name,
    ?string $description,
    ?string $type,
    ?string $priority,
    ?string $defaultSiteId,
    ?string $defaultResponsibleId,
    ?string $duration,
    ?array $labelIds,
    ?array $items,
    bool $hasName,
    bool $hasDescription,
    bool $hasType,
    bool $hasPriority,
    bool $hasDefaultSiteId,
    bool $hasDefaultResponsibleId,
    bool $hasDuration,
    bool $hasLabelIds,
    bool $hasItems,
  ): InterventionTemplateView {
    $record = $this->entityManager->find(InterventionTemplateRecord::class, $id);
    if (!$record instanceof InterventionTemplateRecord) {
      throw InterventionNotFoundException::withId($id);
    }

    if ($hasName && null !== $name) {
      $record->name = $name;
    }
    if ($hasDescription) {
      $record->description = $description;
    }
    if ($hasType && null !== $type) {
      $record->type = $type;
    }
    if ($hasPriority && null !== $priority) {
      $record->priority = $priority;
    }
    if ($hasDefaultSiteId) {
      $record->defaultSiteId = $defaultSiteId;
    }
    if ($hasDefaultResponsibleId) {
      $record->defaultResponsibleId = $defaultResponsibleId;
    }
    if ($hasDuration) {
      $record->duration = $duration;
    }
    if ($hasLabelIds) {
      $record->labelIds = $labelIds ?? [];
    }
    $record->updatedAt = new DateTimeImmutable();

    if ($hasItems) {
      $this->replaceItems($record, $items ?? []);
    }

    $this->flush($record);

    return $this->view($record);
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   *
   * @param string $id the template id value
   */
  public function delete(string $id): void
  {
    $record = $this->entityManager->find(InterventionTemplateRecord::class, $id);
    if (!$record instanceof InterventionTemplateRecord) {
      throw InterventionNotFoundException::withId($id);
    }
    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  /**
   * Method find.
   *
   * @since 1.0.0
   *
   * @param string $id the template id value
   *
   * @return ?InterventionTemplateView the template view, or null when not found
   */
  public function find(string $id): ?InterventionTemplateView
  {
    $record = $this->entityManager->find(InterventionTemplateRecord::class, $id);

    return $record instanceof InterventionTemplateRecord ? $this->view($record) : null;
  }

  /**
   * Method list.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param ?string $search an optional case-insensitive partial match on the name
   *
   * @return InterventionTemplatePage the template page result
   */
  public function list(string $organizationId, int $page, int $itemsPerPage, ?string $search = null): InterventionTemplatePage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('t')
      ->from(InterventionTemplateRecord::class, 't')
      ->where('t.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('t.name', 'ASC');

    if (null !== $search && '' !== $search) {
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
      $qb->andWhere('LOWER(t.name) LIKE LOWER(:search)')
        ->setParameter('search', '%' . $escaped . '%');
    }

    $total = (int) (clone $qb)
      ->select('COUNT(t.id)')
      ->resetDQLPart('orderBy')
      ->getQuery()
      ->getSingleScalarResult();
    /** @var list<InterventionTemplateRecord> $records */
    $records = $qb
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new InterventionTemplatePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  /**
   * Method replaceItems.
   *
   * Replaces a template's items wholesale: existing rows are removed and new
   * ones are persisted in the given (position) order.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateRecord $record the template record value
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the replacement items, in position order
   */
  private function replaceItems(InterventionTemplateRecord $record, array $items): void
  {
    foreach ($record->items as $existing) {
      $record->items->removeElement($existing);
      $this->entityManager->remove($existing);
    }

    $position = 0;
    foreach ($items as $item) {
      $itemRecord = new InterventionTemplateItemRecord();
      $itemRecord->id = $this->uuidFactory->generateRaw();
      $itemRecord->template = $record;
      $itemRecord->position = $position;
      $itemRecord->action = $item['action'];
      $itemRecord->target = $item['target'];
      $itemRecord->resultResource = $item['resultResource'];
      $itemRecord->required = $item['required'];
      $itemRecord->defaultAssigneeId = $item['defaultAssigneeId'];
      $record->items->add($itemRecord);
      $this->entityManager->persist($itemRecord);
      ++$position;
    }
  }

  /**
   * Method flush.
   *
   * Flushes the unit of work, translating the
   * `uniq_intervention_template_org_name` unique constraint violation into a
   * domain conflict.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateRecord $record the record being persisted
   */
  private function flush(InterventionTemplateRecord $record): void
  {
    try {
      $this->entityManager->flush();
    } catch (Throwable $exception) {
      if ($this->isDuplicateNameConstraintViolation($exception)) {
        throw new InterventionConflictException('A template named "' . $record->name . '" already exists in this organization.');
      }

      throw $exception;
    }
  }

  /**
   * Method isDuplicateNameConstraintViolation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the transactional exception
   *
   * @return bool true when the failure is caused by (organization, name) uniqueness
   */
  private function isDuplicateNameConstraintViolation(Throwable $exception): bool
  {
    $current = $exception;
    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());
        if (
          str_contains($message, 'uniq_intervention_template_org_name')
          || (str_contains($message, 'intervention_templates') && str_contains($message, 'name'))
        ) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }

  /**
   * Method view.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateRecord $record the record value
   *
   * @return InterventionTemplateView the template view result
   */
  private function view(InterventionTemplateRecord $record): InterventionTemplateView
  {
    $items = $record->items->toArray();
    usort($items, static fn (InterventionTemplateItemRecord $a, InterventionTemplateItemRecord $b): int => $a->position <=> $b->position);

    return new InterventionTemplateView(
      $record->id,
      $this->organizationId($record),
      $record->name,
      $record->description,
      $record->type,
      $record->priority,
      $record->defaultSiteId,
      $record->defaultResponsibleId,
      $record->duration,
      $record->labelIds,
      array_map(
        static fn (InterventionTemplateItemRecord $item): InterventionTemplateItemView => new InterventionTemplateItemView(
          $item->id,
          $item->position,
          $item->action,
          $item->target,
          $item->resultResource,
          $item->required,
          $item->defaultAssigneeId,
        ),
        $items,
      ),
      $record->createdAt,
      $record->updatedAt,
    );
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateRecord $record the record value
   *
   * @return string the organization id result
   */
  private function organizationId(InterventionTemplateRecord $record): string
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new InterventionConflictException('Intervention template organization is missing.');
    }

    return $record->organization->id;
  }
}
