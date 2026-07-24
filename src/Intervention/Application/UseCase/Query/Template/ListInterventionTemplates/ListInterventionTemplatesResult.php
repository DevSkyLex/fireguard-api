<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Template\ListInterventionTemplates;

use Intervention\Application\Contract\Template\InterventionTemplatePage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInterventionTemplatesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionTemplatesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplatePage $page the page value
   */
  public function __construct(public InterventionTemplatePage $page)
  {
  }
}
