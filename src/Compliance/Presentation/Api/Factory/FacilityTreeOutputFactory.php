<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Factory;

use Compliance\Application\Contract\FacilityTreeNode;
use Compliance\Presentation\Api\Dto\Output\FacilityTreeOutput;

use function array_map;

/**
 * Factory FacilityTreeOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityTreeOutputFactory
{
  // #region Methods
  /**
   * Method fromTree.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $generatedAt ISO 8601 generation datetime
   * @param list<FacilityTreeNode> $tree the root-level facility tree nodes
   *
   * @return FacilityTreeOutput the enriched facility tree output
   */
  public function fromTree(string $organizationId, string $generatedAt, array $tree): FacilityTreeOutput
  {
    $output = new FacilityTreeOutput();
    $output->organizationId = $organizationId;
    $output->generatedAt = $generatedAt;
    $output->nodes = array_map($this->nodeToArray(...), $tree);

    return $output;
  }

  /**
   * Method nodeToArray.
   *
   * Recursively serializes a node and its children into the array shape the
   * `FacilityTreeOutput::$nodes` contract documents.
   *
   * @since 1.0.0
   *
   * @param FacilityTreeNode $node the facility tree node
   *
   * @return array{
   *   id: string,
   *   name: string,
   *   type: string,
   *   parentFacilityId: ?string,
   *   equipmentCount: int,
   *   status: string,
   *   complianceRate: ?float,
   *   children: array<int, mixed>,
   * } the node's serialized shape
   */
  private function nodeToArray(FacilityTreeNode $node): array
  {
    return [
      'id' => $node->id,
      'name' => $node->name,
      'type' => $node->type,
      'parentFacilityId' => $node->parentFacilityId,
      'equipmentCount' => $node->equipmentCount,
      'status' => $node->status->value,
      'complianceRate' => $node->complianceRate,
      'children' => array_map($this->nodeToArray(...), $node->children),
    ];
  }
  // #endregion
}
