<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use App\Tests\Architecture\Support\{ArchitectureLayer, ArchitectureNamespace, Module};

/**
 * Test InfrastructureDependenciesTest
 * @final
 *
 * Tests that the Infrastructure layer
 * stays inside the module
 *
 * @category Architecture Rule Tests
 * @package App\Tests\Architecture\Rule
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InfrastructureDependenciesTest extends BaseHexagonalArchitectureTest
{
  //#region Methods
  /**
   * Method testInfrastructureStaysInsideModule
   *
   * Tests that the Infrastructure layer
   * stays inside the module
   *
   * @access public
   *
   * @return iterable<Rule> The test rules
   */
  public function testInfrastructureStaysInsideModule(): iterable
  {
    foreach ($this->modulesHavingLayer(ArchitectureLayer::INFRASTRUCTURE) as $module) {
      if ($module->namespace === ArchitectureNamespace::SHARED->value) {
        continue;
      }

      $infrastructureSelectors = $this->selectorsForModuleLayer(
        module: $module,
        layer: ArchitectureLayer::INFRASTRUCTURE
      );

      if ($infrastructureSelectors === []) {
        continue;
      }

      $forbiddenSelectors = $this->selectorsForNamespaces(
        namespaces: $this->foreignModuleNamespaces(module: $module)
      );

      if ($forbiddenSelectors === []) {
        continue;
      }

      yield PHPat::rule()
        ->classes(...$infrastructureSelectors)
        ->shouldNotDependOn()
        ->classes(...$forbiddenSelectors)
        ->because(tips: 'Infrastructure adapters must not leak across modules.');
    }
  }

  /**
   * Method testInfrastructureDependsOnlyOnModuleAndShared
   *
   * Ensures module infrastructure only depends on
   * its module layers and shared contracts
   *
   * @access public
   *
   * @return iterable<Rule> Iterable of rules, one per module
   */
  public function testInfrastructureDependsOnlyOnModuleAndShared(): iterable
  {
    $sharedDomainSelectors = $this->sharedLayerSelectors(layer: ArchitectureLayer::DOMAIN);
    $sharedApplicationSelectors = $this->sharedLayerSelectors(layer: ArchitectureLayer::APPLICATION);
    $sharedPortSelectors = $this->sharedApplicationPortSelectors();

    foreach ($this->modulesHavingLayer(ArchitectureLayer::INFRASTRUCTURE) as $module) {
      if ($module->namespace === ArchitectureNamespace::SHARED->value) continue;

      $infrastructureSelectors = $this->selectorsForModuleLayer(
        module: $module,
        layer: ArchitectureLayer::INFRASTRUCTURE
      );

      if ($infrastructureSelectors === []) continue;

      $allowedSelectors = [
        ...$infrastructureSelectors,
        ...$this->selectorsForModuleLayer(
          module: $module,
          layer: ArchitectureLayer::APPLICATION
        ),
        ...$this->selectorsForModuleLayer(
          module: $module,
          layer: ArchitectureLayer::DOMAIN
        ),
        ...$sharedDomainSelectors,
        ...$sharedApplicationSelectors,
        ...$sharedPortSelectors,
      ];

      yield PHPat::rule()
        ->classes(...$infrastructureSelectors)
        ->canOnlyDependOn()
        ->classes(...$allowedSelectors)
        ->because(tips: 'Infrastructure adapters must stay within their module or shared contracts.');
    }
  }

  /**
   * Method otherModuleNamespaces
   *
   * Returns module namespaces except the provided one
   *
   * @access private
   *
   * @param Module $module Module to compare
   *
   * @return list<string> List of namespaces
   */
  private function foreignModuleNamespaces(Module $module): array
  {
    return array_values(array_filter(
      $this->moduleNamespaces(),
      static fn(string $namespace): bool => $namespace !== $module->namespace
        && $namespace !== ''
        && $namespace !== ArchitectureNamespace::SHARED->value
    ));
  }
  //#endregion
}
