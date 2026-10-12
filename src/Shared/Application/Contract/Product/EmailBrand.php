<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Product;

use InvalidArgumentException;

use function preg_match;

/**
 * Class EmailBrand
 *
 * Shares the server-owned image path and its stable email Content-ID between rendering and delivery.
 *
 * @category Contract
 */
final readonly class EmailBrand
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Rejects identifiers that cannot be safely used in an HTML CID reference.
   * A missing image remains a delivery concern and does not invalidate configuration.
   *
   * @access public
   *
   * @param string $markPath the configured local PNG path
   * @param string $contentId the image reference used by the email layout
   *
   * @return void
   *
   * @throws InvalidArgumentException when the Content-ID contains unsupported characters
   */
  public function __construct(public string $markPath, public string $contentId)
  {
    if (1 !== preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $contentId)) {
      throw new InvalidArgumentException('The email brand Content-ID must contain only letters, digits, dots, underscores or hyphens.');
    }
  }
  // #endregion
}
