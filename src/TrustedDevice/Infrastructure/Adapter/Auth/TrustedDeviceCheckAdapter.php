<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Adapter\Auth;

use Auth\Application\Port\Outbound\TrustedDeviceCheckPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted\{CheckDeviceTrustedQuery, CheckDeviceTrustedResult};

/**
 * Adapter TrustedDeviceCheckAdapter.
 *
 * Implements the TrustedDeviceCheckPort interface from the Auth module,
 * delegating to the TrustedDevice module's query handler.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustedDeviceCheckAdapter implements TrustedDeviceCheckPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TrustedDeviceCheckAdapter class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isTrusted
   * {@inheritDoc}
   *
   * Checks if the provided device token is valid and trusted
   * for the given user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $deviceToken the trusted device token (from cookie)
   *
   * @return bool true if the device is trusted, false otherwise
   */
  public function isTrusted(string $userId, string $deviceToken): bool
  {
    try {
      $query = new CheckDeviceTrustedQuery(
        userId: $userId,
        token: $deviceToken,
      );

      /** @var CheckDeviceTrustedResult $result */
      $result = $this->queryBus->ask(query: $query);

      return $result->trusted;
    } catch (Throwable) {
      // If check fails, consider device not trusted
      return false;
    }
  }
  // #endregion
}
