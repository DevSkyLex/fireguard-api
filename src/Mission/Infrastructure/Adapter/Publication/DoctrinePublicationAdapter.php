<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Mission\Application\Contract\Publication\{MissionPublicationContext, PublicationView};
use Mission\Application\Port\Outbound\PublicationRepositoryPort;
use Mission\Application\Service\{MissionChangeApplication, MissionDraftPublisher, MissionNotificationService};
use Mission\Domain\Exception\{MissionConflictException, MissionNotFoundException, PublicationNotFoundException};
use Mission\Infrastructure\Persistence\Doctrine\Record\{MissionChangeRecord, MissionRecord, PublicationRecord};
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
   * @param MissionChangeApplication $changeApplication the change application value
   * @param MissionDraftPublisher $draftPublisher the draft publisher value
   * @param MissionNotificationService $notifications the notifications value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private MissionChangeApplication $changeApplication,
    private MissionDraftPublisher $draftPublisher,
    private MissionNotificationService $notifications,
  ) {
  }

  /**
   * Method missionContext.
   *
   * Executes the mission context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionPublicationContext the mission context result
   */
  public function missionContext(string $missionId): ?MissionPublicationContext
  {
    $mission = $this->entityManager->find(MissionRecord::class, $missionId);
    if (!$mission instanceof MissionRecord || !$mission->organization instanceof OrganizationRecord) {
      return null;
    }

    return new MissionPublicationContext($mission->id, $mission->organization->id, $mission->status, $mission->revision);
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
   * Method findByMissionRevision.
   *
   * Executes the find by mission revision operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param int $missionRevision the mission revision value
   *
   * @return ?PublicationView the find by mission revision result
   */
  public function findByMissionRevision(string $missionId, int $missionRevision): ?PublicationView
  {
    $mission = $this->entityManager->getReference(MissionRecord::class, $missionId);
    $publication = $this->entityManager->getRepository(PublicationRecord::class)->findOneBy([
      'mission' => $mission,
      'missionRevision' => $missionRevision,
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
   * @param string $missionId the mission id value
   * @param int $missionRevision the mission revision value
   *
   * @return PublicationView the create or get pending result
   */
  public function createOrGetPending(string $publicationId, string $missionId, int $missionRevision): PublicationView
  {
    return $this->entityManager->wrapInTransaction(function () use ($publicationId, $missionId, $missionRevision): PublicationView {
      $mission = $this->entityManager->find(MissionRecord::class, $missionId, LockMode::PESSIMISTIC_WRITE);
      if (!$mission instanceof MissionRecord) {
        throw MissionNotFoundException::withId($missionId);
      }
      $existing = $this->entityManager->getRepository(PublicationRecord::class)->findOneBy([
        'mission' => $mission,
        'missionRevision' => $missionRevision,
      ]);
      if ($existing instanceof PublicationRecord) {
        return $this->view($existing);
      }

      $publication = new PublicationRecord();
      $publication->id = $publicationId;
      $publication->mission = $mission;
      $publication->missionRevision = $missionRevision;
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
      if (!$publication instanceof PublicationRecord || !$publication->mission instanceof MissionRecord) {
        throw PublicationNotFoundException::withId($publicationId);
      }
      if ('completed' === $publication->status) {
        return [$publication->mission->id, $publication->mission->name, []];
      }
      $mission = $this->entityManager->find(MissionRecord::class, $publication->mission->id, LockMode::PESSIMISTIC_WRITE);
      if (!$mission instanceof MissionRecord) {
        throw MissionNotFoundException::withId($publication->mission->id);
      }
      if ('submitted' !== $mission->status || $mission->revision !== $publication->missionRevision) {
        throw new MissionConflictException('Mission changed before publication execution.');
      }
      if (!$mission->organization instanceof OrganizationRecord) {
        throw new MissionConflictException('Mission organization is unavailable.');
      }

      $changes = $this->entityManager->getRepository(MissionChangeRecord::class)->findBy([
        'mission' => $mission,
        'status' => 'proposed',
      ]);
      foreach ($changes as $change) {
        $this->changeApplication->apply($mission->organization->id, $change->resource, $change->patch);
        $change->status = 'applied';
        ++$change->revision;
        $change->updatedAt = new DateTimeImmutable();
      }

      $this->draftPublisher->publish($mission->id);
      $mission->status = 'published';
      ++$mission->revision;
      $mission->updatedAt = new DateTimeImmutable();
      $publication->status = 'completed';
      $publication->completedAt = new DateTimeImmutable();
      $this->entityManager->flush();

      return [
        $mission->id,
        $mission->name,
        array_values(array_filter([$mission->responsibleId, ...$mission->participants], is_string(...))),
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
        'UPDATE mission_publications SET status = :status, error = :error, completed_at = :completedAt WHERE id = :id AND status <> :completed',
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
    if (!$publication->mission instanceof MissionRecord) {
      throw PublicationNotFoundException::withId($publication->id);
    }

    return new PublicationView(
      $publication->id,
      $publication->mission->id,
      $publication->missionRevision,
      $publication->status,
      $publication->error,
      $publication->createdAt,
      $publication->completedAt,
    );
  }
}
