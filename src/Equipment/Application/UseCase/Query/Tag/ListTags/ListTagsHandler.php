<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Tag\ListTags;

use Equipment\Application\Port\Outbound\TagRepositoryPort;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function array_slice;

/**
 * UseCase ListTagsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTagsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private TagRepositoryPort $tagRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListTagsQuery $query): ListTagsResult
  {
    $organizationId = EquipmentOrganizationId::fromString($query->organizationId);

    $total = $this->tagRepository->countByOrganizationId($organizationId, $query->search);

    $tags = $this->tagRepository->findByOrganizationId($organizationId, $query->search);

    $sliced = array_slice($tags, $query->pagination->offset, $query->pagination->limit);

    return new ListTagsResult(
      tags: array_map(
        static fn ($tag): array => [
          'id' => (string) $tag->id(),
          'name' => $tag->name(),
          'organizationId' => (string) $tag->organizationId(),
        ],
        $sliced,
      ),
      total: $total,
    );
  }
  // #endregion
}
