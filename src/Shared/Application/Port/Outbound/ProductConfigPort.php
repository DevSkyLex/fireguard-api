<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Application\Contract\Product\{EmailBrand, ProductIdentity};

/**
 * Interface ProductConfigPort
 *
 * Publishes immutable server configuration. No request, tenant or browser input may override it.
 * Implementations validate configuration before returning values and perform no external I/O.
 *
 * @category Port
 */
interface ProductConfigPort
{
  // #region Methods
  /**
   * Method identity
   *
   * @access public
   *
   * @return ProductIdentity the configured display and documentation identity
   */
  public function identity(): ProductIdentity;

  /**
   * Method emailBrand
   *
   * @access public
   *
   * @return EmailBrand the local email image and its CID reference
   */
  public function emailBrand(): EmailBrand;

  /**
   * Method frontendUrl
   *
   * @access public
   *
   * @return string an absolute HTTP(S) frontend base URL without credentials, query or fragment
   */
  public function frontendUrl(): string;

  /**
   * Method assistantInstructions
   *
   * @access public
   *
   * @return string the operator-owned system instructions
   */
  public function assistantInstructions(): string;
  // #endregion
}
