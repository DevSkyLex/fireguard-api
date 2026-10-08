<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\Uuid;

use function mb_strlen;
use function preg_match;
use function trim;

/**
 * Class PurchaseOrderIdentity
 *
 * Validates the draft's supplier, currency and display name together.
 *
 * @category ValueObject
 */
final readonly class PurchaseOrderIdentity
{
  // #region Properties
  /**
   * Property name
   */
  public string $name;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $supplierId the selected supplier UUID
   * @param string $currency the uppercase organization currency
   * @param string $name the raw display name
   *
   * @return void
   */
  public function __construct(public string $supplierId, public string $currency, string $name)
  {
    Uuid::assertValid($supplierId);
    self::normalizeCurrency($currency);
    $this->name = self::normalizeName($name);
  }
  // #endregion

  // #region Methods
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

  // #endregion
}
