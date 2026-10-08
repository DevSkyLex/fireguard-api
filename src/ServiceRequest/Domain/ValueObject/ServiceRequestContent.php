<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\ValueObject;

/**
 * Class ServiceRequestContent
 *
 * Groups editable request content; creation and edits validate through the aggregate.
 * Restoration preserves historical text and priority without applying new-write normalization.
 *
 * @category ValueObject
 */
final readonly class ServiceRequestContent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $title declared or retained title
   * @param string $description declared or retained explanation
   * @param string $priority declared or retained priority
   *
   * @return void
   */
  public function __construct(public string $title, public string $description, public string $priority = 'normal')
  {
  }
  // #endregion
}
