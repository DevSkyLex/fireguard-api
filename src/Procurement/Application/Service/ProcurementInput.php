<?php

declare(strict_types=1);

namespace Procurement\Application\Service;

use DateMalformedStringException;
use DateTimeImmutable;
use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\{DecimalAmount, Uuid};

use function array_is_list;
use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function strtolower;

/**
 * Class ProcurementInput
 *
 * Decodes bounded mutation payloads without deciding access or changing retained state.
 *
 * @category Service
 */
final readonly class ProcurementInput
{
  // #region Methods
  /**
   * Method fields
   *
   * Rejects undeclared fields before decoding a bounded mutation payload.
   *
   * @access public
   *
   * @param array<string,mixed> $payload
   * @param list<string> $allowed
   *
   * @return void
   */
  public function fields(array $payload, array $allowed): void
  {
    foreach ($payload as $field => $value) {
      if (!in_array($field, $allowed, true)) {
        throw ProcurementException::invalid('Unknown procurement field: ' . $field);
      }
    }
  }

  /**
   * Method text
   *
   * Requires a submitted string without coercing transport values.
   *
   * @access public
   *
   * @param array<string,mixed> $payload
   * @param string $field the field value
   *
   * @return string
   */
  public function text(array $payload, string $field): string
  {
    $value = $payload[$field] ?? null;
    if (!is_string($value)) {
      throw ProcurementException::invalid('Field ' . $field . ' must be a string.');
    }

    return $value;
  }

  /**
   * Method optionalText
   *
   * Distinguishes an explicit nullable value from a required string.
   *
   * @access public
   *
   * @param array<string,mixed> $payload
   * @param string $field the field value
   *
   * @return ?string
   */
  public function optionalText(array $payload, string $field): ?string
  {
    if (!array_key_exists($field, $payload) || null === $payload[$field]) {
      return null;
    }

    return $this->text($payload, $field);
  }

  /**
   * Method textOr
   *
   * Retains the previous value when a patch omits the field.
   *
   * @access public
   *
   * @param array<string,mixed> $payload
   * @param string $field the field value
   * @param string $fallback the fallback value
   *
   * @return string
   */
  public function textOr(array $payload, string $field, string $fallback): string
  {
    return array_key_exists($field, $payload) ? $this->text($payload, $field) : $fallback;
  }

  /**
   * Method optionalOr
   *
   * Preserves patch omission separately from an explicit clear.
   *
   * @access public
   *
   * @param array<string,mixed> $payload
   * @param string $field the field value
   * @param ?string $fallback the fallback value
   *
   * @return ?string
   */
  public function optionalOr(array $payload, string $field, ?string $fallback): ?string
  {
    return array_key_exists($field, $payload) ? $this->optionalText($payload, $field) : $fallback;
  }

  /**
   * Method quantity
   *
   * Requires a positive exact decimal string within the procurement quantity limit.
   *
   * @access public
   *
   * @param mixed $quantity the quantity value
   *
   * @return string
   */
  public function quantity(mixed $quantity): string
  {
    if (!is_string($quantity)) {
      throw ProcurementException::invalid('Quantity must be an exact decimal string.');
    }
    $value = DecimalAmount::fromString($quantity);
    if ($value->isNegative() || $value->isZero() || $value->compareTo(DecimalAmount::fromInt(100000)) > 0) {
      throw ProcurementException::invalid('Quantity must be positive and at most 100000.');
    }

    return $value->toString();
  }

  /**
   * Method uuid
   *
   * Validates and normalizes identifier spelling for scoped lookups and replay.
   *
   * @access public
   *
   * @param string $id the id value
   *
   * @return string
   */
  public function uuid(string $id): string
  {
    return strtolower(new Uuid($id)->value);
  }

  /**
   * Method date
   *
   * Requires a valid physical timestamp with an explicit offset.
   *
   * @access public
   *
   * @param string $value the value value
   *
   * @return DateTimeImmutable
   */
  public function date(string $value): DateTimeImmutable
  {
    if (1 !== preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})$/', $value)) {
      throw ProcurementException::invalid('An explicit-offset receivedAt timestamp is required.');
    }

    try {
      $date = new DateTimeImmutable($value);
    } catch (DateMalformedStringException) {
      throw ProcurementException::invalid('The receivedAt date is invalid.');
    }
    $errors = DateTimeImmutable::getLastErrors();
    if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
      throw ProcurementException::invalid('The receivedAt date is invalid.');
    }

    return $date;
  }

  /**
   * Method contacts
   *
   * Decodes declarative contact fields without coercing their values.
   *
   * @access public
   *
   * @param mixed $input the input value
   *
   * @return list<array{name:string,email:?string,phone:?string,role:?string}>
   */
  public function contacts(mixed $input): array
  {
    if (!is_array($input) || !array_is_list($input)) {
      throw ProcurementException::invalid('Supplier contacts must be a list.');
    }
    $contacts = [];
    foreach ($input as $row) {
      if (!is_array($row)) {
        throw ProcurementException::invalid('A supplier contact must be an object.');
      }
      /** @var array<string,mixed> $row */
      $this->fields($row, ['name', 'email', 'phone', 'role']);
      $contacts[] = ['name' => $this->text($row, 'name'), 'email' => $this->optionalText($row, 'email'), 'phone' => $this->optionalText($row, 'phone'), 'role' => $this->optionalText($row, 'role')];
    }

    return $contacts;
  }

  /**
   * Method equipmentTemplate
   *
   * Decodes only supported equipment identity and technical property fields.
   *
   * @access public
   *
   * @param array<string,mixed> $input
   *
   * @return array{name?:?string,brand?:?string,model?:?string,subType?:?string,serialNumber?:?string,assetCode?:?string,criticality?:?string,technicalProperties?:list<array{key:string,value:string,unit:?string}>}
   */
  public function equipmentTemplate(array $input): array
  {
    $this->fields($input, ['name', 'brand', 'model', 'subType', 'serialNumber', 'assetCode', 'criticality', 'technicalProperties']);
    $output = ['name' => $this->optionalText($input, 'name'), 'brand' => $this->optionalText($input, 'brand'), 'model' => $this->optionalText($input, 'model'), 'subType' => $this->optionalText($input, 'subType'), 'serialNumber' => $this->optionalText($input, 'serialNumber'), 'assetCode' => $this->optionalText($input, 'assetCode'), 'criticality' => $this->optionalText($input, 'criticality')];
    if (array_key_exists('technicalProperties', $input)) {
      $properties = $input['technicalProperties'];
      if (!is_array($properties) || !array_is_list($properties)) {
        throw ProcurementException::invalid('Equipment technical properties must be a list.');
      }
      $rows = [];
      foreach ($properties as $property) {
        if (!is_array($property)) {
          throw ProcurementException::invalid('Equipment properties must be objects.');
        }
        /** @var array<string,mixed> $property */
        $this->fields($property, ['key', 'value', 'unit']);
        $rows[] = ['key' => $this->text($property, 'key'), 'value' => $this->text($property, 'value'), 'unit' => $this->optionalText($property, 'unit')];
      }
      $output['technicalProperties'] = $rows;
    }

    return $output;
  }
  // #endregion
}
