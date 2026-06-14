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
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, PublicationRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_filter;
use function array_values;

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
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private InterventionChangeApplication $changeApplication,
    private InterventionDraftPublisher $draftPublisher,
    private InterventionNotificationService $notifications,
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
    if (!$publication instanceof PublicationRecord || 'completed' === $publication->status || 'failed' === $publication->status) {
      return;
    }
    $publication->status = 'processing';
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
    if ('failed' === $publication->status) {
      $publication->status = 'pending';
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
  public function publish(string $publicationId): void
  {
    /** @var array{string, string, list<string>} $notification */
    $notification = $this->entityManager->wrapInTransaction(function () use ($publicationId): array {
      $publication = $this->entityManager->find(PublicationRecord::class, $publicationId, LockMode::PESSIMISTIC_WRITE);
      if (!$publication instanceof PublicationRecord || !$publication->intervention instanceof InterventionRecord) {
        throw PublicationNotFoundException::withId($publicationId);
      }
      if ('completed' === $publication->status) {
        return [$publication->intervention->id, $publication->intervention->name, []];
      }
      $intervention = $this->entityManager->find(InterventionRecord::class, $publication->intervention->id, LockMode::PESSIMISTIC_WRITE);
      if (!$intervention instanceof InterventionRecord) {
        throw InterventionNotFoundException::withId($publication->intervention->id);
      }
      if ('submitted' !== $intervention->status || $intervention->revision !== $publication->interventionRevision) {
        throw new InterventionConflictException('Intervention changed before publication execution.');
      }
      if (!$intervention->organization instanceof OrganizationRecord) {
        throw new InterventionConflictException('Intervention organization is unavailable.');
      }

      $changes = $this->entityManager->getRepository(InterventionChangeRecord::class)->findBy([
        'intervention' => $intervention,
        'status' => 'proposed',
      ]);
      foreach ($changes as $change) {
        $this->changeApplication->apply($intervention->organization->id, $change->resource, $change->patch);
        $change->status = 'applied';
        ++$change->revision;
        $change->updatedAt = new DateTimeImmutable();
      }

      $this->draftPublisher->publish($intervention->id);
      $intervention->status = 'published';
      ++$intervention->revision;
      $intervention->updatedAt = new DateTimeImmutable();
      $publication->status = 'completed';
      $publication->completedAt = new DateTimeImmutable();
      $this->entityManager->flush();

      return [
        $intervention->id,
        $intervention->name,
        array_values(array_filter([$intervention->responsibleId, ...$intervention->participants], is_string(...))),
      ];
    });
    $this->notifications->published(...$notification);
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
   */
  public function markFailed(string $publicationId, string $error): void
  {
    if (!$this->entityManager->isOpen()) {
      $this->entityManager->getConnection()->executeStatement(
        'UPDATE intervention_publications SET status = :status, error = :error, completed_at = :completedAt WHERE id = :id AND status <> :completed',
        [
          'status' => 'failed',
          'error' => $error,
          'completedAt' => new DateTimeImmutable(),
          'id' => $publicationId,
          'completed' => 'completed',
        ],
        ['completedAt' => 'datetime_immutable'],
      );

      return;
    }
    $publication = $this->entityManager->find(PublicationRecord::class, $publicationId);
    if (!$publication instanceof PublicationRecord || 'completed' === $publication->status) {
      return;
    }
    $publication->status = 'failed';
    $publication->error = $error;
    $publication->completedAt = new DateTimeImmutable();
    $this->entityManager->flush();
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
