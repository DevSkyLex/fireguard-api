<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Service\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;

use function is_array;
use function is_string;

/**
 * Class OrganizationReplayInput
 *
 * Reads canonical owner input without trusting browser-supplied organization scope.
 *
 * @category Service
 */
final readonly class OrganizationReplayInput
{
  // #region Methods
  /**
   * Method identifier
   *
   * Rejects malformed optional identifiers before any owner lookup.
   *
   * @access public
   *
   * @param mixed $value trusted route or parsed input value
   *
   * @return string|null valid optional identifier
   */
  public static function identifier(mixed $value): ?string
  {
    if (null !== $value && (!is_string($value) || '' === $value)) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $value;
  }

  /**
   * Method fields
   *
   * Preserves canonical JSON and multipart field extraction without coercion.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request parsed canonical request
   *
   * @return array<string, mixed> original JSON or multipart scalar fields
   */
  public static function fields(HttpIdempotencyAuthorizationRequest $request): array
  {
    $body = $request->canonicalInput['body'] ?? null;
    if (!is_array($body)) {
      return [];
    }
    $value = $body['value'] ?? null;
    $source = is_array($value) ? ($value['fields'] ?? null) : ($body['fields'] ?? null);
    $fields = [];
    if (is_array($source)) {
      foreach ($source as $key => $field) {
        if (is_string($key)) {
          $fields[$key] = $field;
        }
      }
    }

    return $fields;
  }
  // #endregion
}
