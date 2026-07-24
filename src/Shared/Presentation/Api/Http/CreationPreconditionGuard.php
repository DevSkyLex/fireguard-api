<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Http;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};

/**
 * Domain CreationPreconditionGuard.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreationPreconditionGuard
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreationPreconditionGuard class.
   *
   * @since 1.0.0
   *
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(private RequestStack $requestStack)
  {
  }

  /**
   * Method assertCreateOnly.
   *
   * Executes the assert create only operation.
   *
   * @since 1.0.0
   */
  public function assertCreateOnly(): void
  {
    $ifNoneMatch = $this->requestStack->getCurrentRequest()?->headers->get('If-None-Match');
    if (null === $ifNoneMatch || '' === $ifNoneMatch) {
      throw new PreconditionRequiredHttpException('If-None-Match: * is required for client UUID creation.');
    }
    if ('*' !== $ifNoneMatch) {
      throw new PreconditionFailedHttpException('Client UUID creation requires If-None-Match: *.');
    }
  }
}
