<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound\Internal;

use Import\Application\Contract\ImportKindDescriptor;
use Import\Application\Port\Outbound\ImportKindProcessorPort;

/**
 * Interface ImportKindRegistryPort
 *
 * Resolves configured contributions without coupling persisted jobs to registration.
 *
 * @category Port
 */
interface ImportKindRegistryPort
{
  // #region Methods
  /**
   * Method find
   *
   * @access public
   *
   * @param string $key persisted contributor key
   *
   * @return ?ImportKindProcessorPort registered contribution, absent when unavailable
   */
  public function find(string $key): ?ImportKindProcessorPort;

  /**
   * Method get
   *
   * @access public
   *
   * @param string $key requested contributor key
   *
   * @return ImportKindProcessorPort registered contribution
   */
  public function get(string $key): ImportKindProcessorPort;

  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<ImportKindDescriptor> configured contributions in registration order
   */
  public function descriptors(): array;
  // #endregion
}
