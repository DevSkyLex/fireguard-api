<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Http;

use Shared\Application\Contract\Http\MergePatchDocument;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Domain MergePatchFields.
 *
 * @category Domain
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MergePatchFields
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MergePatchFields class.
   *
   * @since 1.0.0
   *
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(private RequestStack $requestStack)
  {
  }

  /**
   * Method all.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed>
   */
  public function all(): array
  {
    $request = $this->requestStack->getCurrentRequest();
    if (null === $request || 'PATCH' !== $request->getMethod()) {
      return [];
    }

    return MergePatchDocument::fields($request->getContent());
  }
}
