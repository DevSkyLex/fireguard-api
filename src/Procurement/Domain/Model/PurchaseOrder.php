<?php

declare(strict_types=1);

namespace Procurement\Domain\Model;

use DateTimeImmutable;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\ValueObject\{ProcurementLine, PurchaseOrderHistory, PurchaseOrderIdentity, PurchaseOrderLines, PurchaseOrderStatus};
use Shared\Domain\ValueObject\Uuid;

use function in_array;

/**
 * Class PurchaseOrder
 *
 * Owns ordered, received and returned quantities without rewriting physical history.
 *
 * @category Model
 */
final class PurchaseOrder
{
  // #region Properties
  /**
   * Constant ZERO_QUANTITY
   *
   * Uses the exact canonical quantity spelling retained in line snapshots.
   */
  private const string ZERO_QUANTITY = '0.000000';

  /**
   * Property createdAt
   */
  public readonly DateTimeImmutable $createdAt;

  /**
   * Property lines
   *
   * Holds immutable validated snapshots of the retained line quantities.
   */
  private PurchaseOrderLines $lines;

  /**
   * Property identity
   *
   * Holds draft values adopted together after validation.
   */
  private PurchaseOrderIdentity $identity;

  /**
   * Property updatedAt
   */
  private DateTimeImmutable $updatedAt;

  /**
   * Property status
   */
  private PurchaseOrderStatus $status;

  /**
   * Property revision
   */
  private int $revision;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Restores an order only when its quantities agree with its lifecycle.
   *
   * @access private
   *
   * @param string $id the stable order UUID
   * @param string $organizationId the owning organization UUID
   * @param PurchaseOrderLines $lines the immutable line snapshots
   * @param PurchaseOrderIdentity $identity the identity value
   * @param PurchaseOrderHistory $history the history value
   *
   * @return void
   */
  private function __construct(public readonly string $id, public readonly string $organizationId, PurchaseOrderIdentity $identity, PurchaseOrderLines $lines, PurchaseOrderHistory $history)
  {
    Uuid::assertValid($id);
    Uuid::assertValid($organizationId);
    $this->identity = $identity;
    $this->lines = $lines;
    $this->status = $history->status;
    $this->revision = $history->revision;
    $this->createdAt = $history->createdAt;
    $this->updatedAt = $history->updatedAt;
    $this->assertRestoredStatus();
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Creates a draft which can be completed before it is ordered.
   *
   * @access public
   *
   * @param string $id the order UUID
   * @param string $organizationId the owning organization UUID
   * @param string $supplierId the supplier UUID
   * @param string $currency the organization currency
   * @param string $name the display name
   * @param list<ProcurementLine> $lines the draft lines
   * @param DateTimeImmutable $now the creation instant
   *
   * @return self the draft order
   */
  public static function create(string $id, string $organizationId, string $supplierId, string $currency, string $name, array $lines, DateTimeImmutable $now): self
  {
    return new self($id, $organizationId, new PurchaseOrderIdentity($supplierId, $currency, $name), new PurchaseOrderLines($lines), new PurchaseOrderHistory(PurchaseOrderStatus::DRAFT, 1, $now, $now));
  }

  /**
   * Method reconstitute
   *
   * Restores retained reception and return history without changing identities.
   *
   * @access public
   *
   * @param string $id the order UUID
   * @param string $organizationId the owning organization UUID
   * @param PurchaseOrderLines $lines the persisted line snapshots
   * @param PurchaseOrderIdentity $identity the identity value
   * @param PurchaseOrderHistory $history the history value
   *
   * @return self the restored order
   */
  public static function reconstitute(string $id, string $organizationId, PurchaseOrderIdentity $identity, PurchaseOrderLines $lines, PurchaseOrderHistory $history): self
  {
    return new self($id, $organizationId, $identity, $lines, $history);
  }

  /**
   * Method changeDraft
   *
   * Validates all replacement values before updating an editable draft.
   *
   * @access public
   *
   * @param int $expectedRevision the reviewed resource revision
   * @param string $supplierId the replacement supplier UUID
   * @param string $currency the organization currency
   * @param string $name the replacement display name
   * @param list<ProcurementLine> $lines the replacement draft lines
   * @param DateTimeImmutable $now the update instant
   *
   * @return void
   */
  public function changeDraft(int $expectedRevision, string $supplierId, string $currency, string $name, array $lines, DateTimeImmutable $now): void
  {
    $this->assertRevision($expectedRevision);
    $this->assertTime($now);
    if (PurchaseOrderStatus::DRAFT !== $this->status) {
      throw ProcurementException::conflict('Only a draft purchase order can be changed.');
    }

    $identity = new PurchaseOrderIdentity($supplierId, $currency, $name);
    $lines = new PurchaseOrderLines($lines);
    foreach ($lines->values() as $line) {
      if (self::ZERO_QUANTITY !== $line->receivedQuantity || self::ZERO_QUANTITY !== $line->returnedQuantity) {
        throw ProcurementException::invalid('Draft lines cannot contain receipt or return history.');
      }
    }

    $this->identity = $identity;
    $this->lines = $lines;
    $this->touch($now);
  }

  /**
   * Method order
   *
   * Freezes commercial line identity before the first physical reception.
   *
   * @access public
   *
   * @param int $expectedRevision the reviewed resource revision
   * @param DateTimeImmutable $now the order instant
   *
   * @return void
   */
  public function order(int $expectedRevision, DateTimeImmutable $now): void
  {
    $this->assertRevision($expectedRevision);
    $this->assertTime($now);
    if (PurchaseOrderStatus::DRAFT !== $this->status || [] === $this->lines->values()) {
      throw ProcurementException::conflict('Ordering requires a nonempty draft.');
    }

    $this->status = PurchaseOrderStatus::ORDERED;
    $this->touch($now);
  }

  /**
   * Method cancelRemaining
   *
   * Stops outstanding delivery while keeping received and returned quantities.
   *
   * @access public
   *
   * @param int $expectedRevision the reviewed resource revision
   * @param DateTimeImmutable $now the cancellation instant
   *
   * @return void
   */
  public function cancelRemaining(int $expectedRevision, DateTimeImmutable $now): void
  {
    $this->assertRevision($expectedRevision);
    $this->assertTime($now);
    if (PurchaseOrderStatus::CANCELLED === $this->status) {
      return;
    }
    if (PurchaseOrderStatus::RECEIVED === $this->status) {
      throw ProcurementException::conflict('A fully received order has no remaining delivery to cancel.');
    }

    $this->status = PurchaseOrderStatus::CANCELLED;
    $this->touch($now);
  }

  /**
   * Method recordReceipt
   *
   * Adds a bounded quantity to one line; handlers atomically save the associated
   * stock movement or individual equipment creations and an idempotency receipt.
   *
   * @access public
   *
   * @param int $expectedRevision the reviewed resource revision
   * @param string $lineId the ordered line UUID
   * @param string $quantity the positive exact received quantity
   * @param DateTimeImmutable $now the reception instant
   *
   * @return void
   */
  public function recordReceipt(int $expectedRevision, string $lineId, string $quantity, DateTimeImmutable $now): void
  {
    $this->assertRevision($expectedRevision);
    $this->assertTime($now);
    if (!$this->status->allowsReceipt()) {
      throw ProcurementException::conflict('This order cannot receive new quantities.');
    }

    $this->lines = $this->lines->receive($lineId, $quantity);
    $this->status = $this->lines->receivedStatus();
    $this->touch($now);
  }

  /**
   * Method recordReturn
   *
   * Returns only previously received units. A return never reopens delivery or
   * erases the gross reception history.
   *
   * @access public
   *
   * @param int $expectedRevision the reviewed resource revision
   * @param string $lineId the line UUID
   * @param string $quantity the positive exact returned quantity
   * @param DateTimeImmutable $now the supplier-return instant
   *
   * @return void
   */
  public function recordReturn(int $expectedRevision, string $lineId, string $quantity, DateTimeImmutable $now): void
  {
    $this->assertRevision($expectedRevision);
    $this->assertTime($now);
    if (!in_array($this->status, [PurchaseOrderStatus::PARTIAL_RECEIVED, PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::CANCELLED], true)) {
      throw ProcurementException::conflict('An order must retain received goods before recording a return.');
    }

    $this->lines = $this->lines->returnReceived($lineId, $quantity);
    $this->touch($now);
  }

  /**
   * Method supplierId
   *
   * @access public
   *
   * @return string the supplier UUID
   */
  public function supplierId(): string
  {
    return $this->identity->supplierId;
  }

  /**
   * Method currency
   *
   * @access public
   *
   * @return string the uppercase currency
   */
  public function currency(): string
  {
    return $this->identity->currency;
  }

  /**
   * Method name
   *
   * @access public
   *
   * @return string the order display name
   */
  public function name(): string
  {
    return $this->identity->name;
  }

  /**
   * Method lines
   *
   * @access public
   *
   * @return list<ProcurementLine> the immutable line snapshots
   */
  public function lines(): array
  {
    return $this->lines->values();
  }

  /**
   * Method status
   *
   * @access public
   *
   * @return PurchaseOrderStatus the current reception lifecycle
   */
  public function status(): PurchaseOrderStatus
  {
    return $this->status;
  }

  /**
   * Method revision
   *
   * @access public
   *
   * @return int the resource revision
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
   * Method cancelledRemaining
   *
   * @access public
   *
   * @return bool whether further deliveries have been explicitly cancelled
   */
  public function cancelledRemaining(): bool
  {
    return PurchaseOrderStatus::CANCELLED === $this->status;
  }

  /**
   * Method assertRestoredStatus
   *
   * @access private
   *
   * @return void
   */
  private function assertRestoredStatus(): void
  {
    if (PurchaseOrderStatus::DRAFT === $this->status) {
      foreach ($this->lines->values() as $line) {
        if (self::ZERO_QUANTITY !== $line->receivedQuantity || self::ZERO_QUANTITY !== $line->returnedQuantity) {
          throw ProcurementException::invalid('Draft lines cannot contain receipt or return history.');
        }
      }

      return;
    }
    if (PurchaseOrderStatus::CANCELLED === $this->status) {
      return;
    }
    if ([] === $this->lines->values() || $this->lines->receivedStatus() !== $this->status) {
      throw ProcurementException::invalid('Purchase-order status must agree with retained receipt quantities.');
    }
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
      throw ProcurementException::invalid('A purchase-order update cannot precede its previous update.');
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
