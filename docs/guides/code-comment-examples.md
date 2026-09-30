# PHP comment examples

These examples complement [the shared convention](code-comments.md). Each sample
is independent. Release and author values illustrate the existing project format:
copy them into source only when its history verifies them. Use the tags that add
information about the actual declaration; more tags do not justify unsupported
guarantees. Preserve existing analysis annotations and examples.

## Useful tags

| Tag | Use when |
| --- | --- |
| @category | Identifying the architectural responsibility of a class-like declaration. |
| @version / @since / @author | The release or authorship is verified; keep existing values. |
| @access | Recording the actual visibility of a method or member. |
| @param | Explaining every parameter, including units, optionality and constraints. |
| @return | Describing the result, including void and meaningful generics or shapes. |
| @var | A member needs a shape, collection element type or constraint beyond its PHP type. |
| @throws | A specific failure can escape the declaration; describe its condition. |
| @template | The declaration actually defines a generic parameter. |
| @extends / @implements / @use | Supplying generic arguments for the actual parent, interface or trait. |
| @see | A related declaration helps a caller understand the contract. |
| @example | A short example clarifies usage or a non-obvious constraint. |
| @deprecated | Deprecation is established and a real replacement exists. |
| @internal | The declaration is intentionally outside the supported public contract. |
| @phpstan-* / @psalm-* | An existing analysis contract needs the tool-specific annotation. |

Use ordinary PHPDoc prose. If a block contains @description, put the tag alone
on its line and the prose on the next line. Do not add @description to every PHP
block when ordinary prose already expresses its purpose.

## Class, property, constructor and method

~~~php
/**
 * Class NotificationTitle
 *
 * Holds a trimmed, non-empty title suitable for a notification heading.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @see \Stringable
 */
final readonly class NotificationTitle implements \Stringable
{
  // #region Properties
  /**
   * Property value
   *
   * Validated title text; surrounding whitespace is removed at construction.
   *
   * @access private
   * @since 1.0.0
   *
   * @var non-empty-string
   */
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Normalizes the title before storing it and rejects blank notification headings.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value the proposed title, including any surrounding whitespace
   *
   * @return void
   *
   * @throws \InvalidArgumentException when the normalized title is empty
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);

    if ($normalized === '') {
      throw new \InvalidArgumentException('A notification title must not be empty.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString
   *
   * Returns the normalized heading without changing the stored title.
   *
   * @access public
   * @since 1.0.0
   *
   * @return non-empty-string the title text ready for display
   *
   * @see \Stringable::__toString()
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion
}
~~~

The first member starts directly after its region marker. Separate subsequent
member docblocks with a blank line. Promoted properties remain in the constructor
group and are documented through its parameters.

## Typed constant

Place the block inside the class's meaningful constants or properties group.

~~~php
/**
 * Constant MAX_TITLE_BYTES
 *
 * Encoded-byte limit for a caller's UTF-8 title-length validation.
 *
 * @access private
 * @since 1.0.0
 *
 * @var positive-int
 */
private const int MAX_TITLE_BYTES = 160;
~~~

## Enum and cases

A case documents what the value means. It does not need a constructor, a return
tag or a repeated explanation of the whole enum.

~~~php
/**
 * Enum NotificationDeliveryStatus
 *
 * Describes whether a delivery is waiting, completed or permanently failed.
 *
 * @category Enum
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum NotificationDeliveryStatus: string
{
  // #region Cases
  /**
   * Case Pending
   *
   * Delivery has not reached a terminal outcome and may still be attempted.
   *
   * @since 1.0.0
   *
   * @var self
   */
  case Pending = 'pending';

  /**
   * Case Delivered
   *
   * The destination accepted the notification.
   *
   * @since 1.0.0
   *
   * @var self
   */
  case Delivered = 'delivered';

  /**
   * Case Failed
   *
   * Delivery ended without acceptance and will not be retried.
   *
   * @since 1.0.0
   *
   * @var self
   */
  case Failed = 'failed';
  // #endregion

  // #region Methods
  /**
   * Method isTerminal
   *
   * Indicates whether this delivery has reached its final outcome.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool true for delivered or permanently failed notifications
   */
  public function isTerminal(): bool
  {
    return $this !== self::Pending;
  }
  // #endregion
}
~~~

A switch arm is not a declaration. Add a short inline rationale only when its
behavior would otherwise be surprising; do not put declaration docblocks on arms.

## Interface and failure contract

~~~php
/**
 * Interface NotificationTitleResolverPort
 *
 * Resolves display titles without exposing the storage representation to callers.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NotificationTitleResolverPort
{
  // #region Methods
  /**
   * Method resolve
   *
   * Reads the title without creating a notification or changing its delivery state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param non-empty-string $notificationId the identifier of the notification to read
   *
   * @return NotificationTitle|null the stored title, or null when no notification exists
   */
  public function resolve(string $notificationId): ?NotificationTitle;
  // #endregion
}
~~~

An implementation preserves this non-empty-string input contract. Do not replace
its inherited PHPDoc with a broader string, mixed or array annotation that changes
static analysis. Add @throws only for failures that the actual port exposes.

## Generic function and collection types

~~~php
/**
 * Function keepItems
 *
 * Filters a list while preserving its order and reindexing retained items.
 *
 * @access public
 * @since 1.0.0
 *
 * @template TItem
 *
 * @param list<TItem> $items the ordered input collection
 * @param callable(TItem): bool $predicate the test applied to each item
 *
 * @return list<TItem> the retained items in their original order
 *
 * @throws \Throwable when the supplied predicate fails
 */
function keepItems(array $items, callable $predicate): array
{
  return array_values(array_filter($items, $predicate));
}
~~~

Put @template and the matching generic types on the declaration. Do not add
comments inside PHPDoc array shapes or generic arguments.

## Deprecation, internal helpers and exceptions

Keep the normal declaration title, purpose, access, parameters and return groups.
Add these tags only after verifying the corresponding source contract:

~~~php
/**
 * Method legacyTitle
 *
 * Returns the stored title through the historical accessor.
 *
 * @access public
 * @since 1.0.0
 *
 * @return non-empty-string the normalized title
 *
 * @deprecated 1.2.0 Use title() instead.
 *
 * @see self::title()
 */
~~~

For an exception class, explain the failed invariant and use category Exception.
For an internal helper, explain its supported caller boundary before adding
@internal. Neither tag replaces the declaration's actual documentation.

## Final quality check

- Verify purpose against the implementation and caller contract.
- Check parameter names, visibility, types, nullability, units and failure conditions.
- Keep useful historical prose, authors, versions, examples and analysis annotations.
- Use one docblock per declaration, before its attributes.
- Remove duplicate or empty regions; preserve declaration order.
- Separate members with a blank line, except the first in a class or region.
- Run the scoped documentation and style checks, then compare executable PHP tokens.
