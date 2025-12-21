<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use App\Tests\Architecture\Support\{ArchitectureLayer, ArchitectureNamespace, Module, ModuleCollection};
use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function dirname;
use function is_dir;
use function ksort;
use function sprintf;

/**
 * Test BaseHexagonalArchitectureTest.
 *
 * @category Architecture Rule Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class BaseHexagonalArchitectureTest
{
  // #region Methods
  /**
   * Method modules.
   *
   * @return list<Module> List of modules
   */
  final protected function modules(): array
  {
    return ModuleCollection::all();
  }
  // #endregion

  // #region Methods
  /**
   * Method modulesHavingLayer.
   *
   * @param ArchitectureLayer $layer Layer to filter modules
   *
   * @return list<Module> List of modules having the specified layer
   */
  final protected function modulesHavingLayer(ArchitectureLayer $layer): array
  {
    return array_values(array_filter(
      $this->modules(),
      static fn (Module $module): bool => $module->hasLayer(layer: $layer)
    ));
  }

  /**
   * Method selectorsForLayer.
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

      if ('' === $namespace) {
        continue;
      }

      $selectors[] = Selector::inNamespace(namespace: $namespace);
    }

    return $selectors;
  }

  /**
   * Method selectorsForLayers.
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

        if ('' === $namespace) {
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
   * Method selectorsForModuleLayer.
   *
   * @param Module            $module Module to inspect
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

    if ('' === $namespace) {
      return [];
    }

    return [
      Selector::inNamespace(namespace: $namespace),
    ];
  }

  /**
   * Method moduleNamespaces.
   *
   * @return list<string> List of module namespaces
   */
  final protected function moduleNamespaces(): array
  {
    return array_map(
      static fn (Module $module): string => $module->namespace,
      $this->modules()
    );
  }

  /**
   * Method sharedModule.
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
   * Method sharedLayerSelectors.
   *
   * @param ArchitectureLayer $layer Layer name
   *
   * @return list<SelectorInterface> List of selectors for the shared layer
   */
  final protected function sharedLayerSelectors(ArchitectureLayer $layer): array
  {
    $sharedModule = $this->sharedModule();

    if (null === $sharedModule) {
      return [];
    }

    return $this->selectorsForModuleLayer($sharedModule, $layer);
  }

  /**
   * Method sharedApplicationPortSelectors.
   *
   * @return list<SelectorInterface> List of selectors for shared ports
   */
  final protected function sharedApplicationPortSelectors(): array
  {
    $sharedModule = $this->sharedModule();

    if (null === $sharedModule || !$sharedModule->hasLayer(
      layer: ArchitectureLayer::APPLICATION
    )) {
      return [];
    }

    $portPath = $sharedModule->layerPath(
      layer: ArchitectureLayer::APPLICATION
    ) . DIRECTORY_SEPARATOR . ArchitectureNamespace::PORT->value;

    if (!is_dir($portPath)) {
      return [];
    }

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
   * Method selectorsForNamespaces.
   *
   * @param array<string> $namespaces Namespaces to convert
   *
   * @return list<SelectorInterface> List of selectors
   */
  final protected function selectorsForNamespaces(array $namespaces): array
  {
    $selectors = [];

    foreach ($namespaces as $namespace) {
      if ('' === $namespace) {
        continue;
      }

      $selectors[] = Selector::inNamespace(namespace: $namespace);
    }

    return $selectors;
  }

  /**
   * Method srcDir.
   *
   * @return string The src directory
   */
  final protected function srcDir(): string
  {
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
  }
  // #endregion
}
