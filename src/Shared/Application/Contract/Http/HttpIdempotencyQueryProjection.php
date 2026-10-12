<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use InvalidArgumentException;
use Shared\Application\Message\{QueryMessage, ResultMessage};

use function is_a;

/**
 * Contract HttpIdempotencyQueryProjection.
 *
 * Declares exact read messages and immutable result classes that an owner permits in its mutation receipt.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HttpIdempotencyQueryProjection
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param class-string<QueryMessage> $queryClass the exact owner-private query schema
   * @param class-string<ResultMessage> $resultClass the exact query result schema
   * @param list<class-string> $contractClasses immutable nested result types explicitly allowed by the owner
   * @param bool $captureBeforePrimary whether processor prechecks are captured for revision-preserving replay
   * @param bool $refreshBeforeCommit whether an observed query is refreshed inside the owning transaction
   */
  public function __construct(
    public string $queryClass,
    public string $resultClass,
    public array $contractClasses = [],
    public bool $captureBeforePrimary = true,
    public bool $refreshBeforeCommit = true,
  ) {
    if (!self::validMessages($queryClass, $resultClass)) {
      throw new InvalidArgumentException('HTTP idempotency query projection requires explicit message schemas.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method validMessages.
   *
   * Validates runtime metadata independently of trusted class-string annotations.
   *
   * @since 1.0.0
   *
   * @param string $queryClass the configured exact query name
   * @param string $resultClass the configured exact result name
   *
   * @return bool whether both names implement their message marker
   */
  private static function validMessages(string $queryClass, string $resultClass): bool
  {
    return is_a($queryClass, QueryMessage::class, true) && is_a($resultClass, ResultMessage::class, true);
  }
  // #endregion
}
