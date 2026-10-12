<?php

declare(strict_types=1);

namespace Approval\Application\Service;

use Approval\Application\Contract\Action\ApprovalActionDescriptor;
use Approval\Application\Port\Outbound\{ApprovalActionCatalogPort, ApprovalActionDescriptorProviderPort, ApprovalActionExecutorPort};
use InvalidArgumentException;

use function array_values;
use function sprintf;

/**
 * Class ApprovalActionCatalogRegistry
 *
 * Resolves tagged owner metadata and rejects ambiguous registrations at construction.
 *
 * @category Service
 */
final readonly class ApprovalActionCatalogRegistry implements ApprovalActionCatalogPort
{
  // #region Properties
  /**
   * Property descriptors
   *
   * @var array<string, ApprovalActionDescriptor>
   */
  private array $descriptors;
  // #endregion

  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param iterable<ApprovalActionDescriptorProviderPort> $providers the owner contributions
   * @param iterable<ApprovalActionExecutorPort> $executors the owner action executors
   */
  public function __construct(iterable $providers, iterable $executors)
  {
    $executorKeys = [];
    foreach ($executors as $executor) {
      $key = $executor->actionType();
      if (isset($executorKeys[$key])) {
        throw new InvalidArgumentException(sprintf('Duplicate approval executor "%s".', $key));
      }
      $executorKeys[$key] = true;
    }
    $descriptors = [];
    foreach ($providers as $provider) {
      $descriptor = $provider->descriptor();
      if (isset($descriptors[$descriptor->value])) {
        throw new InvalidArgumentException(sprintf('Duplicate approval action "%s".', $descriptor->value));
      }
      if (!isset($executorKeys[$descriptor->value])) {
        throw new InvalidArgumentException(sprintf('Approval action "%s" has no executor.', $descriptor->value));
      }
      $descriptors[$descriptor->value] = $descriptor;
    }
    foreach ($executorKeys as $value => $unused) {
      if (!isset($descriptors[$value])) {
        throw new InvalidArgumentException(sprintf('Approval executor "%s" has no descriptor.', $value));
      }
    }
    $this->descriptors = $descriptors;
  }
  // #endregion

  // #region Methods
  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<ApprovalActionDescriptor> the installed action metadata
   */
  public function descriptors(): array
  {
    return array_values($this->descriptors);
  }

  /**
   * Method find
   *
   * @access public
   *
   * @param string $value the stable action identifier
   *
   * @return ?ApprovalActionDescriptor the installed metadata
   */
  public function find(string $value): ?ApprovalActionDescriptor
  {
    return $this->descriptors[$value] ?? null;
  }
  // #endregion
}
