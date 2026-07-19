<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetFacilityTree;

use Compliance\Application\Contract\FacilityTreeNode;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetFacilityTreeResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityTreeResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $generatedAt ISO 8601 generation datetime (a live snapshot, same semantics as the compliance register)
   * @param list<FacilityTreeNode> $tree the root-level facility tree nodes, each carrying its nested children
   */
  public function __construct(
    public string $generatedAt,
    public array $tree,
  ) {
  }
}
