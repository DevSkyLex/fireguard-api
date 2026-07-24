<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Http;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};

use function preg_match;

/**
 * Domain RevisionGuard.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevisionGuard
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the RevisionGuard class.
   *
   * @since 1.0.0
   *
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(private RequestStack $requestStack)
  {
  }

  /**
   * Method assertMatches.
   *
   * Executes the assert matches operation.
   *
   * @since 1.0.0
   *
   * @param int $revision the revision value
   */
  public function assertMatches(int $revision): void
  {
    $expectedRevision = $this->expectedRevision();
    if ($revision !== $expectedRevision) {
      throw new PreconditionFailedHttpException('The resource revision is stale.');
    }
  }

  /**
   * Method expectedRevision.
   *
   * Executes the expected revision operation.
   *
   * @since 1.0.0
   *
   * @return int the expected revision result
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
}
