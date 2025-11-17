<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use App\Tests\Architecture\Support\Module;

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
  //#region Constants
  /**
   * Constant LAYER
   *
   * Layer to test dependencies
   *
   * @access public
   *
   * @var string LAYER
   */
  public const string LAYER = self::INFRASTRUCTURE_LAYER;
  //#endregion

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
    foreach ($this->modulesHavingLayer(self::LAYER) as $module) {
      if ($module->namespace === self::SHARED_NAMESPACE) {
        continue;
      }

      $infrastructureSelectors = $this->selectorsForModuleLayer(
        module: $module,
        layer: self::LAYER
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
    $sharedDomainSelectors = $this->sharedLayerSelectors(layer: self::DOMAIN_LAYER);
    $sharedApplicationSelectors = $this->sharedLayerSelectors(layer: self::APPLICATION_LAYER);
    $sharedPortSelectors = $this->sharedApplicationPortSelectors();

    foreach ($this->modulesHavingLayer(self::LAYER) as $module) {
      if ($module->namespace === self::SHARED_NAMESPACE) continue;

      $infrastructureSelectors = $this->selectorsForModuleLayer(
        module: $module,
        layer: self::LAYER
      );

      if ($infrastructureSelectors === []) continue;

      $allowedSelectors = [
        ...$infrastructureSelectors,
        ...$this->selectorsForModuleLayer(
          module: $module,
          layer: self::APPLICATION_LAYER
        ),
        ...$this->selectorsForModuleLayer(
          module: $module,
          layer: self::DOMAIN_LAYER
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
        && $namespace !== self::SHARED_NAMESPACE
    ));
  }
  //#endregion
}
