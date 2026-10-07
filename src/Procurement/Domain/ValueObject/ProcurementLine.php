<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\{DecimalAmount, Uuid};

use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function mb_strlen;
use function trim;

/**
 * Class ProcurementLine
 *
 * Separates consumable stock from equipment that must receive individual identities.
 *
 * @category ValueObject
 */
final readonly class ProcurementLine
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Validates quantities and line identity without mutating physical history.
   *
   * @access private
   *
   * @param string $id the stable order-line UUID
   * @param string $kind part or equipment_to_individualize
   * @param ?string $partId the article UUID for a consumable line
   * @param ?string $typeCode the declared type for individually tracked equipment
   * @param array<string, mixed> $identityTemplate the bounded declarative identity template
   * @param string $quantity the exact ordered quantity with six fractional digits
   * @param ?string $unitCost the exact nonnegative unit amount with six fractional digits, or unknown
   * @param string $receivedQuantity the exact retained gross received quantity
   * @param string $returnedQuantity the exact retained quantity returned to the supplier
   *
   * @return void
   */
  private function __construct(
    public string $id,
    public string $kind,
    public ?string $partId,
    public ?string $typeCode,
    public array $identityTemplate,
    public string $quantity,
    public ?string $unitCost,
    public string $receivedQuantity,
    public string $returnedQuantity,
    public ?string $partCode = null,
    public ?string $partLabel = null,
    public ?string $partUnit = null,
  ) {
    new Uuid($id);
    if (!in_array($kind, ['part', 'equipment_to_individualize'], true)) {
      throw ProcurementException::invalid('Unknown procurement line kind.');
    }
    $ordered = DecimalAmount::fromString($quantity);
    $received = DecimalAmount::fromString($receivedQuantity);
    $returned = DecimalAmount::fromString($returnedQuantity);
    if ($ordered->isNegative() || $ordered->isZero() || $ordered->compareTo(DecimalAmount::fromInt(100000)) > 0 || $received->isNegative() || $received->compareTo($ordered) > 0 || $returned->isNegative() || $returned->compareTo($received) > 0) {
      throw ProcurementException::invalid('An ordered line needs a positive quantity up to 100000 and bounded receipt and return quantities.');
    }

    if ('part' === $kind) {
      if (null === $partId || null !== $typeCode || [] !== $identityTemplate) {
        throw ProcurementException::invalid('A part line needs only an article identity.');
      }
      new Uuid($partId);
    } elseif (null !== $partId || null === $typeCode || '' === $typeCode || mb_strlen($typeCode) > 32) {
      throw ProcurementException::invalid('An equipment line needs a type code and cannot also refer to a consumable article.');
    }
    if ('equipment_to_individualize' === $kind && (!$ordered->isInteger() || !$received->isInteger() || !$returned->isInteger())) {
      throw ProcurementException::invalid('Individually tracked equipment quantities must be whole units.');
    }

    self::validateTemplate($identityTemplate);
    foreach ([[$partCode, 100], [$partLabel, 255], [$partUnit, 32]] as [$value, $maximum]) {
      if (null !== $value && ('' === trim($value) || mb_strlen($value) > $maximum)) {
        throw ProcurementException::invalid('The retained article identity exceeds its allowed length.');
      }
    }
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
   * @param string $kind part or equipment_to_individualize
   * @param ?string $partId the article UUID
   * @param ?string $typeCode the declared equipment type
   * @param array<string, mixed> $identityTemplate the declarative template
   * @param string $quantity the positive exact ordered quantity
   * @param ?string $unitCost the exact unit amount, or unknown
   *
   * @return self the draft line
   */
  public static function create(string $id, string $kind, ?string $partId, ?string $typeCode, array $identityTemplate, string $quantity, ?string $unitCost, ?string $partCode = null, ?string $partLabel = null, ?string $partUnit = null): self
  {
    return self::reconstitute($id, $kind, $partId, $typeCode, $identityTemplate, $quantity, $unitCost, '0.000000', '0.000000', $partCode, $partLabel, $partUnit);
  }

  /**
   * Method reconstitute
   *
   * Restores receipt and return totals with all quantity invariants enforced.
   *
   * @access public
   *
   * @param string $id the stable line UUID
   * @param string $kind part or equipment_to_individualize
   * @param ?string $partId the article UUID
   * @param ?string $typeCode the declared equipment type
   * @param array<string, mixed> $identityTemplate the declarative template
   * @param string $quantity the positive exact ordered quantity
   * @param ?string $unitCost the exact unit amount, or unknown
   * @param string $receivedQuantity the retained exact gross received quantity
   * @param string $returnedQuantity the retained exact returned quantity
   *
   * @return self the validated line snapshot
   */
  public static function reconstitute(string $id, string $kind, ?string $partId, ?string $typeCode, array $identityTemplate, string $quantity, ?string $unitCost, string $receivedQuantity, string $returnedQuantity, ?string $partCode = null, ?string $partLabel = null, ?string $partUnit = null): self
  {
    $cost = null === $unitCost ? null : DecimalAmount::fromString($unitCost);
    if (null !== $cost && $cost->isNegative()) {
      throw ProcurementException::invalid('A procurement unit cost cannot be negative.');
    }

    return new self($id, $kind, $partId, null === $typeCode ? null : trim($typeCode), $identityTemplate, DecimalAmount::fromString($quantity)->toString(), $cost?->toString(), DecimalAmount::fromString($receivedQuantity)->toString(), DecimalAmount::fromString($returnedQuantity)->toString(), $partCode, $partLabel, $partUnit);
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

    return new self($this->id, $this->kind, $this->partId, $this->typeCode, $this->identityTemplate, $this->quantity, $this->unitCost, DecimalAmount::fromString($this->receivedQuantity)->add($received)->toString(), $this->returnedQuantity, $this->partCode, $this->partLabel, $this->partUnit);
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

    return new self($this->id, $this->kind, $this->partId, $this->typeCode, $this->identityTemplate, $this->quantity, $this->unitCost, $this->receivedQuantity, DecimalAmount::fromString($this->returnedQuantity)->add($returned)->toString(), $this->partCode, $this->partLabel, $this->partUnit);
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

  /**
   * Method validateTemplate
   *
   * The template is declarative JSON; individual identities are validated through
   * the Equipment public contract when the goods are actually received.
   *
   * @access private
   *
   * @param array<array-key, mixed> $template the raw identity template
   *
   * @return void
   */
  private static function validateTemplate(array $template): void
  {
    if (([] !== $template && array_is_list($template)) || count($template) > 50) {
      throw ProcurementException::invalid('An equipment identity template must be a bounded object.');
    }
    foreach ($template as $key => $value) {
      if (!is_string($key) || '' === trim($key) || mb_strlen($key) > 64) {
        throw ProcurementException::invalid('Identity template field names must contain 1 to 64 characters.');
      }
    }

    $nodes = 0;
    self::validateTemplateValue($template, 0, $nodes);
  }

  /**
   * Method validateTemplateValue
   *
   * Rejects objects, nonfinite numbers and unbounded payloads without interpreting
   * or executing declarative identity properties.
   *
   * @access private
   *
   * @param mixed $value the candidate JSON value
   * @param int $depth the current nesting depth
   * @param int $nodes the number of already visited values
   *
   * @return void
   */
  private static function validateTemplateValue(mixed $value, int $depth, int &$nodes): void
  {
    if (++$nodes > 500 || $depth > 4) {
      throw ProcurementException::invalid('An identity template exceeds its size or depth bound.');
    }
    if (null === $value || is_bool($value) || is_int($value)) {
      return;
    }
    if (is_string($value)) {
      if (mb_strlen($value) > 2000) {
        throw ProcurementException::invalid('An identity template value exceeds its maximum length.');
      }

      return;
    }
    if (is_float($value)) {
      if (!is_finite($value)) {
        throw ProcurementException::invalid('Identity template numbers must be finite.');
      }

      return;
    }
    if (is_array($value)) {
      foreach ($value as $key => $item) {
        if (is_string($key) && mb_strlen($key) > 64) {
          throw ProcurementException::invalid('An identity template field name exceeds its maximum length.');
        }
        self::validateTemplateValue($item, $depth + 1, $nodes);
      }

      return;
    }

    throw ProcurementException::invalid('Identity templates must contain declarative JSON values.');
  }
  // #endregion
}
