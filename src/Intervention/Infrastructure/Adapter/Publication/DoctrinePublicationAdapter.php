<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};
use Intervention\Application\Port\Outbound\PublicationRepositoryPort;
use Intervention\Application\Service\{InterventionChangeApplication, InterventionDraftPublisher, InterventionNotificationService};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException, PublicationNotFoundException};
use Intervention\Domain\Service\{InterventionChangePolicy, PublicationTransitionPolicy};
use Intervention\Domain\ValueObject\{InterventionChangeStatus, PublicationStatus};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, PublicationRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_filter;
use function array_values;
use function in_array;

/**
 * Adapter DoctrinePublicationAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrinePublicationAdapter implements PublicationRepositoryPort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DoctrinePublicationAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param InterventionChangeApplication $changeApplication the change application value
   * @param InterventionDraftPublisher $draftPublisher the draft publisher value
   * @param InterventionNotificationService $notifications the notifications value
   * @param PublicationTransitionPolicy $transitionPolicy the publication status transition policy value
   * @param InterventionChangePolicy $changePolicy the intervention change status policy value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private InterventionChangeApplication $changeApplication,
    private InterventionDraftPublisher $draftPublisher,
    private InterventionNotificationService $notifications,
    private PublicationTransitionPolicy $transitionPolicy,
    private InterventionChangePolicy $changePolicy,
  ) {
  }

  /**
   * Method interventionContext.
   *
   * Executes the intervention context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionPublicationContext the intervention context result
   */
  public function interventionContext(string $interventionId): ?InterventionPublicationContext
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$intervention instanceof InterventionRecord || !$intervention->organization instanceof OrganizationRecord) {
      return null;
    }

    return new InterventionPublicationContext($intervention->id, $intervention->organization->id, $intervention->status, $intervention->revision);
  }

  /**
   * Method find.
   *
   * Executes the find operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   *
   * @return ?PublicationView the find result
   */
  public function find(string $publicationId): ?PublicationView
  {
    $publication = $this->entityManager->find(PublicationRecord::class, $publicationId);

    return $publication instanceof PublicationRecord ? $this->view($publication) : null;
  }

  /**
   * Method findByInterventionRevision.
   *
   * Executes the find by intervention revision operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param int $interventionRevision the intervention revision value
   *
   * @return ?PublicationView the find by intervention revision result
   */
  public function findByInterventionRevision(string $interventionId, int $interventionRevision): ?PublicationView
  {
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);
    $publication = $this->entityManager->getRepository(PublicationRecord::class)->findOneBy([
      'intervention' => $intervention,
      'interventionRevision' => $interventionRevision,
    ]);

    return $publication instanceof PublicationRecord ? $this->view($publication) : null;
  }

  /**
   * Method createOrGetPending.
   *
   * Executes the create or get pending operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   * @param string $interventionId the intervention id value
   * @param int $interventionRevision the intervention revision value
   *
   * @return PublicationView the create or get pending result
   */
  public function createOrGetPending(string $publicationId, string $interventionId, int $interventionRevision): PublicationView
  {
    return $this->entityManager->wrapInTransaction(function () use ($publicationId, $interventionId, $interventionRevision): PublicationView {
      $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId, LockMode::PESSIMISTIC_WRITE);
      if (!$intervention instanceof InterventionRecord) {
        throw InterventionNotFoundException::withId($interventionId);
      }
      $existing = $this->entityManager->getRepository(PublicationRecord::class)->findOneBy([
        'intervention' => $intervention,
        'interventionRevision' => $interventionRevision,
      ]);
      if ($existing instanceof PublicationRecord) {
        return $this->view($existing);
      }

      $publication = new PublicationRecord();
      $publication->id = $publicationId;
      $publication->intervention = $intervention;
      $publication->interventionRevision = $interventionRevision;
      $publication->createdAt = new DateTimeImmutable();
      $this->entityManager->persist($publication);
      $this->entityManager->flush();

      return $this->view($publication);
    });
  }

  /**
   * Method markProcessing.
   *
   * Executes the mark processing operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   */
  public function markProcessing(string $publicationId): void
  {
    $publication = $this->entityManager->find(PublicationRecord::class, $publicationId);
    if (!$publication instanceof PublicationRecord) {
      return;
    }
    $currentStatus = PublicationStatus::from($publication->status);
    if (PublicationStatus::COMPLETED === $currentStatus || PublicationStatus::FAILED === $currentStatus) {
      return;
    }
    $this->transitionPolicy->assertAllowed($currentStatus, PublicationStatus::PROCESSING);
    $publication->status = PublicationStatus::PROCESSING->value;
    $this->entityManager->flush();
  }

  /**
   * Method retryFailed.
   *
   * Executes the retry failed operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   *
   * @return PublicationView the retry failed result
   */
  public function retryFailed(string $publicationId): PublicationView
  {
    $publication = $this->entityManager->find(PublicationRecord::class, $publicationId);
    if (!$publication instanceof PublicationRecord) {
      throw PublicationNotFoundException::withId($publicationId);
    }
    $currentStatus = PublicationStatus::from($publication->status);
    if (PublicationStatus::FAILED === $currentStatus) {
      $this->transitionPolicy->assertAllowed($currentStatus, PublicationStatus::PENDING);
      $publication->status = PublicationStatus::PENDING->value;
      $publication->error = null;
      $publication->completedAt = null;
      $this->entityManager->flush();
    }

    return $this->view($publication);
  }

  /**
   * Method publish.
   *
   * Executes the publish operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   */
  public function publish(string $publicationId): bool
  {
    /** @var array{string, string, list<string>, bool} $notification */
    $notification = $this->entityManager->wrapInTransaction(function () use ($publicationId): array {
      $publication = $this->entityManager->find(PublicationRecord::class, $publicationId, LockMode::PESSIMISTIC_WRITE);
      if (!$publication instanceof PublicationRecord || !$publication->intervention instanceof InterventionRecord) {
        throw PublicationNotFoundException::withId($publicationId);
      }
      $currentPublicationStatus = PublicationStatus::from($publication->status);
      if (PublicationStatus::COMPLETED === $currentPublicationStatus) {
        // Idempotent at-least-once replay: a concurrent delivery already
        // completed this publication — no transition, no notification.
        return [$publication->intervention->id, $publication->intervention->name, [], false];
      }
      $intervention = $this->entityManager->find(InterventionRecord::class, $publication->intervention->id, LockMode::PESSIMISTIC_WRITE);
      // @codeCoverageIgnoreStart
      // Unreachable: line 220 already dereferences $publication->intervention,
      // which registers the association proxy in the identity map, so the find()
      // above always returns it. Verified empirically by dropping the FK and
      // NOT NULL constraints: Doctrine raises EntityNotFoundException from proxy
      // initialisation instead, never reaching this throw.
      if (!$intervention instanceof InterventionRecord) {
        throw InterventionNotFoundException::withId($publication->intervention->id);
      }
      // @codeCoverageIgnoreEnd
      if (InterventionStatus::SUBMITTED->value !== $intervention->status || $intervention->revision !== $publication->interventionRevision) {
        throw new InterventionConflictException('Intervention changed before publication execution.');
      }
      if (!$intervention->organization instanceof OrganizationRecord) {
        throw new InterventionConflictException('Intervention organization is unavailable.');
      }

      $changes = $this->entityManager->getRepository(InterventionChangeRecord::class)->findBy([
        'intervention' => $intervention,
        'status' => InterventionChangeStatus::PROPOSED->value,
      ]);
      foreach ($changes as $change) {
        $this->changeApplication->apply($intervention->organization->id, $change->resource, $change->patch);
        $this->changePolicy->assertTransitionAllowed(InterventionChangeStatus::from($change->status), InterventionChangeStatus::APPLIED);
        $change->status = InterventionChangeStatus::APPLIED->value;
        ++$change->revision;
        $change->updatedAt = new DateTimeImmutable();
      }

      $this->draftPublisher->publish($intervention->id);
      $intervention->status = InterventionStatus::PUBLISHED->value;
      ++$intervention->revision;
      $intervention->updatedAt = new DateTimeImmutable();
      $this->transitionPolicy->assertAllowed($currentPublicationStatus, PublicationStatus::COMPLETED);
      $publication->status = PublicationStatus::COMPLETED->value;
      $publication->completedAt = new DateTimeImmutable();
      $this->entityManager->flush();

      return [
        $intervention->id,
        $intervention->name,
        array_values(array_filter([$intervention->responsibleId, ...$intervention->participants], is_string(...))),
        true,
      ];
    });

    [$interventionId, $interventionName, $recipients, $transitioned] = $notification;
    if ($transitioned) {
      $this->notifications->published($interventionId, $interventionName, $recipients);
    }

    return $transitioned;
  }

  /**
   * Method markFailed.
   *
   * Executes the mark failed operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   * @param string $error the error value
   *
   * @return bool true when this call transitioned the publication to failed
   */
  public function markFailed(string $publicationId, string $error): bool
  {
    if (!$this->entityManager->isOpen()) {
      // The entity manager is closed (a prior flush failure typically),
      // so this falls back to a raw SQL UPDATE that keeps its own WHERE
      // guard rather than going through the transition policy.
      $affected = $this->entityManager->getConnection()->executeStatement(
        'UPDATE intervention_publications SET status = :status, error = :error, completed_at = :completedAt WHERE id = :id AND status <> :completed AND status <> :failed',
        [
          'status' => PublicationStatus::FAILED->value,
          'error' => $error,
          'completedAt' => new DateTimeImmutable(),
          'id' => $publicationId,
          'completed' => PublicationStatus::COMPLETED->value,
          'failed' => PublicationStatus::FAILED->value,
        ],
        ['completedAt' => 'datetime_immutable'],
      );

      return $affected > 0;
    }
    $publication = $this->entityManager->find(PublicationRecord::class, $publicationId);
    if (!$publication instanceof PublicationRecord) {
      return false;
    }
    $currentStatus = PublicationStatus::from($publication->status);
    if (in_array($currentStatus, [PublicationStatus::COMPLETED, PublicationStatus::FAILED], true)) {
      return false;
    }
    $this->transitionPolicy->assertAllowed($currentStatus, PublicationStatus::FAILED);
    $publication->status = PublicationStatus::FAILED->value;
    $publication->error = $error;
    $publication->completedAt = new DateTimeImmutable();
    $this->entityManager->flush();

    return true;
  }

  /**
   * Method view.
   *
   * Executes the view operation.
   *
   * @since 1.0.0
   *
   * @param PublicationRecord $publication the publication value
   *
   * @return PublicationView the view result
   */
  private function view(PublicationRecord $publication): PublicationView
  {
    if (!$publication->intervention instanceof InterventionRecord) {
      throw PublicationNotFoundException::withId($publication->id);
    }

    return new PublicationView(
      $publication->id,
      $publication->intervention->id,
      $publication->interventionRevision,
      $publication->status,
      $publication->error,
      $publication->createdAt,
      $publication->completedAt,
    );
  }
}
