<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Template\ListInterventionTemplates;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInterventionTemplatesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionTemplatesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param ?string $search an optional case-insensitive partial match on the name
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public int $page,
    public int $itemsPerPage,
    public ?string $search = null,
  ) {
  }
}
