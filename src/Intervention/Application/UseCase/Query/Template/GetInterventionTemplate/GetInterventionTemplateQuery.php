<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Template\GetInterventionTemplate;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInterventionTemplateQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionTemplateQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $templateId the template id value
   */
  public function __construct(
    public string $userId,
    public string $templateId,
  ) {
  }
}
