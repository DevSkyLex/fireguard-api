<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Adapter\Doctrine;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Shared\Application\Port\Outbound\HealthCheckPort;
use Throwable;

use function bin2hex;
use function random_bytes;

/**
 * Adapter HealthCheckAdapter.
 *
 * Implements health checks for database and cache dependencies.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HealthCheckAdapter implements HealthCheckPort
{
  // #region Constructor
  public function __construct(
    private Connection $authConnection,
    private Connection $mainConnection,
    private CacheItemPoolInterface $cache,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function checkDatabase(): bool
  {
    try {
      $this->authConnection->executeQuery('SELECT 1');
      $this->mainConnection->executeQuery('SELECT 1');

      return true;
    } catch (Throwable) {
      return false;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function checkCache(): bool
  {
    try {
      $testKey = 'health_check_' . bin2hex(random_bytes(8));
      $item = $this->cache->getItem($testKey);
      $item->set('ok');
      $item->expiresAfter(10);
      $this->cache->save($item);

      $retrieved = $this->cache->getItem($testKey);

      $this->cache->deleteItem($testKey);

      return $retrieved->isHit() && 'ok' === $retrieved->get();
    } catch (Throwable) {
      return false;
    }
  }
  // #endregion
}
