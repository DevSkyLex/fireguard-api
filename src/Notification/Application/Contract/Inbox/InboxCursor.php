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

/** Position in the total ordering: instant descending, source and identifier ascending. */
final readonly class InboxCursor
{
  // #region Constants
  private const string INVALID_CURSOR_MESSAGE = 'Invalid inbox cursor.';
  // #endregion

  public function __construct(
    public DateTimeImmutable $occurredAt,
    public string $sourceKey,
    public string $id,
  ) {
  }

  public static function fromItem(InboxItem $item): self
  {
    return new self($item->occurredAt, $item->sourceKey, $item->id);
  }

  public function encode(): string
  {
    return rtrim(strtr(base64_encode(json_encode([
      'v' => 1, 'at' => $this->occurredAt->format('Y-m-d\TH:i:s.uP'),
      'source' => $this->sourceKey, 'id' => $this->id,
    ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
  }

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
   * Keeps all microseconds when binding PostgreSQL timestamp comparisons.
   */
  public function databaseInstant(): string
  {
    return $this->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
  }

  public function precedes(InboxItem $item): bool
  {
    return $item->occurredAt < $this->occurredAt
      || (0 === ($item->occurredAt <=> $this->occurredAt) && ($item->sourceKey > $this->sourceKey
        || ($item->sourceKey === $this->sourceKey && $item->id > $this->id)));
  }
}
