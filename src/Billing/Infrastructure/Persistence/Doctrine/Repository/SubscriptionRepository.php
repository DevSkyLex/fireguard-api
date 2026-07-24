<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Persistence\Doctrine\Repository;

use Billing\Application\Port\Outbound\SubscriptionRepositoryPort;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Infrastructure\Persistence\Doctrine\Mapper\SubscriptionMapper;
use Billing\Infrastructure\Persistence\Doctrine\Record\SubscriptionRecord;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

/**
 * Repository SubscriptionRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SubscriptionRepository implements SubscriptionRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<SubscriptionRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SubscriptionRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(SubscriptionRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the subscription aggregate (insert or update).
   *
   * @since 1.0.0
   *
   * @param Subscription $subscription the subscription aggregate
   */
  public function save(Subscription $subscription): void
  {
    $existing = $this->repository->find((string) $subscription->id());

    if ($existing instanceof SubscriptionRecord) {
      SubscriptionMapper::applyTo($existing, $subscription);
    } else {
      $this->entityManager->persist(SubscriptionMapper::toRecord($subscription));
    }

    $this->entityManager->flush();
  }

  /**
   * Method findByOrganizationId.
   *
   * Finds the subscription owned by an organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?Subscription the subscription when found
   */
  public function findByOrganizationId(string $organizationId): ?Subscription
  {
    $record = $this->repository->findOneBy(['organizationId' => $organizationId]);

    return $record instanceof SubscriptionRecord ? SubscriptionMapper::toDomain($record) : null;
  }

  /**
   * Method findByStripeCustomerId.
   *
   * Finds the subscription linked to a Stripe customer.
   *
   * @since 1.0.0
   *
   * @param string $stripeCustomerId the Stripe customer identifier
   *
   * @return ?Subscription the subscription when found
   */
  public function findByStripeCustomerId(string $stripeCustomerId): ?Subscription
  {
    $record = $this->repository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

    return $record instanceof SubscriptionRecord ? SubscriptionMapper::toDomain($record) : null;
  }
  // #endregion
}
