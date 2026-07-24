<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Label;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Label\{InterventionLabelPage, InterventionLabelView};
use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionLabelRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;
use Throwable;

use function array_map;
use function max;
use function min;
use function str_contains;
use function strtolower;

/**
 * Adapter DoctrineInterventionLabelAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionLabelAdapter implements InterventionLabelPort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DoctrineInterventionLabelAdapter class.
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
   * @param string $color the color value
   *
   * @return InterventionLabelView the created label view
   */
  public function create(string $organizationId, string $name, string $color): InterventionLabelView
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw InterventionNotFoundException::withId($organizationId);
    }

    $now = new DateTimeImmutable();
    $record = new InterventionLabelRecord();
    $record->id = $this->uuidFactory->generateRaw();
    $record->organization = $organization;
    $record->name = $name;
    $record->color = $color;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);
    $this->flush($record);

    return $this->view($record);
  }

  /**
   * Method update.
   *
   * @since 1.0.0
   *
   * @param string $id the label id value
   * @param ?string $name the name value, only applied when `$hasName` is true
   * @param ?string $color the color value, only applied when `$hasColor` is true
   * @param bool $hasName whether the name field was present in the request
   * @param bool $hasColor whether the color field was present in the request
   *
   * @return InterventionLabelView the updated label view
   */
  public function update(string $id, ?string $name, ?string $color, bool $hasName, bool $hasColor): InterventionLabelView
  {
    $record = $this->entityManager->find(InterventionLabelRecord::class, $id);
    if (!$record instanceof InterventionLabelRecord) {
      throw InterventionNotFoundException::withId($id);
    }
    if ($hasName && null !== $name) {
      $record->name = $name;
    }
    if ($hasColor && null !== $color) {
      $record->color = $color;
    }
    $record->updatedAt = new DateTimeImmutable();
    $this->flush($record);

    return $this->view($record);
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   *
   * @param string $id the label id value
   */
  public function delete(string $id): void
  {
    $record = $this->entityManager->find(InterventionLabelRecord::class, $id);
    if (!$record instanceof InterventionLabelRecord) {
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
   * @param string $id the label id value
   *
   * @return ?InterventionLabelView the label view, or null when not found
   */
  public function find(string $id): ?InterventionLabelView
  {
    $record = $this->entityManager->find(InterventionLabelRecord::class, $id);

    return $record instanceof InterventionLabelRecord ? $this->view($record) : null;
  }

  /**
   * Method list.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return InterventionLabelPage the label page result
   */
  public function list(string $organizationId, int $page, int $itemsPerPage): InterventionLabelPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('l')
      ->from(InterventionLabelRecord::class, 'l')
      ->where('l.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('l.name', 'ASC');
    $total = (int) (clone $qb)
      ->select('COUNT(l.id)')
      ->resetDQLPart('orderBy')
      ->getQuery()
      ->getSingleScalarResult();
    /** @var list<InterventionLabelRecord> $records */
    $records = $qb
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new InterventionLabelPage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  /**
   * Method flush.
   *
   * Flushes the unit of work, translating the `uniq_intervention_label_org_name`
   * unique constraint violation into a domain conflict.
   *
   * @since 1.0.0
   *
   * @param InterventionLabelRecord $record the record being persisted
   */
  private function flush(InterventionLabelRecord $record): void
  {
    try {
      $this->entityManager->flush();
    } catch (Throwable $exception) {
      if ($this->isDuplicateNameConstraintViolation($exception)) {
        throw new InterventionConflictException('A label named "' . $record->name . '" already exists in this organization.');
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
          str_contains($message, 'uniq_intervention_label_org_name')
          || (str_contains($message, 'intervention_labels') && str_contains($message, 'name'))
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
   * @param InterventionLabelRecord $record the record value
   *
   * @return InterventionLabelView the label view result
   */
  private function view(InterventionLabelRecord $record): InterventionLabelView
  {
    return new InterventionLabelView(
      $record->id,
      $this->organizationId($record),
      $record->name,
      $record->color,
      $record->createdAt,
      $record->updatedAt,
    );
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @param InterventionLabelRecord $record the record value
   *
   * @return string the organization id result
   */
  private function organizationId(InterventionLabelRecord $record): string
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new InterventionConflictException('Intervention label organization is missing.');
    }

    return $record->organization->id;
  }
}
