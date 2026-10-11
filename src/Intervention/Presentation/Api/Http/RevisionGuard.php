<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Http;

use Shared\Application\Contract\Http\{RevisionMismatchException, RevisionMissingException, RevisionPrecondition};
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};

/**
 * Domain RevisionGuard.
 *
 * @category Domain
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
    if (!RevisionPrecondition::matches($expectedRevision, $revision)) {
      throw new PreconditionFailedHttpException(RevisionMismatchException::MESSAGE);
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

    try {
      return RevisionPrecondition::expectedRevision($ifMatch);
    } catch (RevisionMissingException $failure) {
      throw new PreconditionRequiredHttpException($failure->getMessage());
    } catch (RevisionMismatchException $failure) {
      throw new PreconditionFailedHttpException($failure->getMessage());
    }
  }
}
