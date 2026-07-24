<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;
use Webhook\Infrastructure\Persistence\Doctrine\Mapper\WebhookSubscriptionMapper;
use Webhook\Infrastructure\Persistence\Doctrine\Record\WebhookSubscriptionRecord;

use function array_map;
use function in_array;

/**
 * Repository WebhookSubscriptionRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookSubscriptionRepository implements WebhookSubscriptionRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<WebhookSubscriptionRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(WebhookSubscriptionRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(WebhookSubscription $subscription): void
  {
    $record = $this->repository->find((string) $subscription->id());
    $newRecord = false;

    if (!$record instanceof WebhookSubscriptionRecord) {
      $record = new WebhookSubscriptionRecord();
      $newRecord = true;
    }

    WebhookSubscriptionMapper::toRecord($subscription, $record);

    if ($newRecord) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function remove(WebhookSubscription $subscription): void
  {
    $record = $this->repository->find((string) $subscription->id());

    if ($record instanceof WebhookSubscriptionRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  public function findById(WebhookSubscriptionId $id): ?WebhookSubscription
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof WebhookSubscriptionRecord ? WebhookSubscriptionMapper::toDomain($record) : null;
  }

  public function listByOrganization(string $organizationId, int $limit, int $offset): array
  {
    /** @var list<WebhookSubscriptionRecord> $records */
    $records = $this->repository->createQueryBuilder('s')
      ->where('s.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId)
      ->orderBy('s.createdAt', 'DESC')
      ->addOrderBy('s.id', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(WebhookSubscriptionMapper::toDomain(...), $records);
  }

  public function countByOrganization(string $organizationId): int
  {
    return (int) $this->repository->count(['organizationId' => $organizationId]);
  }

  public function countActiveByOrganization(string $organizationId): int
  {
    return (int) $this->repository->count(['organizationId' => $organizationId, 'isActive' => true]);
  }

  public function findActiveByOrganizationAndEventType(string $organizationId, string $eventType): array
  {
    /** @var list<WebhookSubscriptionRecord> $records */
    $records = $this->repository->findBy([
      'organizationId' => $organizationId,
      'isActive' => true,
    ]);

    $matching = [];
    foreach ($records as $record) {
      if (in_array($eventType, $record->eventTypes, true)) {
        $matching[] = WebhookSubscriptionMapper::toDomain($record);
      }
    }

    return $matching;
  }
  // #endregion
}
