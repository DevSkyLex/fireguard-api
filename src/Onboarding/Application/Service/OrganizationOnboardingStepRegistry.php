<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

use LogicException;
use Onboarding\Application\Contract\Step\OrganizationOnboardingStepDefinition;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingStepContributionPort;
use Onboarding\Domain\Exception\UnsupportedOnboardingStepException;

use function array_map;
use function array_values;
use function sprintf;
use function uasort;

/**
 * Class OrganizationOnboardingStepRegistry
 *
 * Orders registered owner contributions and rejects ambiguous keys before flow execution.
 *
 * @category Service
 */
final readonly class OrganizationOnboardingStepRegistry
{
  // #region Properties
  /**
   * Property contributions
   *
   * @var array<string, OrganizationOnboardingStepContributionPort>
   */
  private array $contributions;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<OrganizationOnboardingStepContributionPort> $contributions owner-registered steps
   *
   * @return void
   *
   * @throws LogicException when a key has more than one contributor
   */
  public function __construct(iterable $contributions)
  {
    $indexed = [];
    foreach ($contributions as $contribution) {
      $key = $contribution::definition()->key;
      if (isset($indexed[$key])) {
        throw new LogicException(sprintf('Duplicate onboarding step key "%s".', $key));
      }
      $indexed[$key] = $contribution;
    }
    uasort($indexed, static fn (OrganizationOnboardingStepContributionPort $a, OrganizationOnboardingStepContributionPort $b): int => ($a::definition()->order <=> $b::definition()->order) ?: ($a::definition()->key <=> $b::definition()->key));
    $this->contributions = $indexed;
  }
  // #endregion

  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationOnboardingStepDefinition> canonical public step descriptors
   */
  public function definitions(): array
  {
    return array_values(array_map(static fn (OrganizationOnboardingStepContributionPort $step): OrganizationOnboardingStepDefinition => $step::definition(), $this->contributions));
  }

  /**
   * Method keys
   *
   * @access public
   *
   * @return list<string> canonical registered keys
   */
  public function keys(): array
  {
    return array_map(static fn (OrganizationOnboardingStepDefinition $step): string => $step->key, $this->definitions());
  }

  /**
   * Method get
   *
   * @access public
   *
   * @param string $key requested new execution key
   *
   * @return OrganizationOnboardingStepContributionPort registered owner
   *
   * @throws UnsupportedOnboardingStepException when no owner registered the key
   */
  public function get(string $key): OrganizationOnboardingStepContributionPort
  {
    return $this->contributions[$key] ?? throw UnsupportedOnboardingStepException::withStepKey($key);
  }
  // #endregion
}
