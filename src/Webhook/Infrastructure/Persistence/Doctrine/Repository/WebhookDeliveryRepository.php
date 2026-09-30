<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Webhook\Application\Port\Outbound\WebhookDeliveryRepositoryPort;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};
use Webhook\Infrastructure\Persistence\Doctrine\Mapper\WebhookDeliveryMapper;
use Webhook\Infrastructure\Persistence\Doctrine\Record\WebhookDeliveryRecord;

use function array_map;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class WebhookDeliveryRepository
 *
 * Reserves webhook deliveries idempotently and provides their persisted state.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookDeliveryRepository implements WebhookDeliveryRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<WebhookDeliveryRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Creates the repository and prepares the delivery record repository.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(WebhookDeliveryRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method reserve
   *
   * Reserves a delivery with an insert-on-conflict operation so duplicate events remain transaction-safe.
   *
   * @access public
   *
   * @param WebhookDeliveryId $id delivery identifier
   * @param WebhookSubscriptionId $subscriptionId receiving subscription identifier
   * @param string $organizationId tenant scope for the delivery
   * @param string $eventType event type being delivered
   * @param string $eventId source event identifier
   * @param array<string, mixed> $payload event payload to store
   *
   * @return bool whether a new reservation was inserted
   */
  public function reserve(
    WebhookDeliveryId $id,
    WebhookSubscriptionId $subscriptionId,
    string $organizationId,
    string $eventType,
    string $eventId,
    array $payload,
  ): bool {
    $now = new DateTimeImmutable();

    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush()
    // closes the EntityManager (see
    // `Automation\Infrastructure\Persistence\Doctrine\Repository\AutomationRunRepository::reserveRun()`
    // for the same concern and precedent). A duplicate reservation is an
    // expected, routine outcome here (fan-out re-run, Messenger
    // redelivery), not an exceptional one.
    // ON CONFLICT DO NOTHING makes a duplicate reservation an in-DB no-op
    // returning zero affected rows — no exception, so the surrounding
    // transaction is never aborted (catching the violation instead poisons it on
    // PostgreSQL).
    $affected = $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO webhook_deliveries '
      . '(id, subscription_id, organization_id, event_type, event_id, payload, status, attempts, created_at, updated_at) '
      . 'VALUES (:id, :subscriptionId, :organizationId, :eventType, :eventId, :payload, :status, :attempts, :createdAt, :updatedAt) '
      . 'ON CONFLICT DO NOTHING',
      [
        'id' => (string) $id,
        'subscriptionId' => (string) $subscriptionId,
        'organizationId' => $organizationId,
        'eventType' => $eventType,
        'eventId' => $eventId,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'status' => WebhookDeliveryStatus::PENDING->value,
        'attempts' => 0,
        'createdAt' => $now,
        'updatedAt' => $now,
      ],
      [
        'createdAt' => 'datetime_immutable',
        'updatedAt' => 'datetime_immutable',
      ],
    );

    return $affected > 0;
  }

  /**
   * Method save
   *
   * Inserts or updates a delivery record from its domain aggregate.
   *
   * @access public
   *
   * @param WebhookDelivery $delivery delivery aggregate to persist
   *
   * @return void
   */
  public function save(WebhookDelivery $delivery): void
  {
    $record = $this->repository->find((string) $delivery->id());
    $newRecord = false;

    if (!$record instanceof WebhookDeliveryRecord) {
      $record = new WebhookDeliveryRecord();
      $newRecord = true;
    }

    WebhookDeliveryMapper::toRecord($delivery, $record);

    if ($newRecord) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById
   *
   * Finds a delivery by its identifier and maps it to the domain model.
   *
   * @access public
   *
   * @param WebhookDeliveryId $id delivery identifier
   *
   * @return WebhookDelivery|null delivery aggregate, or null when absent
   */
  public function findById(WebhookDeliveryId $id): ?WebhookDelivery
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof WebhookDeliveryRecord ? WebhookDeliveryMapper::toDomain($record) : null;
  }

  /**
   * Method listBySubscription
   *
   * Lists subscription deliveries newest first with optional status filtering and pagination.
   *
   * @access public
   *
   * @param WebhookSubscriptionId $subscriptionId subscription whose deliveries are listed
   * @param string|null $status optional exact status filter
   * @param int $limit maximum number of rows
   * @param int $offset zero-based row offset
   *
   * @return list<WebhookDelivery> matching delivery aggregates
   */
  public function listBySubscription(WebhookSubscriptionId $subscriptionId, ?string $status, int $limit, int $offset): array
  {
    $queryBuilder = $this->repository->createQueryBuilder('d')
      ->where('d.subscriptionId = :subscriptionId')
      ->setParameter('subscriptionId', (string) $subscriptionId)
      ->orderBy('d.createdAt', 'DESC')
      ->addOrderBy('d.id', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    if (null !== $status) {
      $queryBuilder->andWhere('d.status = :status')->setParameter('status', $status);
    }

    /** @var list<WebhookDeliveryRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    return array_map(WebhookDeliveryMapper::toDomain(...), $records);
  }

  /**
   * Method countBySubscription
   *
   * Counts a subscription’s deliveries with an optional exact status filter.
   *
   * @access public
   *
   * @param WebhookSubscriptionId $subscriptionId subscription whose deliveries are counted
   * @param string|null $status optional exact status filter
   *
   * @return int matching delivery count
   */
  public function countBySubscription(WebhookSubscriptionId $subscriptionId, ?string $status): int
  {
    $criteria = ['subscriptionId' => (string) $subscriptionId];

    if (null !== $status) {
      $criteria['status'] = $status;
    }

    return (int) $this->repository->count($criteria);
  }
  // #endregion
}
