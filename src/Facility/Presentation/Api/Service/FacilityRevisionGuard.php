<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};

use function preg_match;

/**
 * Service FacilityRevisionGuard.
 *
 * Parses the public If-Match contract for Facility mutations. Persistence
 * performs the final comparison inside the owning main transaction.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityRevisionGuard
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(private RequestStack $requestStack)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method expectedRevision.
   *
   * @since 1.0.0
   */
  public function expectedRevision(): int
  {
    $ifMatch = $this->requestStack->getCurrentRequest()?->headers->get('If-Match');
    if (null === $ifMatch || '' === $ifMatch) {
      throw new PreconditionRequiredHttpException('If-Match is required for this mutation.');
    }
    if (1 !== preg_match('/^"revision-(\d+)"$/', $ifMatch, $matches)) {
      throw new PreconditionFailedHttpException('The resource revision is stale.');
    }

    return (int) $matches[1];
  }

  /**
   * Method assertMatches.
   *
   * @since 1.0.0
   */
  public function assertMatches(int $revision): void
  {
    if ($revision !== $this->expectedRevision()) {
      throw new PreconditionFailedHttpException('The resource revision is stale.');
    }
  }
  // #endregion
}
