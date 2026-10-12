<?php

declare(strict_types=1);

namespace Shared\Application\Service\Idempotency;

use InvalidArgumentException;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResultProofContributionPort;

use function sprintf;

/**
 * Class HttpIdempotencyResultProofRegistry
 *
 * Materializes at most one owner result-proof contributor for every declared operation during construction.
 *
 * @category Service
 */
final readonly class HttpIdempotencyResultProofRegistry
{
  // #region Properties
  /**
   * @var array<string, HttpIdempotencyResultProofContributionPort> unique owner contributions
   */
  private array $contributors;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param HttpIdempotencyOperationRegistry $operations declared operation ownership
   * @param iterable<HttpIdempotencyResultProofContributionPort> $contributors registered result-proof owners
   *
   * @return void
   */
  public function __construct(HttpIdempotencyOperationRegistry $operations, iterable $contributors)
  {
    $owners = [];
    foreach ($contributors as $contributor) {
      foreach ($operations->all() as $name => $operation) {
        if (!$contributor->supports($operation)) {
          continue;
        }
        if (isset($owners[$name])) {
          throw new InvalidArgumentException(sprintf('Duplicate HTTP result-proof ownership for "%s".', $name));
        }
        $owners[$name] = $contributor;
      }
    }
    $this->contributors = $owners;
  }
  // #endregion

  // #region Methods
  /**
   * Method find
   *
   * @access public
   *
   * @param HttpIdempotencyOperation $operation the declared owning operation
   *
   * @return HttpIdempotencyResultProofContributionPort|null its unique owner contributor
   */
  public function find(HttpIdempotencyOperation $operation): ?HttpIdempotencyResultProofContributionPort
  {
    return $this->contributors[$operation->name] ?? null;
  }
  // #endregion
}
