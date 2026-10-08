<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\Uuid;

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
 * Class ProcurementGoodsIdentity
 *
 * Keeps a part catalog snapshot or an equipment identity template as one validated goods identity.
 *
 * @category ValueObject
 */
final readonly class ProcurementGoodsIdentity
{
  // #region Properties
  /**
   * Property typeCode
   */
  public ?string $typeCode;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $kind part or equipment_to_individualize
   * @param ?string $partId the scoped part UUID
   * @param ?string $typeCode the equipment type code
   * @param array<string,mixed> $identityTemplate the bounded declarative identity
   * @param ?string $partCode the retained catalog code
   * @param ?string $partLabel the retained catalog label
   * @param ?string $partUnit the retained catalog unit
   *
   * @return void
   */
  public function __construct(public string $kind, public ?string $partId, ?string $typeCode, public array $identityTemplate, public ?string $partCode = null, public ?string $partLabel = null, public ?string $partUnit = null)
  {
    $this->typeCode = null === $typeCode ? null : trim($typeCode);
    if (!in_array($kind, ['part', 'equipment_to_individualize'], true)) {
      throw ProcurementException::invalid('Unknown procurement line kind.');
    }
    if ('part' === $kind) {
      if (null === $partId || null !== $this->typeCode || [] !== $identityTemplate) {
        throw ProcurementException::invalid('A part line needs only an article identity.');
      }
      Uuid::assertValid($partId);
    } elseif (null !== $partId || null === $this->typeCode || '' === $this->typeCode || mb_strlen($this->typeCode) > 32) {
      throw ProcurementException::invalid('An equipment line needs a type code and cannot also refer to a consumable article.');
    }
    self::validateTemplate($identityTemplate);
    self::assertRetainedPartIdentity($partCode, $partLabel, $partUnit);
  }
  // #endregion

  // #region Methods
  /**
   * Method assertRetainedPartIdentity
   *
   * @access private
   *
   * @param ?string $partCode the retained catalog code
   * @param ?string $partLabel the retained catalog label
   * @param ?string $partUnit the retained catalog unit
   *
   * @return void
   */
  private static function assertRetainedPartIdentity(?string $partCode, ?string $partLabel, ?string $partUnit): void
  {
    foreach ([[$partCode, 100], [$partLabel, 255], [$partUnit, 32]] as [$value, $maximum]) {
      if (null !== $value && ('' === trim($value) || mb_strlen($value) > $maximum)) {
        throw ProcurementException::invalid('The retained article identity exceeds its allowed length.');
      }
    }
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

    } elseif (is_float($value)) {
      if (!is_finite($value)) {
        throw ProcurementException::invalid('Identity template numbers must be finite.');
      }

    } elseif (is_array($value)) {
      self::validateTemplateArray($value, $depth, $nodes);
    } else {
      throw ProcurementException::invalid('Identity templates must contain declarative JSON values.');
    }
  }

  /**
   * Method validateTemplateArray
   *
   * Shares the node budget across every nested item before accepting a template.
   *
   * @access private
   *
   * @param array<array-key,mixed> $values the declarative child values
   * @param int $depth the current nesting depth
   * @param int $nodes the shared number of visited values
   *
   * @return void
   */
  private static function validateTemplateArray(array $values, int $depth, int &$nodes): void
  {
    foreach ($values as $key => $item) {
      if (is_string($key) && mb_strlen($key) > 64) {
        throw ProcurementException::invalid('An identity template field name exceeds its maximum length.');
      }
      self::validateTemplateValue($item, $depth + 1, $nodes);
    }
  }
  // #endregion
}
