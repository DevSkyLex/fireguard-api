<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Publication\GetPublication;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetPublicationQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPublicationQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPublicationQuery class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $publicationId the publication id value
   */
  public function __construct(
    public string $userId,
    public string $publicationId,
  ) {
  }
}
