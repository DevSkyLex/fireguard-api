<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportTemplate;

use Shared\Application\Message\QueryMessage;

/** Query GetImportTemplateQuery. Templates follow the selected organization's write capability. */
final readonly class GetImportTemplateQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the requester, organization, and import kind used to build an authorized CSV template.
   *
   * @access public
   *
   * @param string $userId user requesting the template
   * @param string $organizationId organization whose write capability is checked
   * @param string $kind import kind selecting the template column contract
   *
   * @return void
   */
  public function __construct(public string $userId, public string $organizationId, public string $kind)
  {
  }
  // #endregion
}
