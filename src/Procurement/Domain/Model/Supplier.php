<?php

declare(strict_types=1);

namespace Procurement\Domain\Model;

use DateTimeImmutable;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\ValueObject\{SupplierDetails, SupplierHistory};
use Shared\Domain\ValueObject\Uuid;

/**
 * Class Supplier
 *
 * Owns an internal supplier and its contacts while retaining archival history.
 *
 * @category Model
 */
final class Supplier
{
  /**
   * Property createdAt
   */
  public readonly DateTimeImmutable $createdAt;

  // #region Properties
  /**
   * Property details
   *
   * Holds validated contact data adopted together by each mutation.
   */
  private SupplierDetails $details;

  /**
   * Property updatedAt
   */
  private DateTimeImmutable $updatedAt;

  /**
   * Property archivedAt
   */
  private ?DateTimeImmutable $archivedAt;

  /**
   * Property revision
   */
  private int $revision;
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
   * @param SupplierDetails $details the details value
   * @param SupplierHistory $history the history value
   *
   * @return void
   */
  private function __construct(public readonly string $id, public readonly string $organizationId, SupplierDetails $details, SupplierHistory $history)
  {
    Uuid::assertValid($id);
    Uuid::assertValid($organizationId);
    $this->details = $details;
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
   * Creates an active supplier scoped to one organization.
   *
   * @access public
   *
   * @param string $id the supplier UUID
   * @param string $organizationId the organization UUID
   * @param DateTimeImmutable $now the creation instant
   * @param SupplierDetails $details the details value
   *
   * @return self the active supplier
   */
  public static function create(string $id, string $organizationId, SupplierDetails $details, DateTimeImmutable $now): self
  {
    return new self($id, $organizationId, $details, new SupplierHistory(null, $now, $now, 1));
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
   * @param SupplierDetails $details the details value
   * @param SupplierHistory $history the history value
   *
   * @return self the restored supplier
   */
  public static function reconstitute(string $id, string $organizationId, SupplierDetails $details, SupplierHistory $history): self
  {
    return new self($id, $organizationId, $details, $history);
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

    $this->details = new SupplierDetails($name, $code, $email, $phone, $contacts);
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
    return $this->details->name;
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
    return $this->details->code;
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
    return $this->details->email;
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
    return $this->details->phone;
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
    return $this->details->contacts;
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

  // #endregion
}
