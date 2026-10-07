<?php

declare(strict_types=1);

namespace Procurement\Domain\Model;

use DateTimeImmutable;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\ValueObject\{ProcurementLine, PurchaseOrderStatus};
use Shared\Domain\ValueObject\{DecimalAmount, Uuid};

use function array_is_list;
use function array_values;
use function count;
use function in_array;
use function mb_strlen;
use function preg_match;
use function trim;

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
   * Property lines
   *
   * @var list<ProcurementLine>
   */
  private array $lines;
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
   * @param string $supplierId the supplier UUID
   * @param string $currency the uppercase organization currency
   * @param string $name the order's display name
   * @param list<ProcurementLine> $lines the immutable line snapshots
   * @param PurchaseOrderStatus $status the gross reception lifecycle
   * @param int $revision the positive resource revision
   * @param DateTimeImmutable $createdAt the creation instant
   * @param DateTimeImmutable $updatedAt the latest update instant
   *
   * @return void
   */
  private function __construct(
    public readonly string $id,
    public readonly string $organizationId,
    private string $supplierId,
    private string $currency,
    private string $name,
    array $lines,
    private PurchaseOrderStatus $status,
    private int $revision,
    public readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
    new Uuid($id);
    new Uuid($organizationId);
    new Uuid($supplierId);
    if ($revision < 1 || $updatedAt < $createdAt) {
      throw ProcurementException::invalid('Purchase-order revision and historical timestamps are inconsistent.');
    }

    $this->currency = self::normalizeCurrency($currency);
    $this->name = self::normalizeName($name);
    $this->lines = self::validateLines($lines);
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
    return new self($id, $organizationId, $supplierId, $currency, $name, $lines, PurchaseOrderStatus::DRAFT, 1, $now, $now);
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
   * @param string $supplierId the supplier UUID
   * @param string $currency the organization currency
   * @param string $name the display name
   * @param list<ProcurementLine> $lines the persisted line snapshots
   * @param PurchaseOrderStatus $status the persisted lifecycle
   * @param int $revision the persisted resource revision
   * @param DateTimeImmutable $createdAt the creation instant
   * @param DateTimeImmutable $updatedAt the latest update instant
   *
   * @return self the restored order
   */
  public static function reconstitute(string $id, string $organizationId, string $supplierId, string $currency, string $name, array $lines, PurchaseOrderStatus $status, int $revision, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): self
  {
    return new self($id, $organizationId, $supplierId, $currency, $name, $lines, $status, $revision, $createdAt, $updatedAt);
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

    new Uuid($supplierId);
    $currency = self::normalizeCurrency($currency);
    $name = self::normalizeName($name);
    $lines = self::validateLines($lines);
    foreach ($lines as $line) {
      if ('0.000000' !== $line->receivedQuantity || '0.000000' !== $line->returnedQuantity) {
        throw ProcurementException::invalid('Draft lines cannot contain receipt or return history.');
      }
    }

    $this->supplierId = $supplierId;
    $this->currency = $currency;
    $this->name = $name;
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
    if (PurchaseOrderStatus::DRAFT !== $this->status || [] === $this->lines) {
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

    $index = $this->lineIndex($lineId);
    $line = $this->lines[$index]->receive($quantity);
    $lines = $this->lines;
    $lines[$index] = $line;
    $this->lines = array_values($lines);
    $this->status = $this->receivedStatus();
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

    $index = $this->lineIndex($lineId);
    $line = $this->lines[$index]->returnReceived($quantity);
    $lines = $this->lines;
    $lines[$index] = $line;
    $this->lines = array_values($lines);
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
    return $this->supplierId;
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
    return $this->currency;
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
    return $this->name;
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
    return $this->lines;
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
   * Method lineIndex
   *
   * @access private
   *
   * @param string $lineId the retained line UUID
   *
   * @return int its current list index
   */
  private function lineIndex(string $lineId): int
  {
    foreach ($this->lines as $index => $line) {
      if ($line->id === $lineId) {
        return $index;
      }
    }

    throw ProcurementException::invalid('The order does not contain this line.');
  }

  /**
   * Method receivedStatus
   *
   * @access private
   *
   * @return PurchaseOrderStatus the lifecycle derived from gross receipts
   */
  private function receivedStatus(): PurchaseOrderStatus
  {
    $received = DecimalAmount::zero();
    $remaining = DecimalAmount::zero();
    foreach ($this->lines as $line) {
      $received = $received->add(DecimalAmount::fromString($line->receivedQuantity));
      $remaining = $remaining->add(DecimalAmount::fromString($line->remainingQuantity()));
    }

    if ($received->isZero()) {
      return PurchaseOrderStatus::ORDERED;
    }

    return $remaining->isZero() ? PurchaseOrderStatus::RECEIVED : PurchaseOrderStatus::PARTIAL_RECEIVED;
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
      foreach ($this->lines as $line) {
        if ('0.000000' !== $line->receivedQuantity || '0.000000' !== $line->returnedQuantity) {
          throw ProcurementException::invalid('Draft lines cannot contain receipt or return history.');
        }
      }

      return;
    }
    if (PurchaseOrderStatus::CANCELLED === $this->status) {
      return;
    }
    if ([] === $this->lines || $this->receivedStatus() !== $this->status) {
      throw ProcurementException::invalid('Purchase-order status must agree with retained receipt quantities.');
    }
  }

  /**
   * Method validateLines
   *
   * @access private
   *
   * @param array<array-key, mixed> $lines the raw candidate line snapshots
   *
   * @return list<ProcurementLine> the bounded list with unique identities
   */
  private static function validateLines(array $lines): array
  {
    if (!array_is_list($lines) || count($lines) > 500) {
      throw ProcurementException::invalid('A purchase order needs a list of at most 500 lines.');
    }

    $identities = [];
    $validated = [];
    foreach ($lines as $line) {
      if (!$line instanceof ProcurementLine || isset($identities[$line->id])) {
        throw ProcurementException::invalid('Purchase-order lines must have unique validated identities.');
      }
      $identities[$line->id] = true;
      $validated[] = $line;
    }

    return $validated;
  }

  /**
   * Method normalizeCurrency
   *
   * @access private
   *
   * @param string $currency the declared organization currency
   *
   * @return string the validated currency
   */
  private static function normalizeCurrency(string $currency): string
  {
    if (1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
      throw ProcurementException::invalid('A purchase-order currency must contain three uppercase letters.');
    }

    return $currency;
  }

  /**
   * Method normalizeName
   *
   * @access private
   *
   * @param string $name the raw display name
   *
   * @return string the normalized display name
   */
  private static function normalizeName(string $name): string
  {
    $name = trim($name);
    if ('' === $name || mb_strlen($name) > 160) {
      throw ProcurementException::invalid('A purchase-order name must contain 1 to 160 characters.');
    }

    return $name;
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
