<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\FreshEmailOwnershipPort;
use User\Domain\Exception\EmailOwnershipUnavailableException;

use function in_array;
use function is_string;

/**
 * Class FreshEmailOwnershipAdapter
 *
 * Reads current email ownership directly from the owning auth row without account projection caches.
 *
 * @category Adapter
 */
final readonly class FreshEmailOwnershipAdapter implements FreshEmailOwnershipPort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected auth connection
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function get(string $userId): EmailOwnershipResult
  {
    $row = $this->connection->fetchAssociative('SELECT email, locale, status, email_ownership_verified_at FROM users WHERE id = :id', ['id' => $userId]);
    if (false === $row || 'active' !== ($row['status'] ?? null) || !is_string($row['email'] ?? null)) {
      throw EmailOwnershipUnavailableException::unavailable();
    }
    $verifiedAt = $row['email_ownership_verified_at'] ?? null;
    $date = is_string($verifiedAt) ? new DateTimeImmutable($verifiedAt) : null;
    $locale = $row['locale'] ?? 'en';

    return new EmailOwnershipResult($userId, $row['email'], null !== $date, is_string($locale) && in_array($locale, ['en', 'fr', 'es'], true) ? $locale : 'en', $date);
  }
  // #endregion
}
