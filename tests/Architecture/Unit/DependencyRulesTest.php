<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, DependencyRules, ExactDependencyBaseline, Module, ModuleCollection};
use PHPat\Rule\Assertion\Relation\CanOnlyDepend\CanOnlyDepend;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\RelationRule;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;

use function basename;
use function class_exists;
use function count;
use function dirname;
use function iterator_to_array;

/**
 * Exercises the real PHPat selectors, including literal migration exceptions.
 *
 * @category Architecture Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DependencyRulesTest extends PHPStanTestCase
{
  /**
   * @return void
   */
  #[Test]
  public function everyModuleUsesItsActualComposerNamespaceAndRetainsItsRule(): void
  {
    $policy = new DependencyRules(new ExactDependencyBaseline([]));
    foreach (ArchitectureLayer::cases() as $layer) {
      $expected = 0;
      $rules = iterator_to_array($policy->forLayer($layer));
      foreach (ModuleCollection::all() as $module) {
        self::assertSame(basename($module->path), $module->namespace);
        if ($module->hasLayer($layer)) {
          ++$expected;
          self::assertArrayHasKey($module->layerNamespace($layer) . '\\*', $rules);
        }
      }
      // PHPat indexes iterable rules by key; duplicate generator keys silently discard modules.
      self::assertCount($expected, $rules);
    }
    $handler = 'Facility\\Application\\UseCase\\Query\\Facility\\ListFacilities\\ListFacilitiesHandler';
    self::assertTrue(class_exists($handler));
    $application = iterator_to_array($policy->forLayer(ArchitectureLayer::APPLICATION));
    self::assertTrue($this->selects($application['Facility\\Application\\*'](), $handler));
    self::assertFalse($this->accepts($application['Facility\\Application\\*'](), $handler, 'Doctrine\\DBAL\\Connection'));
  }

  /**
   * @return void
   */
  #[Test]
  public function anExactExceptionNeverExemptsAnotherTargetOrAnotherConsumer(): void
  {
    $consumer = 'Facility\\Application\\UseCase\\Query\\Facility\\ListFacilities\\ListFacilitiesHandler';
    $otherConsumer = 'Facility\\Application\\UseCase\\Query\\Facility\\ListFacilities\\ListFacilitiesQuery';
    $legacy = 'Organization\\Domain\\Model\\Organization\\Organization';
    $unexpected = 'User\\Domain\\Model\\User\\User';
    $policy = new DependencyRules(new ExactDependencyBaseline([$consumer => [$legacy]]));
    $builders = iterator_to_array($policy->forLayer(ArchitectureLayer::APPLICATION));
    $general = $builders['Facility\\Application\\*']();
    $exception = $builders[$consumer]();

    self::assertSame(CanOnlyDepend::class, $exception->getAssertion());
    self::assertFalse($this->selects($general, $consumer));
    self::assertTrue($this->selects($exception, $consumer));
    self::assertTrue($this->accepts($exception, $consumer, $legacy));
    self::assertFalse($this->accepts($exception, $consumer, $unexpected));
    self::assertFalse($this->selects($exception, $otherConsumer));
    self::assertFalse($this->accepts($general, $otherConsumer, $legacy));
  }

  /**
   * @return void
   */
  #[Test]
  public function publicSurfacesAndTheSharedKernelAreAllowedButPrivateTypesAreDenied(): void
  {
    $policy = new DependencyRules(new ExactDependencyBaseline([]));
    $builders = iterator_to_array($policy->forLayer(ArchitectureLayer::APPLICATION));
    $rule = $builders['Facility\\Application\\*']();
    $consumer = 'Facility\\Application\\UseCase\\Query\\Facility\\ListFacilities\\ListFacilitiesHandler';

    self::assertTrue($this->accepts($rule, $consumer, 'Organization\\Application\\Port\\Inbound\\OrganizationQuotaPort'));
    self::assertTrue($this->accepts($rule, $consumer, 'Shared\\Application\\Port\\Outbound\\UuidGeneratorPort'));
    self::assertFalse($this->accepts($rule, $consumer, 'Organization\\Domain\\Model\\Organization\\Organization'));
    self::assertFalse($this->accepts($rule, $consumer, 'Symfony\\Component\\DependencyInjection\\Attribute\\Autowire'));
  }

  /**
   * @return void
   */
  #[Test]
  public function aPublicPortCannotExportEvenItsOwnersPrivateModels(): void
  {
    $policy = new DependencyRules(new ExactDependencyBaseline([]));
    $builders = iterator_to_array($policy->forContracts());
    $rule = $builders['Organization\\Application\\Port\\*']();
    $consumer = 'Organization\\Application\\Port\\Inbound\\OrganizationQuotaPort';

    self::assertTrue($this->selects($rule, $consumer));
    self::assertFalse($this->accepts($rule, $consumer, 'Organization\\Domain\\Model\\Organization\\Organization'));
    self::assertFalse($this->accepts($rule, $consumer, 'Doctrine\\DBAL\\Connection'));
  }

  /**
   * @return void
   */
  #[Test]
  public function anInternalPortIsAvailableOnlyInsideItsOwner(): void
  {
    $policy = new DependencyRules(new ExactDependencyBaseline([]));
    $privatePort = 'Facility\\Application\\Port\\Outbound\\Internal\\FacilityRepositoryPort';
    $publicRules = iterator_to_array($policy->forContracts());
    self::assertFalse($this->selects($publicRules['Facility\\Application\\Port\\*'](), $privatePort));
    self::assertTrue($this->matchesAny(
      $policy->allowedTargets(new Module('Facility', 'unused'), ArchitectureLayer::APPLICATION),
      $privatePort,
    ));
    self::assertFalse($this->matchesAny(
      $policy->allowedTargets(new Module('Equipment', 'unused'), ArchitectureLayer::APPLICATION),
      $privatePort,
    ));
  }

  /**
   * @return void
   */
  #[Test]
  public function vendorImageExclusionDoesNotExposePrivateInterventionTypes(): void
  {
    $policy = new DependencyRules(new ExactDependencyBaseline([]));
    $module = new Module('Facility', 'unused');
    $targets = $policy->allowedTargets($module, ArchitectureLayer::INFRASTRUCTURE);

    self::assertTrue($this->matchesAny($targets, 'Intervention\\Image\\ImageManager'));
    self::assertTrue($this->matchesAny($targets, 'Doctrine\\DBAL\\Connection'));
    self::assertFalse($this->matchesAny($targets, 'Intervention\\Infrastructure\\Persistence\\Doctrine\\Record\\InterventionRecord'));
    self::assertFalse($this->matchesAny($targets, 'Facility\\Presentation\\Api\\Dto\\Output\\Facility\\FacilityOutput'));
    $presentation = $policy->allowedTargets($module, ArchitectureLayer::PRESENTATION);
    self::assertTrue($this->matchesAny($presentation, 'Symfony\\Component\\HttpFoundation\\Response'));
    self::assertFalse($this->matchesAny($presentation, 'Facility\\Infrastructure\\Persistence\\Doctrine\\Record\\FacilityRecord'));
  }

  /**
   * @return void
   */
  #[Test]
  public function allExistingBaselineTargetsRemainLiteralSelectors(): void
  {
    $baseline = ExactDependencyBaseline::load();
    $policy = new DependencyRules($baseline);
    $matched = 0;
    foreach (ArchitectureLayer::cases() as $layer) {
      foreach ($policy->forLayer($layer) as $key => $builder) {
        if (!isset($baseline->pairs[$key])) {
          continue;
        }
        ++$matched;
        $rule = $builder();
        self::assertCount(1, $rule->getSubjects());
        self::assertSame($key, $rule->getSubjects()[0]->getName());
        $targets = [];
        foreach ($rule->getTargets() as $target) {
          $targets[] = $target->getName();
        }
        foreach ($baseline->pairs[$key] as $legacyTarget) {
          self::assertContains($legacyTarget, $targets);
        }
      }
    }
    self::assertSame(count($baseline->pairs), $matched);
  }

  /**
   * @return void
   */
  #[Test]
  public function theLayerGateUsesOnlyExistingPairsForForbiddenLayerTransitions(): void
  {
    $consumer = 'Facility\\Presentation\\ExampleProvider';
    $forbidden = 'Auth\\Infrastructure\\Security\\User\\SecurityUser';
    $allowedLayer = 'Organization\\Domain\\Model\\Organization\\Organization';
    $baseline = new ExactDependencyBaseline([$consumer => [$forbidden, $allowedLayer]]);
    /** @var array{deptrac: array{ruleset: array<string, list<string>|null>}} $definition */
    $definition = Yaml::parseFile(dirname(__DIR__, 3) . '/deptrac.yaml');
    self::assertSame([$consumer => [$forbidden]], $baseline->forLayerRuleset($definition['deptrac']['ruleset']));
    self::assertSame([$consumer => [$forbidden, $allowedLayer]], $baseline->pairs);
  }

  /**
   * @param RelationRule $rule Actual PHPat relation
   * @param class-string $subject Consumer class
   *
   * @return bool Whether PHPat applies this rule
   */
  private function selects(RelationRule $rule, string $subject): bool
  {
    return $this->matchesAny($rule->getSubjects(), $subject)
      && !$this->matchesAny($rule->getSubjectExcludes(), $subject);
  }

  /**
   * @param RelationRule $rule Actual PHPat relation
   * @param class-string $subject Consumer class
   * @param class-string $target Dependency class
   *
   * @return bool Whether the selected consumer may reference the dependency
   */
  private function accepts(RelationRule $rule, string $subject, string $target): bool
  {
    return $this->selects($rule, $subject) && $this->matchesAny($rule->getTargets(), $target);
  }

  /**
   * @param array<SelectorInterface> $selectors Actual PHPat selectors
   * @param class-string $class Existing source or vendor class
   *
   * @return bool Whether a selector accepts the real PHPStan reflection
   */
  private function matchesAny(array $selectors, string $class): bool
  {
    $reflection = self::createReflectionProvider()->getClass($class);
    foreach ($selectors as $selector) {
      if ($selector->matches($reflection)) {
        return true;
      }
    }

    return false;
  }
}
