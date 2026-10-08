<?php

declare(strict_types=1);

namespace Customer\Domain\Model\Customer;

use Customer\Domain\Exception\CustomerException;
use Customer\Domain\ValueObject\{CustomerDetails, CustomerHistory};
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

/**
 * Class Customer
 *
 * Owns an organization's customer identity, validated details and retained archival history.
 *
 * @category Model
 */
final readonly class Customer
{
  // #region Properties
  /**
   * Property id
   */
  public string $id;

  /**
   * Property organizationId
   */
  public string $organizationId;

  /**
   * Property name
   */
  public string $name;

  /**
   * Property code
   */
  public ?string $code;

  /**
   * Property email
   */
  public ?string $email;

  /**
   * Property phone
   */
  public ?string $phone;

  /**
   * Property contacts
   *
   * @var list<array{name:string,email:?string,phone:?string,role:?string}>
   */
  public array $contacts;

  /**
   * Property archivedAt
   */
  public ?DateTimeImmutable $archivedAt;

  /**
   * Property createdAt
   */
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt
   */
  public DateTimeImmutable $updatedAt;

  /**
   * Property revision
   */
  public int $revision;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Combines customer details and history while preserving the public aggregate state.
   *
   * @access private
   *
   * @param string $id the customer identifier
   * @param string $organizationId the owning organization identifier
   * @param CustomerDetails $details validated or faithfully restored customer details
   * @param CustomerHistory $history the persisted lifecycle state
   *
   * @return void
   */
  private function __construct(string $id, string $organizationId, CustomerDetails $details, CustomerHistory $history)
  {
    $this->id = $id;
    $this->organizationId = $organizationId;
    $this->name = $details->name;
    $this->code = $details->code;
    $this->email = $details->email;
    $this->phone = $details->phone;
    /** @var list<array{name:string,email:?string,phone:?string,role:?string}> $contacts */
    $contacts = $details->contacts;
    $this->contacts = $contacts;
    $this->archivedAt = $history->archivedAt;
    $this->createdAt = $history->createdAt;
    $this->updatedAt = $history->updatedAt;
    $this->revision = $history->revision;
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Validates identifiers before normalizing details and starting the first revision.
   *
   * @access public
   *
   * @param string $id the customer identifier
   * @param string $organizationId the owning organization identifier
   * @param CustomerDetails $details the supplied descriptive fields
   * @param DateTimeImmutable $now the creation time
   *
   * @return self the validated customer
   */
  public static function create(string $id, string $organizationId, CustomerDetails $details, DateTimeImmutable $now): self
  {
    Uuid::assertValid($id);
    Uuid::assertValid($organizationId);

    return new self($id, $organizationId, self::normalize($details), new CustomerHistory(null, $now, $now, 1));
  }

  /**
   * Method reconstitute
   *
   * Restores every persisted field without normalization or creation-time validation.
   * The repository supplies the retained contact shapes and lifecycle revision.
   *
   * @access public
   *
   * @param string $id the persisted customer identifier
   * @param string $organizationId the persisted owner identifier
   * @param CustomerDetails $details the exact persisted descriptive fields
   * @param CustomerHistory $history the exact persisted lifecycle state
   *
   * @return self the retained customer state
   */
  public static function reconstitute(string $id, string $organizationId, CustomerDetails $details, CustomerHistory $history): self
  {
    return new self($id, $organizationId, $details, $history);
  }

  /**
   * Method change
   *
   * Applies supplied fields while retaining omitted values and clearing explicit nulls.
   *
   * @access public
   *
   * @param array<string,mixed> $changes the fields present in the patch
   * @param DateTimeImmutable $now the mutation time
   *
   * @return self the customer at its next revision
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

    $details = self::normalize(new CustomerDetails($name, $code, $email, $phone, $contacts));

    return new self($this->id, $this->organizationId, $details, new CustomerHistory($this->archivedAt, $this->createdAt, $now, $this->revision + 1));
  }

  /**
   * Method archive
   *
   * Retains customer details and makes archival replay idempotent.
   *
   * @access public
   *
   * @param DateTimeImmutable $now the archival time
   *
   * @return self the archived customer or its unchanged retained state
   */
  public function archive(DateTimeImmutable $now): self
  {
    if (null !== $this->archivedAt) {
      return $this;
    }

    return new self($this->id, $this->organizationId, $this->details(), new CustomerHistory($now, $this->createdAt, $now, $this->revision + 1));
  }

  /**
   * Method restore
   *
   * Clears archival state once while retaining creation history and descriptive fields.
   *
   * @access public
   *
   * @param DateTimeImmutable $now the restoration time
   *
   * @return self the active customer or its unchanged retained state
   */
  public function restore(DateTimeImmutable $now): self
  {
    if (null === $this->archivedAt) {
      return $this;
    }

    return new self($this->id, $this->organizationId, $this->details(), new CustomerHistory(null, $this->createdAt, $now, $this->revision + 1));
  }

  /**
   * Method details
   *
   * Groups the retained descriptive fields without revalidating historical state.
   *
   * @access private
   *
   * @return CustomerDetails the current descriptive state
   */
  private function details(): CustomerDetails
  {
    return new CustomerDetails($this->name, $this->code, $this->email, $this->phone, $this->contacts);
  }

  /**
   * Method normalize
   *
   * Enforces creation and edit invariants independently of retained restoration state.
   *
   * @access private
   *
   * @param CustomerDetails $details the supplied descriptive fields
   *
   * @return CustomerDetails the normalized and validated descriptive state
   */
  private static function normalize(CustomerDetails $details): CustomerDetails
  {
    return new CustomerDetails(self::name($details->name), self::text($details->code, 80), self::email($details->email), self::text($details->phone, 40), self::contacts($details->contacts));
  }

  /**
   * Method name
   *
   * Requires a nonempty normalized customer or contact name within the domain limit.
   *
   * @access private
   *
   * @param string $value the supplied name
   *
   * @return string the normalized name
   */
  private static function name(string $value): string
  {
    $value = trim($value);
    if ('' === $value || mb_strlen($value) > 160) {
      throw CustomerException::invalid('Customer name must contain 1 to 160 characters.');
    }

    return $value;
  }

  /**
   * Method text
   *
   * Normalizes optional fields and enforces their character limits.
   *
   * @access private
   *
   * @param string|null $value the nullable supplied field
   * @param int $max the maximum character count
   *
   * @return string|null the normalized field, with empty strings cleared
   */
  private static function text(?string $value, int $max): ?string
  {
    $value = null === $value ? null : trim($value);
    if (null !== $value && mb_strlen($value) > $max) {
      throw CustomerException::invalid('Customer field is too long.');
    }

    return '' === $value ? null : $value;
  }

  /**
   * Method email
   *
   * Normalizes optional email addresses and requires a valid retained format for edits.
   *
   * @access private
   *
   * @param string|null $value the nullable supplied address
   *
   * @return string|null the validated address
   */
  private static function email(?string $value): ?string
  {
    $value = self::text($value, 254);
    if (null !== $value && false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
      throw CustomerException::invalid('Invalid customer email.');
    }

    return $value;
  }

  /**
   * Method contacts
   *
   * Validates contact shapes and normalizes their optional descriptive fields.
   *
   * @access private
   *
   * @param array<mixed> $contacts the supplied contact list
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
  // #endregion
}
