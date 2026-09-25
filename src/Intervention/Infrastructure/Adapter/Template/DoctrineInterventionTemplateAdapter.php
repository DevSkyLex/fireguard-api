<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Template;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Template\{InterventionTemplateCreateRequest, InterventionTemplateItemView, InterventionTemplatePage, InterventionTemplateUpdateRequest, InterventionTemplateView};
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
   * @param InterventionTemplateCreateRequest $request the validated request
   *
   * @return InterventionTemplateView the created template view
   */
  public function create(InterventionTemplateCreateRequest $request): InterventionTemplateView
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, $request->organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw InterventionNotFoundException::withId($request->organizationId);
    }

    $now = new DateTimeImmutable();
    $record = new InterventionTemplateRecord();
    $record->id = $this->uuidFactory->generateRaw();
    $record->organization = $organization;
    $record->name = $request->attributes->name;
    $record->description = $request->attributes->description;
    $record->type = $request->attributes->type;
    $record->priority = $request->attributes->priority;
    $record->defaultSiteId = $request->defaults->siteId;
    $record->defaultResponsibleId = $request->defaults->responsibleId;
    $record->duration = $request->attributes->duration;
    $record->labelIds = $request->labelIds;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);
    $this->replaceItems($record, $request->items);
    $this->flush($record);

    return $this->view($record);
  }

  /**
   * Method update.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateUpdateRequest $request the validated request
   *
   * @return InterventionTemplateView the updated template view
   */
  public function update(InterventionTemplateUpdateRequest $request): InterventionTemplateView
  {
    $record = $this->entityManager->find(InterventionTemplateRecord::class, $request->id);
    if (!$record instanceof InterventionTemplateRecord) {
      throw InterventionNotFoundException::withId($request->id);
    }

    if ($request->identity->hasName && null !== $request->identity->name) {
      $record->name = $request->identity->name;
    }
    if ($request->identity->hasDescription) {
      $record->description = $request->identity->description;
    }
    if ($request->identity->hasType && null !== $request->identity->type) {
      $record->type = $request->identity->type;
    }
    if ($request->planning->hasPriority && null !== $request->planning->priority) {
      $record->priority = $request->planning->priority;
    }
    if ($request->defaults->hasSiteId) {
      $record->defaultSiteId = $request->defaults->siteId;
    }
    if ($request->defaults->hasResponsibleId) {
      $record->defaultResponsibleId = $request->defaults->responsibleId;
    }
    if ($request->planning->hasDuration) {
      $record->duration = $request->planning->duration;
    }
    if ($request->collections->hasLabelIds) {
      $record->labelIds = $request->collections->labelIds ?? [];
    }
    $record->updatedAt = new DateTimeImmutable();

    if ($request->collections->hasItems) {
      $this->replaceItems($record, $request->collections->items ?? []);
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
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string, estimatedMinutes?: ?int}> $items the replacement items, in position order
   *
   * @return void completes without returning a value
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
      $itemRecord->estimatedMinutes = $item['estimatedMinutes'] ?? null;
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
          $item->estimatedMinutes,
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
