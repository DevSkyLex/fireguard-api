<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Inbox;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Shared\Domain\Exception\InvalidValueException;

use function base64_decode;
use function base64_encode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function rtrim;
use function strlen;
use function strtr;

use const JSON_THROW_ON_ERROR;

/**
 * Class InboxCursor
 *
 * Encodes and compares a stable position in the inbox ordering: instant descending, then source and identifier ascending.
 *
 * @category Contract
 */
final readonly class InboxCursor
{
  // #region Constants
  /**
   * Constant INVALID_CURSOR_MESSAGE
   *
   * Validation message used when an encoded cursor is malformed or inconsistent.
   *
   * @access private
   */
  private const string INVALID_CURSOR_MESSAGE = 'Invalid inbox cursor.';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Creates a cursor from the event instant, source key, and source identifier.
   *
   * @access public
   *
   * @param DateTimeImmutable $occurredAt event instant used for ordering
   * @param string $sourceKey inbox source key used as the first tiebreaker
   * @param string $id item identifier used as the final tiebreaker
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $occurredAt,
    public string $sourceKey,
    public string $id,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method fromItem
   *
   * Creates a cursor from the ordering fields of an inbox item.
   *
   * @access public
   *
   * @param InboxItem $item inbox item to position after
   *
   * @return self cursor carrying the item’s ordering values
   */
  public static function fromItem(InboxItem $item): self
  {
    return new self($item->occurredAt, $item->sourceKey, $item->id);
  }

  /**
   * Method encode
   *
   * Encodes cursor fields as an unpadded URL-safe Base64 JSON token.
   *
   * @access public
   *
   * @return string encoded cursor token
   */
  public function encode(): string
  {
    return rtrim(strtr(base64_encode(json_encode([
      'v' => 1, 'at' => $this->occurredAt->format('Y-m-d\TH:i:s.uP'),
      'source' => $this->sourceKey, 'id' => $this->id,
    ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
  }

  /**
   * Method decode
   *
   * Validates and decodes a URL-safe cursor token into its ordering fields.
   *
   * @access public
   *
   * @param string $value encoded cursor token
   *
   * @return self decoded cursor
   *
   * @throws InvalidValueException when the token fails format or value validation
   */
  public static function decode(string $value): self
  {
    if (strlen($value) > 2048 || 1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
      throw InvalidValueException::because(self::INVALID_CURSOR_MESSAGE);
    }
    $json = base64_decode(strtr($value, '-_', '+/'), true);

    try {
      $data = false === $json ? null : json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      $data = null;
    }
    if (!is_array($data) || 1 !== ($data['v'] ?? null) || !is_string($data['at'] ?? null)
      || !is_string($data['source'] ?? null) || !is_string($data['id'] ?? null)
      || 1 !== preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $data['source'])
      || '' === $data['id'] || strlen($data['id']) > 128
      || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/D', $data['at'])) {
      throw InvalidValueException::because(self::INVALID_CURSOR_MESSAGE);
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $data['at']);
    if (false === $date || $date->format('Y-m-d\TH:i:s.uP') !== $data['at']) {
      throw InvalidValueException::because(self::INVALID_CURSOR_MESSAGE);
    }

    return new self($date, $data['source'], $data['id']);
  }

  /**
   * Method databaseInstant
   *
   * Formats the cursor instant in UTC with microseconds for PostgreSQL timestamp comparisons.
   *
   * @access public
   *
   * @return string UTC timestamp preserving microseconds
   */
  public function databaseInstant(): string
  {
    return $this->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
  }

  /**
   * Method precedes
   *
   * Determines whether an item sorts after this cursor in the inbox’s descending-time order.
   *
   * @access public
   *
   * @param InboxItem $item item to compare with this cursor
   *
   * @return bool whether the item follows the cursor
   */
  public function precedes(InboxItem $item): bool
  {
    return $item->occurredAt < $this->occurredAt
      || (0 === ($item->occurredAt <=> $this->occurredAt) && ($item->sourceKey > $this->sourceKey
        || ($item->sourceKey === $this->sourceKey && $item->id > $this->id)));
  }
  // #endregion
}
