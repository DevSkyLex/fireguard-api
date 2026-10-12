<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency\ResponseProjection;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Message\{CommandMessage, QueryMessage};

/**
 * Context HttpIdempotencyResponseProjectionState.
 *
 * Keeps bounded precheck and response snapshots on their authorized HTTP request.
 *
 * @category Context
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class HttpIdempotencyResponseProjectionState
{
  // #region Properties
  /**
   * The processor's current before, executing or after primary phase.
   */
  public string $phase = 'before';

  /**
   * Whether this request restores an authorized committed receipt.
   */
  public bool $replay = false;

  /**
   * The exact primary command passed to owner output contributors.
   */
  public ?CommandMessage $command = null;

  /**
   * @var array<class-string, HttpIdempotencyQueryProjection> exact owner-declared query schemas
   */
  public array $schemas = [];

  /**
   * @var array<string, QueryMessage> initially observed queries, never reconstructed from receipt bytes
   */
  public array $observed = [];

  /**
   * @var array<string, array{class: string, query: string, result: string}> initial processor prechecks
   */
  public array $before = [];

  /**
   * @var array<string, array{class: string, query: string, result: string}> before-commit postwrite output queries
   */
  public array $after = [];
  // #endregion

  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param HttpIdempotencyOperation $operation the currently authorized owner descriptor
   */
  public function __construct(public readonly HttpIdempotencyOperation $operation)
  {
  }
  // #endregion
}
