<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\{ArrayParameterType, ParameterType};
use Doctrine\ORM\EntityManagerInterface;
use User\Application\Contract\Presence\{PresencePreference, PresencePreferenceWrite};
use User\Application\Port\Inbound\PresencePreferenceReaderPort;
use User\Application\Port\Outbound\PresencePreferenceRepositoryPort;

/**
 * Service UserPresencePreferenceRepository.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserPresencePreferenceRepository implements PresencePreferenceRepositoryPort, PresencePreferenceReaderPort
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * @since 1.0.0
   */
  public function get(string $userId): PresencePreference
  {
    return $this->readMany([$userId])[$userId] ?? new PresencePreference();
  }

  /**
   * @since 1.0.0
   */
  public function readMany(array $userIds): array
  {
    if ([] === $userIds) {
      return [];
    }
    /** @var list<array{user_id: string, do_not_disturb: bool, invisible: bool, revision: int}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery(
      'SELECT user_id, do_not_disturb, invisible, revision FROM user_presence_preferences WHERE user_id IN (?)',
      [$userIds],
      [ArrayParameterType::STRING],
    )->fetchAllAssociative();
    $preferences = [];
    foreach ($rows as $row) {
      $preferences[(string) $row['user_id']] = new PresencePreference((bool) $row['do_not_disturb'], (int) $row['revision'], (bool) $row['invisible']);
    }

    return $preferences;
  }

  /**
   * @since 1.0.0
   */
  public function save(string $userId, ?bool $doNotDisturb, ?bool $invisible = null): PresencePreferenceWrite
  {
    // PostgreSQL serializes concurrent upserts on the unique account key. A no-op
    // retains its revision; the returned preference always reflects a committed write.
    return $this->entityManager->getConnection()->transactional(function () use ($userId, $doNotDisturb, $invisible): PresencePreferenceWrite {
      /** @var array{do_not_disturb: bool, invisible: bool, revision: int}|false $row */
      $row = $this->entityManager->getConnection()->executeQuery(
        'INSERT INTO user_presence_preferences (user_id, do_not_disturb, invisible, revision)
         VALUES (:id, COALESCE(CAST(:dnd AS BOOLEAN), false), COALESCE(CAST(:invisible AS BOOLEAN), false), :revision)
         ON CONFLICT (user_id) DO UPDATE SET
           do_not_disturb = COALESCE(CAST(:dnd AS BOOLEAN), user_presence_preferences.do_not_disturb),
           invisible = COALESCE(CAST(:invisible AS BOOLEAN), user_presence_preferences.invisible),
           revision = user_presence_preferences.revision + 1
         WHERE user_presence_preferences.do_not_disturb IS DISTINCT FROM COALESCE(CAST(:dnd AS BOOLEAN), user_presence_preferences.do_not_disturb)
            OR user_presence_preferences.invisible IS DISTINCT FROM COALESCE(CAST(:invisible AS BOOLEAN), user_presence_preferences.invisible)
         RETURNING do_not_disturb, invisible, revision',
        ['id' => $userId, 'dnd' => $doNotDisturb, 'invisible' => $invisible, 'revision' => ($doNotDisturb || $invisible) ? 1 : 0],
        ['dnd' => null === $doNotDisturb ? ParameterType::NULL : ParameterType::BOOLEAN, 'invisible' => null === $invisible ? ParameterType::NULL : ParameterType::BOOLEAN, 'revision' => ParameterType::INTEGER],
      )->fetchAssociative();
      if (false === $row) {
        return new PresencePreferenceWrite($this->get($userId), false);
      }
      $preference = new PresencePreference((bool) $row['do_not_disturb'], (int) $row['revision'], (bool) $row['invisible']);

      return new PresencePreferenceWrite($preference, $preference->revision > 0);
    });
  }
}
