<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use InvalidArgumentException;
use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage};

use function in_array;
use function is_a;
use function trim;

/**
 * Class HttpIdempotencyOperation
 *
 * Declares one mutation's database, final transaction owner and primary application result schema.
 * Database/transaction ownership is explicit and is never inferred from a default connection.
 *
 * @category Contract
 */
final readonly class HttpIdempotencyOperation
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Root-owned media commands receive transaction hooks without an enclosing bus transaction.
   * Bus-owned ordinary commands run completely within their declared owning database transaction.
   *
   * @access public
   *
   * @param string $name the stable API operation name
   * @param string $database the explicitly declared auth or main database
   * @param string $transactionMode bus_owner or root_owner final transaction ownership
   * @param class-string<CommandMessage> $primaryCommand the exact route-primary command type
   * @param class-string<ResultMessage> $resultClass the exact primary application result type
   * @param list<class-string> $contractClasses additional owner-published immutable result contract types
   * @param int $statusCode the operation's successful status hint
   * @param bool $secret whether the application result requires encrypted short-lived replay
   * @param bool $requiredKey whether compatibility staging has enabled the mandatory header for this operation
   * @param string|null $resultIdentifierProperty explicit public scalar result identifier field to bind before replay decode
   * @param string|null $resourceKind owner-declared resource scope lookup kind
   * @param list<HttpIdempotencyQueryProjection> $queryProjections exact query schemas required to preserve the final response projection
   * @param list<class-string<QueryMessage>> $freshAuthorizationQueries explicit authorization-only reads that must execute live on every attempt
   * @param bool $resultIdentifierNullable whether an existent declared result identifier may be explicitly null
   *
   * @return void
   */
  public function __construct(
    public string $name,
    public string $database,
    public string $transactionMode,
    public string $primaryCommand,
    public string $resultClass,
    public array $contractClasses = [],
    public int $statusCode = 200,
    public bool $secret = false,
    public bool $requiredKey = false,
    public ?string $resultIdentifierProperty = null,
    public ?string $resourceKind = null,
    public array $queryProjections = [],
    public array $freshAuthorizationQueries = [],
    public bool $resultIdentifierNullable = false,
  ) {
    if ('' === trim($name) || !in_array($database, ['auth', 'main'], true) || !in_array($transactionMode, ['bus_owner', 'root_owner'], true)) {
      throw new InvalidArgumentException('HTTP idempotency operation ownership is incomplete.');
    }
    if (!self::validMessages($primaryCommand, $resultClass)) {
      throw new InvalidArgumentException('HTTP idempotency operation message schema is invalid.');
    }
    if ($statusCode < 200 || $statusCode > 299) {
      throw new InvalidArgumentException('HTTP idempotency operation success status is invalid.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method validMessages
   *
   * Validates runtime metadata independently of the trusted static class-string annotations.
   *
   * @access private
   *
   * @param string $primaryCommand the configured command class name
   * @param string $resultClass the configured result class name
   *
   * @return bool whether both types implement the public message contracts
   */
  private static function validMessages(string $primaryCommand, string $resultClass): bool
  {
    return is_a($primaryCommand, CommandMessage::class, true) && is_a($resultClass, ResultMessage::class, true);
  }
  // #endregion
}
