<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Notification\Application\Port\Outbound\NotificationPreferenceRepositoryPort;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Notification\Infrastructure\Persistence\Doctrine\Mapper\NotificationPreferenceMapper;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationPreferenceRecord;

use function array_map;

/**
 * Class NotificationPreferenceRepository
 *
 * Reads and persists user notification preferences through Doctrine.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationPreferenceRepository implements NotificationPreferenceRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<NotificationPreferenceRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Creates the repository and prepares the preference record repository.
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
    $this->repository = $entityManager->getRepository(NotificationPreferenceRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method findByUserId
   *
   * Lists a user’s notification preferences in category order.
   *
   * @access public
   *
   * @param string $userId user whose preferences are requested
   *
   * @return list<NotificationPreference> preference aggregates
   */
  public function findByUserId(string $userId): array
  {
    /** @var list<NotificationPreferenceRecord> $records */
    $records = $this->repository->findBy(['userId' => $userId], ['category' => 'ASC']);

    return array_map(
      static fn (NotificationPreferenceRecord $record): NotificationPreference => NotificationPreferenceMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method findByUserIdAndCategory
   *
   * Finds one preference by its user and category pair.
   *
   * @access public
   *
   * @param string $userId user whose preference is requested
   * @param string $category preference category
   *
   * @return NotificationPreference|null preference aggregate, or null when none exists
   */
  public function findByUserIdAndCategory(string $userId, string $category): ?NotificationPreference
  {
    $record = $this->repository->find(['userId' => $userId, 'category' => $category]);

    if (!$record instanceof NotificationPreferenceRecord) {
      return null;
    }

    return NotificationPreferenceMapper::toDomain($record);
  }

  /**
   * Method saveMany
   *
   * Updates existing preference records or persists new ones, then flushes the unit of work.
   *
   * @access public
   *
   * @param list<NotificationPreference> $preferences preference aggregates to persist
   *
   * @return void
   */
  public function saveMany(array $preferences): void
  {
    foreach ($preferences as $preference) {
      $record = NotificationPreferenceMapper::toRecord($preference);
      $existing = $this->repository->find(['userId' => $record->userId, 'category' => $record->category]);

      if ($existing instanceof NotificationPreferenceRecord) {
        $existing->emailEnabled = $record->emailEnabled;
        $existing->mercureEnabled = $record->mercureEnabled;
        $existing->updatedAt = $record->updatedAt;
      } else {
        $this->entityManager->persist($record);
      }
    }

    $this->entityManager->flush();
  }
  // #endregion
}
