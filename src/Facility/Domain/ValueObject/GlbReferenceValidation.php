<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;

use function array_is_list;
use function count;
use function in_array;
use function is_array;

/**
 * Class GlbReferenceValidation
 *
 * Validates skin, material and animation references against their document-local collections.
 *
 * @category ValueObject
 */
final readonly class GlbReferenceValidation
{
  // #region Methods
  /**
   * Method validate
   *
   * Checks the remaining cross-collection references after binary and node validation.
   *
   * @access public
   *
   * @param array<array-key, mixed> $json the decoded GLB document
   * @param int $nodeCount the number of available nodes
   *
   * @return void
   */
  public static function validate(array $json, int $nodeCount): void
  {
    $accessorCount = count(GlbValueValidation::items($json, 'accessors'));
    $skins = GlbValueValidation::items($json, 'skins');
    self::validateNodeReferences($json, count($skins), count(GlbValueValidation::items($json, 'cameras')));
    foreach ($skins as $skin) {
      self::validateSkin($skin, $nodeCount, $accessorCount);
    }
    $textureCount = self::validateTextures($json);
    foreach (GlbValueValidation::items($json, 'materials') as $material) {
      self::validateMaterial($material, $textureCount);
    }
    foreach (GlbValueValidation::items($json, 'animations') as $animation) {
      self::validateAnimation($animation, $nodeCount, $accessorCount);
    }
  }

  /**
   * Method validateNodeReferences
   *
   * Requires skin and camera references to resolve within their own collections.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   * @param int $skinCount the number of available skins
   * @param int $cameraCount the number of available cameras
   *
   * @return void
   */
  private static function validateNodeReferences(array $json, int $skinCount, int $cameraCount): void
  {
    foreach (GlbValueValidation::items($json, 'nodes') as $node) {
      foreach (['skin' => $skinCount, 'camera' => $cameraCount] as $key => $count) {
        if (isset($node[$key])) {
          GlbValueValidation::index($node[$key], $count);
        }
      }
    }
  }

  /**
   * Method validateSkin
   *
   * Requires distinct joint nodes and valid optional skeleton and bind-matrix references.
   *
   * @access private
   *
   * @param array<array-key, mixed> $skin the decoded skin
   * @param int $nodeCount the number of available nodes
   * @param int $accessorCount the number of available accessors
   *
   * @return void
   */
  private static function validateSkin(array $skin, int $nodeCount, int $accessorCount): void
  {
    $joints = $skin['joints'] ?? null;
    if (!is_array($joints) || !array_is_list($joints) || [] === $joints) {
      throw FacilityModelException::invalid('A GLB skin must contain joint indices.');
    }
    $seen = [];
    foreach ($joints as $joint) {
      GlbValueValidation::index($joint, $nodeCount);
      if (isset($seen[$joint])) {
        throw FacilityModelException::invalid('Skin joint indices must be unique.');
      }
      $seen[$joint] = true;
    }
    foreach (['skeleton' => $nodeCount, 'inverseBindMatrices' => $accessorCount] as $key => $count) {
      if (isset($skin[$key])) {
        GlbValueValidation::index($skin[$key], $count);
      }
    }
  }

  /**
   * Method validateTextures
   *
   * Validates image and sampler references for each material texture.
   *
   * @access private
   *
   * @param array<array-key, mixed> $json the decoded document
   *
   * @return int the number of available textures
   */
  private static function validateTextures(array $json): int
  {
    $imageCount = count(GlbValueValidation::items($json, 'images'));
    $samplerCount = count(GlbValueValidation::items($json, 'samplers'));
    $textures = GlbValueValidation::items($json, 'textures');
    foreach ($textures as $texture) {
      foreach (['source' => $imageCount, 'sampler' => $samplerCount] as $key => $count) {
        if (isset($texture[$key])) {
          GlbValueValidation::index($texture[$key], $count);
        }
      }
    }

    return count($textures);
  }

  /**
   * Method validateMaterial
   *
   * Checks supported material texture references, including metallic-roughness properties.
   *
   * @access private
   *
   * @param array<array-key, mixed> $material the decoded material
   * @param int $textureCount the number of available textures
   *
   * @return void
   */
  private static function validateMaterial(array $material, int $textureCount): void
  {
    $pbr = $material['pbrMetallicRoughness'] ?? [];
    if (!is_array($pbr)) {
      throw FacilityModelException::invalid('Material properties must be an object.');
    }
    foreach ([$material['normalTexture'] ?? null, $material['occlusionTexture'] ?? null,
      $material['emissiveTexture'] ?? null, $pbr['baseColorTexture'] ?? null, $pbr['metallicRoughnessTexture'] ?? null] as $textureInfo) {
      if (null === $textureInfo) {
        continue;
      }
      if (!is_array($textureInfo)) {
        throw FacilityModelException::invalid('Material texture information must be an object.');
      }
      GlbValueValidation::index($textureInfo['index'] ?? null, $textureCount);
    }
  }

  /**
   * Method validateAnimation
   *
   * Checks animation accessor inputs and outputs before each sampler and target channel.
   *
   * @access private
   *
   * @param array<array-key, mixed> $animation the decoded animation
   * @param int $nodeCount the number of available nodes
   * @param int $accessorCount the number of available accessors
   *
   * @return void
   */
  private static function validateAnimation(array $animation, int $nodeCount, int $accessorCount): void
  {
    $samplers = GlbValueValidation::items($animation, 'samplers');
    foreach ($samplers as $sampler) {
      GlbValueValidation::index($sampler['input'] ?? null, $accessorCount);
      GlbValueValidation::index($sampler['output'] ?? null, $accessorCount);
    }
    foreach (GlbValueValidation::items($animation, 'channels') as $channel) {
      self::validateChannel($channel, count($samplers), $nodeCount);
    }
  }

  /**
   * Method validateChannel
   *
   * Requires a valid animation sampler and supported optional node target.
   *
   * @access private
   *
   * @param array<array-key, mixed> $channel the decoded animation channel
   * @param int $samplerCount the number of available animation samplers
   * @param int $nodeCount the number of available nodes
   *
   * @return void
   */
  private static function validateChannel(array $channel, int $samplerCount, int $nodeCount): void
  {
    GlbValueValidation::index($channel['sampler'] ?? null, $samplerCount);
    $target = $channel['target'] ?? null;
    if (!is_array($target) || !in_array($target['path'] ?? null, ['translation', 'rotation', 'scale', 'weights'], true)) {
      throw FacilityModelException::invalid('Animation target properties are invalid.');
    }
    if (isset($target['node'])) {
      GlbValueValidation::index($target['node'], $nodeCount);
    }
  }
  // #endregion
}
