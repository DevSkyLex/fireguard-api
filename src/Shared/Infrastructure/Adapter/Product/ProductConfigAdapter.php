<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Adapter\Product;

use InvalidArgumentException;
use Shared\Application\Contract\Product\{EmailBrand, ProductIdentity};
use Shared\Application\Port\Outbound\ProductConfigPort;

use function filter_var;
use function in_array;
use function is_array;
use function parse_url;
use function preg_match;

use const FILTER_VALIDATE_URL;

/**
 * Class ProductConfigAdapter
 *
 * Validates deployment configuration once and publishes pure product contracts.
 *
 * @category Adapter
 */
final readonly class ProductConfigAdapter implements ProductConfigPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Frontend links accept HTTP for local development and HTTPS in deployment; neither
   * credentials nor URL suffixes may change how application paths are appended.
   *
   * @access public
   *
   * @param ProductIdentity $identity the configured public identity
   * @param EmailBrand $emailBrand the configured email brand
   * @param string $frontendUrl the trusted frontend base URL
   * @param string $assistantInstructions the configured assistant system message
   *
   * @return void
   *
   * @throws InvalidArgumentException when the frontend URL is not a valid HTTP(S) base URL
   */
  public function __construct(
    private ProductIdentity $identity,
    private EmailBrand $emailBrand,
    private string $frontendUrl,
    private string $assistantInstructions,
  ) {
    $parts = parse_url($frontendUrl);
    if (false === filter_var($frontendUrl, FILTER_VALIDATE_URL)
      || !is_array($parts)
      || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
      || 1 === preg_match('/[\x00-\x20\\\\]/', $frontendUrl)
    ) {
      throw new InvalidArgumentException('The product frontend URL must be an absolute HTTP(S) URL without credentials, query or fragment.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method identity
   *
   * @access public
   *
   * @return ProductIdentity the immutable identity
   */
  public function identity(): ProductIdentity
  {
    return $this->identity;
  }

  /**
   * Method emailBrand
   *
   * @access public
   *
   * @return EmailBrand the immutable email brand
   */
  public function emailBrand(): EmailBrand
  {
    return $this->emailBrand;
  }

  /**
   * Method frontendUrl
   *
   * @access public
   *
   * @return string the trusted frontend base URL
   */
  public function frontendUrl(): string
  {
    return $this->frontendUrl;
  }

  /**
   * Method assistantInstructions
   *
   * @access public
   *
   * @return string the deployment-owned assistant instructions
   */
  public function assistantInstructions(): string
  {
    return $this->assistantInstructions;
  }
  // #endregion
}
