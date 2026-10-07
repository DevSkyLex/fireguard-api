<?php

declare(strict_types=1);

namespace Procurement\Domain\Model;

use DateTimeImmutable;
use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\Uuid;

use function array_is_list;
use function count;
use function filter_var;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * Class Supplier
 *
 * Owns an internal supplier and its contacts while retaining archival history.
 *
 * @category Model
 */
final class Supplier
{
  // #region Properties
  /**
   * Property contacts
   *
   * @var list<array{name:string,email:?string,phone:?string,role:?string}>
   */
  private array $contacts;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Restores validated identity and contact data with an optimistic revision.
   *
   * @access private
   *
   * @param string $id the stable supplier UUID
   * @param string $organizationId the owning organization UUID
   * @param string $name the display name
   * @param ?string $code the organization's external supplier reference
   * @param ?string $email the main supplier email
   * @param ?string $phone the main supplier telephone
   * @param array<mixed> $contacts the declarative contact records
   * @param ?DateTimeImmutable $archivedAt the archival instant
   * @param DateTimeImmutable $createdAt the creation instant
   * @param DateTimeImmutable $updatedAt the latest update instant
   * @param int $revision the positive resource revision
   *
   * @return void
   */
  private function __construct(
    public readonly string $id,
    public readonly string $organizationId,
    private string $name,
    private ?string $code,
    private ?string $email,
    private ?string $phone,
    array $contacts,
    private ?DateTimeImmutable $archivedAt,
    public readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private int $revision,
  ) {
    new Uuid($id);
    new Uuid($organizationId);
    if ($revision < 1 || $updatedAt < $createdAt || (null !== $archivedAt && ($archivedAt < $createdAt || $archivedAt > $updatedAt))) {
      throw ProcurementException::invalid('Supplier revision and historical timestamps are inconsistent.');
    }

    $this->name = self::normalizeName($name);
    $this->code = self::normalizeText($code, 80);
    $this->email = self::normalizeEmail($email);
    $this->phone = self::normalizeText($phone, 40);
    $this->contacts = self::normalizeContacts($contacts);
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Creates an active supplier scoped to one organization.
   *
   * @access public
   *
   * @param string $id the supplier UUID
   * @param string $organizationId the organization UUID
   * @param string $name the supplier name
   * @param ?string $code the external supplier reference
   * @param ?string $email the main email
   * @param ?string $phone the main telephone
   * @param array<mixed> $contacts the supplier's contacts
   * @param DateTimeImmutable $now the creation instant
   *
   * @return self the active supplier
   */
  public static function create(string $id, string $organizationId, string $name, ?string $code, ?string $email, ?string $phone, array $contacts, DateTimeImmutable $now): self
  {
    return new self($id, $organizationId, $name, $code, $email, $phone, $contacts, null, $now, $now, 1);
  }

  /**
   * Method reconstitute
   *
   * Restores a supplier without bypassing contact or lifecycle invariants.
   *
   * @access public
   *
   * @param string $id the supplier UUID
   * @param string $organizationId the organization UUID
   * @param string $name the supplier name
   * @param ?string $code the external supplier reference
   * @param ?string $email the main email
   * @param ?string $phone the main telephone
   * @param array<mixed> $contacts the supplier's contacts
   * @param ?DateTimeImmutable $archivedAt the archival instant
   * @param DateTimeImmutable $createdAt the creation instant
   * @param DateTimeImmutable $updatedAt the latest update instant
   * @param int $revision the positive resource revision
   *
   * @return self the restored supplier
   */
  public static function reconstitute(string $id, string $organizationId, string $name, ?string $code, ?string $email, ?string $phone, array $contacts, ?DateTimeImmutable $archivedAt, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, int $revision): self
  {
    return new self($id, $organizationId, $name, $code, $email, $phone, $contacts, $archivedAt, $createdAt, $updatedAt, $revision);
  }

  /**
   * Method change
   *
   * Validates every field before changing active supplier state.
   *
   * @access public
   *
   * @param int $expectedRevision the last reviewed resource revision
   * @param string $name the replacement display name
   * @param ?string $code the replacement external reference
   * @param ?string $email the replacement main email
   * @param ?string $phone the replacement main telephone
   * @param array<mixed> $contacts the replacement contacts
   * @param DateTimeImmutable $now the update instant
   *
   * @return void
   */
  public function change(int $expectedRevision, string $name, ?string $code, ?string $email, ?string $phone, array $contacts, DateTimeImmutable $now): void
  {
    $this->assertRevision($expectedRevision);
    $this->assertTime($now);
    if (!$this->isActive()) {
      throw ProcurementException::conflict('An archived supplier cannot be changed.');
    }

    $name = self::normalizeName($name);
    $code = self::normalizeText($code, 80);
    $email = self::normalizeEmail($email);
    $phone = self::normalizeText($phone, 40);
    $contacts = self::normalizeContacts($contacts);

    $this->name = $name;
    $this->code = $code;
    $this->email = $email;
    $this->phone = $phone;
    $this->contacts = $contacts;
    $this->touch($now);
  }

  /**
   * Method archive
   *
   * Stops future supplier edits without deleting its purchasing history.
   *
   * @access public
   *
   * @param int $expectedRevision the last reviewed revision
   * @param DateTimeImmutable $now the archival instant
   *
   * @return void
   */
  public function archive(int $expectedRevision, DateTimeImmutable $now): void
  {
    $this->assertRevision($expectedRevision);
    $this->assertTime($now);
    if (null !== $this->archivedAt) {
      return;
    }

    $this->archivedAt = $now;
    $this->touch($now);
  }

  /**
   * Method name
   *
   * @access public
   *
   * @return string the display name
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Method code
   *
   * @access public
   *
   * @return ?string the external supplier reference
   */
  public function code(): ?string
  {
    return $this->code;
  }

  /**
   * Method email
   *
   * @access public
   *
   * @return ?string the main supplier email
   */
  public function email(): ?string
  {
    return $this->email;
  }

  /**
   * Method phone
   *
   * @access public
   *
   * @return ?string the main supplier telephone
   */
  public function phone(): ?string
  {
    return $this->phone;
  }

  /**
   * Method contacts
   *
   * @access public
   *
   * @return list<array{name:string,email:?string,phone:?string,role:?string}> the normalized contacts
   */
  public function contacts(): array
  {
    return $this->contacts;
  }

  /**
   * Method archivedAt
   *
   * @access public
   *
   * @return ?DateTimeImmutable the archival instant
   */
  public function archivedAt(): ?DateTimeImmutable
  {
    return $this->archivedAt;
  }

  /**
   * Method isActive
   *
   * @access public
   *
   * @return bool whether new purchasing activity can use the supplier
   */
  public function isActive(): bool
  {
    return null === $this->archivedAt;
  }

  /**
   * Method revision
   *
   * @access public
   *
   * @return int the current resource revision
   */
  public function revision(): int
  {
    return $this->revision;
  }

  /**
   * Method updatedAt
   *
   * @access public
   *
   * @return DateTimeImmutable the latest mutation instant
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method assertRevision
   *
   * @access private
   *
   * @param int $expectedRevision the reviewed revision
   *
   * @return void
   */
  private function assertRevision(int $expectedRevision): void
  {
    if ($expectedRevision !== $this->revision) {
      throw ProcurementException::stale();
    }
  }

  /**
   * Method assertTime
   *
   * @access private
   *
   * @param DateTimeImmutable $now the mutation instant
   *
   * @return void
   */
  private function assertTime(DateTimeImmutable $now): void
  {
    if ($now < $this->updatedAt) {
      throw ProcurementException::invalid('A supplier update cannot precede its previous update.');
    }
  }

  /**
   * Method touch
   *
   * @access private
   *
   * @param DateTimeImmutable $now the validated mutation instant
   *
   * @return void
   */
  private function touch(DateTimeImmutable $now): void
  {
    $this->updatedAt = $now;
    ++$this->revision;
  }

  /**
   * Method normalizeName
   *
   * @access private
   *
   * @param string $value the raw display name
   *
   * @return string the required normalized name
   */
  private static function normalizeName(string $value): string
  {
    $value = trim($value);
    if ('' === $value || mb_strlen($value) > 160) {
      throw ProcurementException::invalid('Supplier and contact names must contain 1 to 160 characters.');
    }

    return $value;
  }

  /**
   * Method normalizeText
   *
   * @access private
   *
   * @param ?string $value the nullable raw text
   * @param int $maximum the maximum character count
   *
   * @return ?string the normalized text
   */
  private static function normalizeText(?string $value, int $maximum): ?string
  {
    $value = null === $value ? null : trim($value);
    if (null !== $value && mb_strlen($value) > $maximum) {
      throw ProcurementException::invalid('Supplier text exceeds its maximum length.');
    }

    return '' === $value ? null : $value;
  }

  /**
   * Method normalizeEmail
   *
   * @access private
   *
   * @param ?string $value the nullable email
   *
   * @return ?string the normalized email
   */
  private static function normalizeEmail(?string $value): ?string
  {
    $value = self::normalizeText($value, 254);
    if (null !== $value && false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
      throw ProcurementException::invalid('Invalid supplier email.');
    }

    return $value;
  }

  /**
   * Method normalizeContacts
   *
   * @access private
   *
   * @param array<mixed> $contacts the raw contact list
   *
   * @return list<array{name:string,email:?string,phone:?string,role:?string}> the validated contacts
   */
  private static function normalizeContacts(array $contacts): array
  {
    if (!array_is_list($contacts) || count($contacts) > 50) {
      throw ProcurementException::invalid('Supplier contacts must be a list of at most 50 contacts.');
    }

    $normalized = [];
    foreach ($contacts as $contact) {
      if (!is_array($contact) || !is_string($contact['name'] ?? null)) {
        throw ProcurementException::invalid('Each supplier contact requires a name.');
      }
      foreach ($contact as $key => $value) {
        if (!in_array($key, ['name', 'email', 'phone', 'role'], true) || (null !== $value && !is_string($value))) {
          throw ProcurementException::invalid('Invalid supplier contact field.');
        }
      }
      /** @var array{name:string,email?:?string,phone?:?string,role?:?string} $contact */
      $normalized[] = [
        'name' => self::normalizeName($contact['name']),
        'email' => self::normalizeEmail($contact['email'] ?? null),
        'phone' => self::normalizeText($contact['phone'] ?? null, 40),
        'role' => self::normalizeText($contact['role'] ?? null, 80),
      ];
    }

    return $normalized;
  }
  // #endregion
}
