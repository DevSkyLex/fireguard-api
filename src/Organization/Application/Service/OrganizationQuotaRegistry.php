<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use LogicException;
use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;
use Organization\Application\Port\Inbound\OrganizationQuotaCatalogPort;
use Organization\Application\Port\Outbound\OrganizationQuotaContributionPort;
use Shared\Domain\Exception\InvalidValueException;

use function array_values;
use function is_int;
use function is_string;
use function sprintf;
use function uasort;

/**
 * Class OrganizationQuotaRegistry
 *
 * Composes owner-published resources; no FireGuard resource list or counting switch lives in the engine.
 *
 * @category Service
 */
final readonly class OrganizationQuotaRegistry implements OrganizationQuotaCatalogPort
{
  // #region Properties
  /**
   * @var array<string, OrganizationQuotaDefinition>
   */
  private array $resources;

  /**
   * @var array<string, OrganizationQuotaContributionPort>
   */
  private array $owners;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<OrganizationQuotaContributionPort> $contributions registered resource owners
   */
  public function __construct(iterable $contributions)
  {
    $resources = [];
    $owners = [];
    foreach ($contributions as $contribution) {
      foreach ($contribution::definitions() as $definition) {
        if (isset($resources[$definition->key])) {
          throw new LogicException(sprintf('Duplicate organization quota key "%s".', $definition->key));
        }
        $resources[$definition->key] = $definition;
        $owners[$definition->key] = $contribution;
      }
    }
    uasort($resources, static fn (OrganizationQuotaDefinition $left, OrganizationQuotaDefinition $right): int => [$left->position, $left->key] <=> [$right->position, $right->key]);
    $this->resources = $resources;
    $this->owners = $owners;
  }
  // #endregion

  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationQuotaDefinition>
   */
  public function definitions(): array
  {
    return array_values($this->resources);
  }

  /**
   * Method get
   *
   * @access public
   *
   * @return OrganizationQuotaDefinition
   */
  public function get(string $key): OrganizationQuotaDefinition
  {
    return $this->resources[$key] ?? throw InvalidValueException::because(sprintf('Unknown organization quota resource "%s".', $key));
  }

  /**
   * Method usage
   *
   * @access public
   *
   * @return int
   */
  public function usage(string $organizationId, string $key): int
  {
    $this->get($key);

    $usage = $this->owners[$key]->usage($organizationId, $key);
    if ($usage < 0) {
      throw new LogicException(sprintf('Negative organization quota usage for "%s".', $key));
    }

    return $usage;
  }

  /**
   * Method normalizeLimits
   *
   * @access public
   *
   * @param array<array-key, mixed> $limits submitted limits, including malformed numeric keys
   *
   * @return array<string, int>
   */
  public function normalizeLimits(array $limits): array
  {
    $normalized = [];
    foreach ($limits as $key => $value) {
      if (!is_string($key)) {
        throw InvalidValueException::because('Quota resource keys must be registered string identifiers.');
      }
      $this->get($key);
      if (!is_int($value) || $value < 0) {
        throw InvalidValueException::because(sprintf('Quota limit for "%s" must be a non-negative integer.', $key));
      }
      $normalized[$key] = $value;
    }

    return $normalized;
  }
  // #endregion
}
