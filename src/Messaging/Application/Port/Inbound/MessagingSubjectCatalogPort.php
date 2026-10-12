<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Inbound;

use Messaging\Application\Contract\Subject\MessagingSubjectDescriptor;

/**
 * Port MessagingSubjectCatalogPort.
 *
 * Exposes metadata from loaded subject contributors. Lookup never grants
 * access to a subject and returns null for unknown or historical unloaded keys.
 *
 * @category Port
 */
interface MessagingSubjectCatalogPort
{
  // #region Methods
  /**
   * Method descriptors.
   *
   * @access public
   *
   * @return list<MessagingSubjectDescriptor> registered subject descriptors sorted by key
   */
  public function descriptors(): array;

  /**
   * Method find.
   *
   * @access public
   *
   * @param string $type the unchanged subject key
   *
   * @return ?MessagingSubjectDescriptor metadata when the contributor is loaded
   */
  public function find(string $type): ?MessagingSubjectDescriptor;
  // #endregion
}
