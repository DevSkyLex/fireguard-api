<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use Procurement\Domain\Exception\ProcurementException;

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
 * Class SupplierDetails
 *
 * Keeps normalized supplier contact data together for atomic replacement.
 *
 * @category ValueObject
 */
final readonly class SupplierDetails
{
  // #region Properties
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
   * @var list<array{name:string,email:?string,phone:?string,role:?string}> */
  public array $contacts;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Validates every candidate before any aggregate adopts the replacement.
   *
   * @access public
   *
   * @param string $name the raw supplier name
   * @param ?string $code the nullable supplier reference
   * @param ?string $email the nullable main email
   * @param ?string $phone the nullable main telephone
   * @param array<mixed> $contacts the raw contact list
   *
   * @return void
   */
  public function __construct(string $name, ?string $code, ?string $email, ?string $phone, array $contacts)
  {
    $this->name = self::normalizeName($name);
    $this->code = self::normalizeText($code, 80);
    $this->email = self::normalizeEmail($email);
    $this->phone = self::normalizeText($phone, 40);
    $this->contacts = self::normalizeContacts($contacts);
  }
  // #endregion

  // #region Methods
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
