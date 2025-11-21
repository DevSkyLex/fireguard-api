<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use App\Tests\Architecture\Support\{Module, ModuleCollection, ArchitectureLayer, ArchitectureNamespace};

/**
 * Test BaseHexagonalArchitectureTest
 * @abstract
 *
 * Base class for hexagonal architecture tests
 *
 * @category Architecture Rule Tests
 * @package App\Tests\Architecture\Rule
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class BaseHexagonalArchitectureTest
{
  //#region Methods
  /**
   * Method modules
   * @final
   *
   * Returns all modules
   *
   * @access protected
   *
   * @return list<Module> List of modules
   */
  final protected function modules(): array
  {
    return ModuleCollection::all();
  }
  //#endregion

  //#region Methods
  /**
   * Method modulesHavingLayer
   * @final
   *
   * Modules having a specific layer
   *
   * @access protected
   *
   * @param ArchitectureLayer $layer Layer to filter modules
   *
   * @return list<Module> List of modules having the specified layer
   */
  final protected function modulesHavingLayer(ArchitectureLayer $layer): array
  {
    return array_values(array_filter(
      $this->modules(),
      static fn(Module $module): bool => $module->hasLayer(layer: $layer)
    ));
  }

  /**
   * Method selectorsForLayer
   * @final
   *
   * Selectors for a specific layer
   *
   * @access protected
   *
   * @param ArchitectureLayer $layer Layer to get selectors
   *
   * @return list<SelectorInterface> List of selectors for the specified layer
   */
  final protected function selectorsForLayer(ArchitectureLayer $layer): array
  {
    $selectors = [];

    foreach ($this->modulesHavingLayer($layer) as $module) {
      $namespace = $module->layerNamespace(layer: $layer);

      if ($namespace === '') {
        continue;
      }

      $selectors[] = Selector::inNamespace(namespace: $namespace);
    }

    return $selectors;
  }

  /**
   * Method selectorsForLayers
   * @final
   *
   * Selectors for multiple layers
   *
   * @access protected
   *
   * @param list<ArchitectureLayer> $layers Layers to get selectors
   *
   * @return list<SelectorInterface>
   */
  final protected function selectorsForLayers(array $layers): array
  {
    $namespaces = [];

    foreach ($layers as $layer) {
      foreach ($this->modulesHavingLayer($layer) as $module) {
        $namespace = $module->layerNamespace(layer: $layer);

        if ($namespace === '') {
          continue;
        }

        $namespaces[$namespace] = true;
      }
    }

    ksort($namespaces);

    $selectors = [];

    foreach (array_keys($namespaces) as $namespace) {
      $selectors[] = Selector::inNamespace(namespace: $namespace);
    }

    return $selectors;
  }

  /**
   * Method selectorsForModuleLayer
   * @final
   *
   * Selectors for a specific module layer
   *
   * @access protected
   *
   * @param Module $module Module to inspect
   * @param ArchitectureLayer $layer  Layer to get selectors
   *
   * @return list<SelectorInterface> List of selectors for the specified module layer
   */
  final protected function selectorsForModuleLayer(Module $module, ArchitectureLayer $layer): array
  {
    if (!$module->hasLayer($layer)) {
      return [];
    }

    $namespace = $module->layerNamespace(layer: $layer);

    if ($namespace === '') {
      return [];
    }

    return [
      Selector::inNamespace(namespace: $namespace),
    ];
  }

  /**
   * Method moduleNamespaces
   * @final
   *
   * Returns all module namespaces
   *
   * @access protected
   *
   * @return list<string> List of module namespaces
   */
  final protected function moduleNamespaces(): array
  {
    return array_map(
      static fn(Module $module): string => $module->namespace,
      $this->modules()
    );
  }

  /**
   * Method sharedModule
   * @final
   *
   * Returns the shared module if present
   *
   * @access protected
   *
   * @return Module|null Shared module instance or null
   */
  final protected function sharedModule(): ?Module
  {
    foreach ($this->modules() as $module) {
      if ($module->namespace === ArchitectureNamespace::SHARED->value) {
        return $module;
      }
    }

    return null;
  }

  /**
   * Method sharedLayerSelectors
   * @final
   *
   * Returns selectors for a shared layer if available
   *
   * @access protected
   *
   * @param ArchitectureLayer $layer Layer name
   *
   * @return list<SelectorInterface> List of selectors for the shared layer
   */
  final protected function sharedLayerSelectors(ArchitectureLayer $layer): array
  {
    $sharedModule = $this->sharedModule();

    if ($sharedModule === null) {
      return [];
    }

    return $this->selectorsForModuleLayer($sharedModule, $layer);
  }

  /**
   * Method sharedApplicationPortSelectors
   * @final
   *
   * Returns selectors for the shared
   * application ports
   *
   * @access protected
   *
   * @return list<SelectorInterface> List of selectors for shared ports
   */
  final protected function sharedApplicationPortSelectors(): array
  {
    $sharedModule = $this->sharedModule();

    if ($sharedModule === null || !$sharedModule->hasLayer(
      layer: ArchitectureLayer::APPLICATION
    )) return [];

    $portPath = $sharedModule->layerPath(
      layer: ArchitectureLayer::APPLICATION
    ) . DIRECTORY_SEPARATOR . ArchitectureNamespace::PORT->value;

    if (!is_dir($portPath)) return [];

    return [
      Selector::inNamespace(
        namespace: sprintf(
          '%s\\%s',
          $sharedModule->layerNamespace(ArchitectureLayer::APPLICATION),
          ArchitectureNamespace::PORT->value
        )
      ),
    ];
  }

  /**
   * Method selectorsForNamespaces
   * @final
   *
   * Builds selectors for an array of namespaces
   *
   * @access protected
   *
   * @param array<string> $namespaces Namespaces to convert
   *
   * @return list<SelectorInterface> List of selectors
   */
  final protected function selectorsForNamespaces(array $namespaces): array
  {
    $selectors = [];

    foreach ($namespaces as $namespace) {
      if ($namespace === '') {
        continue;
      }

      $selectors[] = Selector::inNamespace(namespace: $namespace);
    }

    return $selectors;
  }

  /**
   * Method srcDir
   * @final
   *
   * Returns the src directory
   *
   * @access protected
   *
   * @return string The src directory
   */
  final protected function srcDir(): string
  {
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
  }
  //#endregion
}

