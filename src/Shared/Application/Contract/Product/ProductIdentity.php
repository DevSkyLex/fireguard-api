<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Product;

/**
 * Class ProductIdentity
 *
 * Publishes deployment-owned product wording and API metadata without framework types.
 *
 * @category Contract
 */
final readonly class ProductIdentity
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Keeps the public name separate from the API title to preserve their distinct wording.
   *
   * @access public
   *
   * @param string $publicName the name displayed in transactional emails
   * @param string $apiTitle the documentation title
   * @param string $apiDescription the documentation description
   * @param string $version the advertised application version
   *
   * @return void
   */
  public function __construct(
    public string $publicName,
    public string $apiTitle,
    public string $apiDescription,
    public string $version,
  ) {
  }
  // #endregion
}
