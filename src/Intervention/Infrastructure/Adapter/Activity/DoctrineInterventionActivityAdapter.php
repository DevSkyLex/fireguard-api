<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Activity;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowPage, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\InterventionActivityPort;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionViewMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionActivityRecord, InterventionRecord};
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function max;
use function min;

/**
 * Adapter DoctrineInterventionActivityAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionActivityAdapter implements InterventionActivityPort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DoctrineInterventionActivityAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param InterventionViewMapper $views the view mapper value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
    private InterventionViewMapper $views,
  ) {
  }

  /**
   * Method append.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $organizationId the owning organization id value
   * @param ?string $actorId the acting organization member id value
   * @param string $kind the activity kind value
   * @param string $event the activity event value
   * @param ?string $body the comment body value
   * @param ?array<string, mixed> $payload structured event data
   *
   * @return InterventionWorkflowView the appended activity view
   */
  public function append(
    string $interventionId,
    string $organizationId,
    ?string $actorId,
    string $kind,
    string $event,
    ?string $body,
    ?array $payload,
    ?string $clientId = null,
  ): InterventionWorkflowView {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($interventionId);
    }

    // An outbox replay can present the same write twice — a response lost in the
    // field is indistinguishable from a request that never arrived. Returning the
    // existing row makes the retry a no-op instead of a duplicate comment.
    if (null !== $clientId) {
      $existing = $this->entityManager
        ->getRepository(InterventionActivityRecord::class)
        ->findOneBy(['clientId' => $clientId]);

      if ($existing instanceof InterventionActivityRecord) {
        return $this->views->activityView($existing);
      }
    }

    $record = new InterventionActivityRecord();
    $record->id = $this->uuidFactory->generateRaw();
    $record->clientId = $clientId;
    $record->intervention = $intervention;
    $record->organizationId = $organizationId;
    $record->actorId = $actorId;
    $record->kind = $kind;
    $record->event = $event;
    $record->body = $body;
    $record->payload = $payload;
    $record->createdAt = new DateTimeImmutable();
    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return $this->views->activityView($record);
  }

  /**
   * Method listByIntervention.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return InterventionWorkflowPage the activity page result
   */
  public function listByIntervention(string $interventionId, int $page, int $itemsPerPage): InterventionWorkflowPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('a')
      ->from(InterventionActivityRecord::class, 'a')
      ->where('a.intervention = :intervention')
      ->setParameter('intervention', $intervention)
      ->orderBy('a.createdAt', 'ASC');
    $total = (int) (clone $qb)->resetDQLPart('orderBy')->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
    /** @var list<InterventionActivityRecord> $records */
    $records = $qb
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new InterventionWorkflowPage(
      array_map($this->views->activityView(...), $records),
      $page,
      $itemsPerPage,
      $total,
    );
  }
}
