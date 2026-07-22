<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;

use function array_key_exists;
use function array_map;
use function count;
use function in_array;
use function is_string;
use function mb_strlen;
use function trim;

/**
 * ValueObject MessageReference.
 *
 * A structured `{type, id, label?, code?}` rich-card reference attached to a
 * message (B3) — e.g. a message that points at a specific non-conformity,
 * intervention, facility, or equipment record. Shape validation only: `type`
 * is restricted to the four subject types the `messaging.subject_resolver`
 * seam already knows how to resolve (see `MessagingSubjectType`), and `id`
 * must be non-blank. This VO does NOT itself check that the referenced
 * record exists in the caller's organization — that is an outbound-port
 * concern, performed by the command handler via the EXISTING
 * `messaging.subject_resolver` seam (the "cheap existence-check port
 * pattern" already used to resolve a conversation's own subject), see
 * `PostMessageHandler`/`EditMessageHandler`.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessageReference
{
  // #region Constants
  /**
   * Constant MAX_REFERENCES.
   *
   * Largest number of references a single message may carry.
   */
  public const int MAX_REFERENCES = 5;

  /**
   * Constant ALLOWED_TYPES.
   *
   * The reference type whitelist — deliberately the same four values as
   * {@see MessagingSubjectType}'s resolver-backed cases (excludes `channel`/
   * `direct`, which have no owning record to reference).
   *
   * @var list<string>
   */
  private const array ALLOWED_TYPES = ['non_conformity', 'intervention', 'facility', 'equipment'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Use {@see self::fromArray()} instead of instantiating directly.
   *
   * @since 1.0.0
   *
   * @param string $type the reference type value
   * @param string $id the referenced record's identifier
   * @param ?string $label an optional display label
   * @param ?string $code an optional display code
   */
  private function __construct(
    public string $type,
    public string $id,
    public ?string $label,
    public ?string $code,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray.
   *
   * @static
   *
   * Validates and builds a single reference from its raw shape.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the raw reference shape
   *
   * @return self the validated reference
   */
  public static function fromArray(array $data): self
  {
    $type = self::stringValue($data, 'type');
    if (!in_array($type, self::ALLOWED_TYPES, true)) {
      throw new MessagingValidationException('A message reference "type" must be one of: non_conformity, intervention, facility, equipment.');
    }

    $id = trim(self::stringValue($data, 'id'));
    if ('' === $id) {
      throw new MessagingValidationException('A message reference "id" must not be blank.');
    }

    $label = self::nullableStringValue($data, 'label');
    if (null !== $label && mb_strlen($label) > 255) {
      throw new MessagingValidationException('A message reference "label" must not exceed 255 characters.');
    }

    $code = self::nullableStringValue($data, 'code');
    if (null !== $code && mb_strlen($code) > 64) {
      throw new MessagingValidationException('A message reference "code" must not exceed 64 characters.');
    }

    return new self($type, $id, $label, $code);
  }

  /**
   * Method listFromArray.
   *
   * @static
   *
   * Validates and builds a bounded list of references (at most
   * {@see self::MAX_REFERENCES}).
   *
   * @since 1.0.0
   *
   * @param list<array<string, mixed>> $items the raw reference shapes
   *
   * @return list<self> the validated references
   */
  public static function listFromArray(array $items): array
  {
    if (count($items) > self::MAX_REFERENCES) {
      throw new MessagingValidationException('A message may carry at most ' . self::MAX_REFERENCES . ' references.');
    }

    return array_map(self::fromArray(...), $items);
  }

  /**
   * Method toArray.
   *
   * @since 1.0.0
   *
   * @return array{type: string, id: string, label: ?string, code: ?string} the raw reference shape
   */
  public function toArray(): array
  {
    return ['type' => $this->type, 'id' => $this->id, 'label' => $this->label, 'code' => $this->code];
  }

  /**
   * Method stringValue.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the raw reference shape
   * @param string $key the field name
   *
   * @return string the field's string value, or an empty string when missing/invalid
   */
  private static function stringValue(array $data, string $key): string
  {
    $value = $data[$key] ?? null;

    return is_string($value) ? $value : '';
  }

  /**
   * Method nullableStringValue.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the raw reference shape
   * @param string $key the field name
   *
   * @return ?string the field's string value, or null when missing/blank/invalid
   */
  private static function nullableStringValue(array $data, string $key): ?string
  {
    if (!array_key_exists($key, $data) || !is_string($data[$key])) {
      return null;
    }

    $trimmed = trim($data[$key]);

    return '' === $trimmed ? null : $trimmed;
  }
  // #endregion
}
