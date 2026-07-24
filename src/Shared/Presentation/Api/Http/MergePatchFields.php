<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Http;

use Symfony\Component\HttpFoundation\RequestStack;

use function array_keys;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Domain MergePatchFields.
 *
 * @category Domain
 *
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
    $fields = json_decode($request->getContent(), true);
    if (!is_array($fields)) {
      return [];
    }
    foreach (array_keys($fields) as $key) {
      if (!is_string($key)) {
        return [];
      }
    }

    /** @var array<string, mixed> $fields */
    return $fields;
  }
}
