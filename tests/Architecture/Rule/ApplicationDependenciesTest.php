<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use App\Tests\Architecture\Support\{ArchitectureLayer, ArchitectureNamespace};
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Test ApplicationDependenciesTest.
 *
 * @category Architecture Rule Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApplicationDependenciesTest extends BaseHexagonalArchitectureTest
{
  // #region Constants
  /**
   * Constant LAYER.
   *
   * Layer to test dependencies
   *
   * @var ArchitectureLayer LAYER
   */
  public const ArchitectureLayer LAYER = ArchitectureLayer::APPLICATION;

  /**
   * Constant FORBIDDEN_LAYER.
   *
   * Layer that should not be used by
   * the Application layer
   *
   * @var ArchitectureLayer FORBIDDEN_LAYER
   */
  public const ArchitectureLayer FORBIDDEN_LAYER = ArchitectureLayer::INFRASTRUCTURE;
  // #endregion

  // #region Methods
  /**
   * Method testApplicationDoesNotDependOnInfrastructure.
   *
   * Test Application layer does not depend
   * on Infrastructure layer
   *
   * @return Rule Returns the test rule
   */
  public function testApplicationDoesNotDependOnInfrastructure(): Rule
  {
    $applicationSelectors = $this->selectorsForLayer(layer: self::LAYER);
    $forbiddenSelectors = $this->selectorsForLayer(layer: self::FORBIDDEN_LAYER);
    $forbiddenSelectors[] = Selector::inNamespace(namespace: ArchitectureNamespace::ROOT->value);

    return PHPat::rule()
      ->classes(...$applicationSelectors)
      ->shouldNotDependOn()
      ->classes(...$forbiddenSelectors)
      ->because(tips: 'Application layer should rely only on Domain and ports.');
  }

  /**
   * Method testApplicationDependsOnlyOnDomainAndSharedContracts.
   *
   * Ensures Application layer collaborates only with its Domain and shared ports
   *
   * @return iterable<Rule> Iterable of rules, one per module
   */
  public function testApplicationDependsOnlyOnDomainAndSharedContracts(): iterable
  {
    $sharedDomainSelectors = $this->sharedLayerSelectors(layer: ArchitectureLayer::DOMAIN);
    $sharedPortSelectors = $this->sharedApplicationPortSelectors();

    foreach ($this->modulesHavingLayer(self::LAYER) as $module) {
      $applicationSelectors = $this->selectorsForModuleLayer(module: $module, layer: self::LAYER);

      if ([] === $applicationSelectors) {
        continue;
      }

      $allowedSelectors = [
        ...$applicationSelectors,
        ...$this->selectorsForModuleLayer(
          module: $module,
          layer: ArchitectureLayer::DOMAIN
        ),
        ...$sharedDomainSelectors,
        ...$sharedPortSelectors,
      ];

      yield PHPat::rule()
        ->classes(...$applicationSelectors)
        ->canOnlyDependOn()
        ->classes(...$allowedSelectors)
        ->because(tips: 'Application layer must orchestrate its Domain or shared contracts only.');
    }
  }

  /**
   * Method testApplicationModulesAreIsolated.
   *
   * Ensures application layers do not depend on other modules' applications
   *
   * @return iterable<Rule> Iterable of rules, one per module
   */
  public function testApplicationModulesAreIsolated(): iterable
  {
    $applicationModules = $this->modulesHavingLayer(self::LAYER);

    foreach ($applicationModules as $module) {
      $namespace = $module->layerNamespace(self::LAYER);

      if ('' === $namespace || $namespace === ArchitectureNamespace::SHARED->value) {
        continue;
      }

      $moduleSelectors = $this->selectorsForModuleLayer($module, self::LAYER);

      if ([] === $moduleSelectors) {
        continue;
      }

      $forbiddenNamespaces = [];

      foreach ($applicationModules as $candidate) {
        if ($candidate->namespace === $module->namespace) {
          continue;
        }

        $namespace = $candidate->layerNamespace(self::LAYER);

        if ('' === $namespace) {
          continue;
        }

        $forbiddenNamespaces[] = $namespace;
      }

      $forbiddenSelectors = $this->selectorsForNamespaces($forbiddenNamespaces);

      if ([] === $forbiddenSelectors) {
        continue;
      }

      yield PHPat::rule()
        ->classes(...$moduleSelectors)
        ->shouldNotDependOn()
        ->classes(...$forbiddenSelectors)
        ->because(tips: 'Application modules must stay isolated from each other.');
    }
  }
  // #endregion
}
