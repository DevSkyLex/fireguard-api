<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;

use function array_is_list;
use function array_key_last;
use function array_keys;
use function array_reverse;
use function count;
use function is_array;
use function is_string;
use function mb_substr;

/**
 * Class GlbNodeValidation
 *
 * Validates the bounded node forest and scene roots before producing stable binding indices.
 *
 * @category ValueObject
 */
final readonly class GlbNodeValidation
{
  // #region Methods
  /**
   * Method catalog
   *
   * Produces a node catalog only after validating transforms, ancestry and scene roots.
   *
   * @access public
   *
   * @param array<array-key, mixed> $json the decoded GLB document
   *
   * @return list<array{index: int, name: string}> immutable file node catalog
   */
  public static function catalog(array $json): array
  {
    $nodes = GlbValueValidation::items($json, 'nodes');
    if ([] === $nodes || count($nodes) > 10000) {
      throw FacilityModelException::invalid('The GLB must contain between 1 and 10000 nodes.');
    }
    $parents = self::parents($nodes, count(GlbValueValidation::items($json, 'meshes')));
    self::validateDepth($nodes, $parents);
    self::validateScenes($json, count($nodes), $parents);
    $catalog = [];
    foreach ($nodes as $index => $node) {
      $name = $node['name'] ?? null;
      if (null !== $name && !is_string($name)) {
        throw FacilityModelException::invalid('A node name must be a string.');
      }
      $catalog[] = ['index' => $index, 'name' => is_string($name) && '' !== $name ? mb_substr($name, 0, 255) : 'Node ' . $index];
    }

    return $catalog;
  }

  /**
   * Method parents
   *
   * Validates each node and records its unique parent without permitting shared children.
   *
   * @access private
   *
   * @param list<array<array-key, mixed>> $nodes the decoded node collection
   * @param int $meshCount the number of available meshes
   *
   * @return array<int, int> child-to-parent indices
   */
  private static function parents(array $nodes, int $meshCount): array
  {
    $parents = [];
    foreach ($nodes as $index => $node) {
      if (isset($node['mesh'])) {
        GlbValueValidation::index($node['mesh'], $meshCount);
      }
      self::validateTransform($node);
      foreach (self::children($node) as $child) {
        GlbValueValidation::index($child, count($nodes));
        if ($child === $index || isset($parents[$child])) {
          throw FacilityModelException::invalid('Node relationships must form a tree without shared children.');
        }
        $parents[$child] = $index;
      }
    }

    return $parents;
  }

  /**
   * Method children
   *
   * Requires a list for optional node child indices.
   *
   * @access private
   *
   * @param array<array-key, mixed> $node the decoded node
   *
   * @return list<mixed> untrusted child indices to validate against the node collection
   */
  private static function children(array $node): array
  {
    $children = $node['children'] ?? [];
    if (!is_array($children) || !array_is_list($children)) {
      throw FacilityModelException::invalid('Node children must be a list.');
    }

    return $children;
  }

  /**
   * Method validateTransform
   *
   * Checks finite matrix or TRS components and their mutually exclusive representation.
   *
   * @access private
   *
   * @param array<array-key, mixed> $node the decoded node
   *
   * @return void
   */
  private static function validateTransform(array $node): void
  {
    foreach (['matrix' => 16, 'translation' => 3, 'rotation' => 4, 'scale' => 3] as $key => $length) {
      if (isset($node[$key])) {
        GlbValueValidation::numbers($node[$key], $length);
      }
    }
    if (isset($node['matrix']) && (isset($node['translation']) || isset($node['rotation']) || isset($node['scale']))) {
      throw FacilityModelException::invalid('Node matrix and TRS transforms are mutually exclusive.');
    }
  }

  /**
   * Method validateDepth
   *
   * Traverses each parent chain once, rejecting cycles and hierarchies deeper than 256 levels.
   *
   * @access private
   *
   * @param list<array<array-key, mixed>> $nodes the validated node collection
   * @param array<int, int> $parents child-to-parent indices
   *
   * @return void
   */
  private static function validateDepth(array $nodes, array $parents): void
  {
    $finished = [];
    foreach (array_keys($nodes) as $index) {
      $path = self::unfinishedPath($index, $parents, $finished);
      if ([] === $path) {
        continue;
      }
      $cursor = $path[array_key_last($path)];
      $depth = $finished[$parents[$cursor] ?? $cursor] ?? 0;
      foreach (array_reverse($path) as $visited) {
        ++$depth;
        self::assertDepth($depth);
        $finished[$visited] = $depth;
      }
    }
  }

  /**
   * Method unfinishedPath
   *
   * Builds the unvisited parent chain while detecting local cycles and bounding traversal work.
   *
   * @access private
   *
   * @param int $index the starting node index
   * @param array<int, int> $parents child-to-parent indices
   * @param array<int, int> $finished previously validated node depths
   *
   * @return list<int> unvisited parent chain, empty for an already completed node
   */
  private static function unfinishedPath(int $index, array $parents, array $finished): array
  {
    $path = [];
    $cursor = $index;
    while (!isset($finished[$cursor])) {
      if (isset($path[$cursor])) {
        throw FacilityModelException::invalid('The GLB node graph contains a cycle.');
      }
      $path[$cursor] = true;
      self::assertDepth(count($path));
      if (!isset($parents[$cursor])) {
        break;
      }
      $cursor = $parents[$cursor];
    }

    return array_keys($path);
  }

  /**
   * Method assertDepth
   *
   * Caps node depth before a browser loader can recurse over the hierarchy.
   *
   * @access private
   *
   * @param int $depth the calculated hierarchy depth
   *
   * @return void
   */
  private static function assertDepth(int $depth): void
  {
    if ($depth > 256) {
      throw FacilityModelException::invalid('A GLB node hierarchy may be at most 256 levels deep.');
    }
  }

  /**
   * Method validateScenes
   *
   * Checks selected scene references and uniquely parentless root lists.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   * @param int $nodeCount the number of validated nodes
   * @param array<int, int> $parents child-to-parent indices
   *
   * @return void
   */
  private static function validateScenes(array $json, int $nodeCount, array $parents): void
  {
    $scenes = GlbValueValidation::items($json, 'scenes');
    if ([] === $scenes) {
      throw FacilityModelException::invalid('The GLB must contain a scene.');
    }
    if (isset($json['scene'])) {
      GlbValueValidation::index($json['scene'], count($scenes));
    }
    foreach ($scenes as $scene) {
      self::validateRoots($scene, $nodeCount, $parents);
    }
  }

  /**
   * Method validateRoots
   *
   * Requires distinct parentless nodes in one scene root list.
   *
   * @access private
   *
   * @param array<array-key, mixed> $scene the decoded scene
   * @param int $nodeCount the number of validated nodes
   * @param array<int, int> $parents child-to-parent indices
   *
   * @return void
   */
  private static function validateRoots(array $scene, int $nodeCount, array $parents): void
  {
    if (!is_array($scene['nodes'] ?? null) || !array_is_list($scene['nodes'])) {
      throw FacilityModelException::invalid('Each scene must list its root nodes.');
    }
    $roots = [];
    foreach ($scene['nodes'] as $root) {
      GlbValueValidation::index($root, $nodeCount);
      if (isset($parents[$root]) || isset($roots[$root])) {
        throw FacilityModelException::invalid('Scene roots must be unique parentless nodes.');
      }
      $roots[$root] = true;
    }
  }
  // #endregion
}
