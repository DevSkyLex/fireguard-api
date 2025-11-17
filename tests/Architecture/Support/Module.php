<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Support;

use function sprintf;
use function is_dir;

/**
 * Class Module
 * @final
 *
 * Utility class to represent a module
 *
 * @category Architecture Tests Support
 * @package App\Tests\Architecture\Support
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Module
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the module with its
   * namespace and path
   *
   * @access public
   *
   * @param string $namespace The module namespace
   * @param string $path The module path
   */
  public function __construct(
    public string $namespace,
    public string $path,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method layerNamespace
   *
   * Get the namespace of a layer
   *
   * @access public
   *
   * @param ArchitectureLayer $layer The layer name
   *
   * @return string The namespace of the layer
   */
  public function layerNamespace(ArchitectureLayer $layer): string
  {
    return sprintf('%s\\%s', $this->namespace, $layer->value);
  }

  /**
   * Method layerPath
   *
   * Get the path of a layer
   *
   * @access public
   *
   * @param ArchitectureLayer $layer The layer name
   *
   * @return string The path of the layer
   */
  public function layerPath(ArchitectureLayer $layer): string
  {
    return $this->path . DIRECTORY_SEPARATOR . $layer->value;
  }

  /**
   * Method hasLayer
   *
   * Check if the module has a layer
   *
   * @access public
   *
   * @param ArchitectureLayer $layer The layer name
   *
   * @return bool True if the module has the layer, false otherwise
   */
  public function hasLayer(ArchitectureLayer $layer): bool
  {
    return is_dir(filename: $this->layerPath(layer: $layer));
  }
  //#endregion
}
