<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Quota;

use InvalidArgumentException;

use function number_format;
use function preg_match;
use function sprintf;

/**
 * Class OrganizationQuotaDefinition
 *
 * Publishes a resource owner's stable key and presentation metadata without persistence dependencies.
 *
 * @category Contract
 */
final readonly class OrganizationQuotaDefinition
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $key stable persisted resource identifier
   * @param string $label display label
   * @param string $noun plural noun for allowance summaries
   * @param string $description catalog description
   * @param int $position deterministic catalog order
   */
  public function __construct(
    public string $key,
    public string $label,
    public string $noun,
    public string $description,
    public int $position = 0,
  ) {
    if (1 !== preg_match('/^[a-z][a-z0-9_]{0,79}$/D', $key)) {
      throw new InvalidArgumentException('Invalid organization quota key.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method summarize
   *
   * @access public
   *
   * @param int|null $limit quantity cap or unlimited
   *
   * @return string allowance summary
   */
  public function summarize(?int $limit): string
  {
    return null === $limit
      ? sprintf('Unlimited %s', $this->noun)
      : sprintf('Up to %s %s', number_format($limit), $this->noun);
  }
  // #endregion
}
