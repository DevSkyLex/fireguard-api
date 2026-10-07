<?php

declare(strict_types=1);

namespace Customer\Domain\Model\Customer;

use Customer\Domain\Exception\CustomerException;
use DateTimeImmutable;
use Shared\Domain\ValueObject\Uuid;

use function array_is_list;
use function array_key_exists;
use function count;
use function filter_var;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/** Class Customer. Organization-owned internal customer with retained archival history. @category Model */
final readonly class Customer
{
  /**
   * @param list<array{name:string,email:?string,phone:?string,role:?string}> $contacts
   */
  private function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public ?string $code,
    public ?string $email,
    public ?string $phone,
    public array $contacts,
    public ?DateTimeImmutable $archivedAt,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public int $revision,
  ) {
  }

  /**
   * @param array<mixed> $contacts
   */
  public static function create(string $id, string $organizationId, string $name, ?string $code, ?string $email, ?string $phone, array $contacts, DateTimeImmutable $now): self
  {
    new Uuid($id);
    new Uuid($organizationId);

    return new self($id, $organizationId, self::name($name), self::text($code, 80), self::email($email), self::text($phone, 40), self::contacts($contacts), null, $now, $now, 1);
  }

  /**
   * @param list<array{name:string,email:?string,phone:?string,role:?string}> $contacts
   */
  public static function reconstitute(string $id, string $organizationId, string $name, ?string $code, ?string $email, ?string $phone, array $contacts, ?DateTimeImmutable $archivedAt, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, int $revision): self
  {
    return new self($id, $organizationId, $name, $code, $email, $phone, $contacts, $archivedAt, $createdAt, $updatedAt, $revision);
  }

  /**
   * @param array<string,mixed> $changes
   */
  public function change(array $changes, DateTimeImmutable $now): self
  {
    $allowed = ['name', 'code', 'email', 'phone', 'contacts'];
    foreach ($changes as $key => $value) {
      if (!in_array($key, $allowed, true)) {
        throw CustomerException::invalid('Unknown customer field.');
      }
      if ('contacts' === $key) {
        if (!is_array($value)) {
          throw CustomerException::invalid('Contacts must be a list.');
        }
      } elseif ('name' === $key && !is_string($value)) {
        throw CustomerException::invalid('Customer name is required.');
      } elseif (null !== $value && !is_string($value)) {
        throw CustomerException::invalid('Customer fields must be strings or null.');
      }
    }
    /** @var string $name */ $name = $changes['name'] ?? $this->name;
    /** @var ?string $code */ $code = array_key_exists('code', $changes) ? $changes['code'] : $this->code;
    /** @var ?string $email */ $email = array_key_exists('email', $changes) ? $changes['email'] : $this->email;
    /** @var ?string $phone */ $phone = array_key_exists('phone', $changes) ? $changes['phone'] : $this->phone;
    /** @var array<mixed> $contacts */ $contacts = $changes['contacts'] ?? $this->contacts;

    return new self($this->id, $this->organizationId, self::name($name), self::text($code, 80), self::email($email), self::text($phone, 40), self::contacts($contacts), $this->archivedAt, $this->createdAt, $now, $this->revision + 1);
  }

  public function archive(DateTimeImmutable $now): self
  {
    if (null !== $this->archivedAt) {
      return $this;
    }

    return new self($this->id, $this->organizationId, $this->name, $this->code, $this->email, $this->phone, $this->contacts, $now, $this->createdAt, $now, $this->revision + 1);
  }

  public function restore(DateTimeImmutable $now): self
  {
    if (null === $this->archivedAt) {
      return $this;
    }

    return new self($this->id, $this->organizationId, $this->name, $this->code, $this->email, $this->phone, $this->contacts, null, $this->createdAt, $now, $this->revision + 1);
  }

  private static function name(string $value): string
  {
    $value = trim($value);
    if ('' === $value || mb_strlen($value) > 160) {
      throw CustomerException::invalid('Customer name must contain 1 to 160 characters.');
    }

    return $value;
  }

  private static function text(?string $value, int $max): ?string
  {
    $value = null === $value ? null : trim($value);
    if (null !== $value && mb_strlen($value) > $max) {
      throw CustomerException::invalid('Customer field is too long.');
    }

    return '' === $value ? null : $value;
  }

  private static function email(?string $value): ?string
  {
    $value = self::text($value, 254);
    if (null !== $value && false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
      throw CustomerException::invalid('Invalid customer email.');
    }

    return $value;
  }

  /**
   * @param array<mixed> $contacts
   *
   * @return list<array{name:string,email:?string,phone:?string,role:?string}>
   */
  private static function contacts(array $contacts): array
  {
    if (!array_is_list($contacts) || count($contacts) > 50) {
      throw CustomerException::invalid('Contacts must be a list of at most 50 contacts.');
    }
    $normalized = [];
    foreach ($contacts as $contact) {
      if (!is_array($contact) || !is_string($contact['name'] ?? null)) {
        throw CustomerException::invalid('Each contact requires a name.');
      }
      foreach ($contact as $key => $value) {
        if (!in_array($key, ['name', 'email', 'phone', 'role'], true) || (null !== $value && !is_string($value))) {
          throw CustomerException::invalid('Invalid contact field.');
        }
      }
      /** @var array{name:string,email?:?string,phone?:?string,role?:?string} $contact */
      $normalized[] = ['name' => self::name($contact['name']), 'email' => self::email($contact['email'] ?? null), 'phone' => self::text($contact['phone'] ?? null, 40), 'role' => self::text($contact['role'] ?? null, 80)];
    }

    return $normalized;
  }
}
