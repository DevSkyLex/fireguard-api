<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\{DecimalAmount, Uuid};

/**
 * Class ProcurementLine
 *
 * Separates consumable stock from equipment that must receive individual identities.
 *
 * @category ValueObject
 */
final readonly class ProcurementLine
{
  // #region Properties
  /**
   * Property id
   */
  public string $id;

  /**
   * Property kind
   */
  public string $kind;

  /**
   * Property partId
   */
  public ?string $partId;

  /**
   * Property typeCode
   */
  public ?string $typeCode;

  /**
   * Property identityTemplate
   *
   * @var array<string,mixed> */
  public array $identityTemplate;

  /**
   * Property quantity
   */
  public string $quantity;

  /**
   * Property unitCost
   */
  public ?string $unitCost;

  /**
   * Property receivedQuantity
   */
  public string $receivedQuantity;

  /**
   * Property returnedQuantity
   */
  public string $returnedQuantity;

  /**
   * Property partCode
   */
  public ?string $partCode;

  /**
   * Property partLabel
   */
  public ?string $partLabel;

  /**
   * Property partUnit
   */
  public ?string $partUnit;

  /**
   * Property identity
   */
  private ProcurementGoodsIdentity $identity;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Adopts a validated snapshot only when equipment quantities remain whole units.
   *
   * @access private
   *
   * @param string $id the stable order-line UUID
   * @param ProcurementGoodsIdentity $identity the validated goods snapshot
   * @param ProcurementLineAmounts $amounts the exact retained quantities and price
   *
   * @return void
   */
  private function __construct(string $id, ProcurementGoodsIdentity $identity, ProcurementLineAmounts $amounts)
  {
    Uuid::assertValid($id);
    if ('equipment_to_individualize' === $identity->kind && (!DecimalAmount::fromString($amounts->quantity)->isInteger() || !DecimalAmount::fromString($amounts->receivedQuantity)->isInteger() || !DecimalAmount::fromString($amounts->returnedQuantity)->isInteger())) {
      throw ProcurementException::invalid('Individually tracked equipment quantities must be whole units.');
    }
    $this->id = $id;
    $this->kind = $identity->kind;
    $this->partId = $identity->partId;
    $this->typeCode = $identity->typeCode;
    $this->identityTemplate = $identity->identityTemplate;
    $this->quantity = $amounts->quantity;
    $this->unitCost = $amounts->unitCost;
    $this->receivedQuantity = $amounts->receivedQuantity;
    $this->returnedQuantity = $amounts->returnedQuantity;
    $this->partCode = $identity->partCode;
    $this->partLabel = $identity->partLabel;
    $this->partUnit = $identity->partUnit;
    $this->identity = $identity;
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Creates a line with no reception or return history.
   *
   * @access public
   *
   * @param string $id the stable line UUID
   * @param ProcurementGoodsIdentity $identity the validated goods snapshot
   * @param string $quantity the positive exact ordered quantity
   * @param ?string $unitCost the exact unit amount, or unknown
   *
   * @return self the draft line
   */
  public static function create(string $id, ProcurementGoodsIdentity $identity, string $quantity, ?string $unitCost): self
  {
    return new self($id, $identity, new ProcurementLineAmounts($quantity, $unitCost));
  }

  /**
   * Method reconstitute
   *
   * Restores receipt and return totals with all quantity invariants enforced.
   *
   * @access public
   *
   * @param string $id the stable line UUID
   * @param ProcurementGoodsIdentity $identity the validated goods snapshot
   * @param ProcurementLineAmounts $amounts the exact retained quantities and price
   *
   * @return self the validated line snapshot
   */
  public static function reconstitute(string $id, ProcurementGoodsIdentity $identity, ProcurementLineAmounts $amounts): self
  {
    return new self($id, $identity, $amounts);
  }

  /**
   * Method receive
   *
   * Returns a new snapshot only after validating the full requested reception.
   *
   * @access public
   *
   * @param string $quantity the positive exact quantity to receive
   *
   * @return self the updated line
   */
  public function receive(string $quantity): self
  {
    $received = DecimalAmount::fromString($quantity);
    if ($received->isNegative() || $received->isZero() || $received->compareTo(DecimalAmount::fromString($this->remainingQuantity())) > 0) {
      throw ProcurementException::invalid('A receipt quantity must be positive and cannot exceed the remaining delivery.');
    }

    return new self($this->id, $this->identity, new ProcurementLineAmounts($this->quantity, $this->unitCost, DecimalAmount::fromString($this->receivedQuantity)->add($received)->toString(), $this->returnedQuantity));
  }

  /**
   * Method returnReceived
   *
   * Keeps gross receipts intact while bounding cumulative supplier returns.
   *
   * @access public
   *
   * @param string $quantity the positive exact quantity to return
   *
   * @return self the updated line
   */
  public function returnReceived(string $quantity): self
  {
    $returned = DecimalAmount::fromString($quantity);
    if ($returned->isNegative() || $returned->isZero() || $returned->compareTo(DecimalAmount::fromString($this->returnableQuantity())) > 0) {
      throw ProcurementException::invalid('A return quantity must be positive and cannot exceed received units not already returned.');
    }

    return new self($this->id, $this->identity, new ProcurementLineAmounts($this->quantity, $this->unitCost, $this->receivedQuantity, DecimalAmount::fromString($this->returnedQuantity)->add($returned)->toString()));
  }

  /**
   * Method remainingQuantity
   *
   * @access public
   *
   * @return string the exact gross quantity still awaiting delivery
   */
  public function remainingQuantity(): string
  {
    return DecimalAmount::fromString($this->quantity)->subtract(DecimalAmount::fromString($this->receivedQuantity))->toString();
  }

  /**
   * Method returnableQuantity
   *
   * @access public
   *
   * @return string the exact received quantity not previously returned
   */
  public function returnableQuantity(): string
  {
    return DecimalAmount::fromString($this->receivedQuantity)->subtract(DecimalAmount::fromString($this->returnedQuantity))->toString();
  }
  // #endregion
}
