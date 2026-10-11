<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Support;

use PHPat\Selector\{Selector, SelectorInterface};
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

use function array_keys;
use function array_map;
use function str_starts_with;

/**
 * Applies layer direction and owner-published module boundaries together.
 *
 * @category Architecture Tests Support
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DependencyRules
{
  /**
   * @param ExactDependencyBaseline $baseline Literal migration exceptions
   */
  public function __construct(private ExactDependencyBaseline $baseline)
  {
  }

  /**
   * @param ArchitectureLayer $layer Layer being checked
   *
   * @return iterable<non-empty-string, Rule> Layer rules with exact exceptions
   */
  public function forLayer(ArchitectureLayer $layer): iterable
  {
    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer($layer)) {
        continue;
      }
      $subjects = [Selector::inNamespace($module->layerNamespace($layer))];
      yield from $this->withExactExceptions(
        $module->layerNamespace($layer) . '\\',
        $subjects,
        $this->allowedTargets($module, $layer),
      );
    }
  }

  /**
   * Public contracts and ports cannot export private model/framework types.
   *
   * @return iterable<non-empty-string, Rule> Pure public-contract rules
   */
  public function forContracts(): iterable
  {
    foreach (ModuleCollection::all() as $module) {
      $namespace = $module->namespace . '\\Application\\Contract';
      yield from $this->withExactExceptions(
        $namespace . '\\',
        [Selector::inNamespace($namespace)],
        $this->publicTargets(),
      );
      $portNamespace = $module->namespace . '\\Application\\Port';
      $internalNamespaces = [$portNamespace . '\\Outbound\\Internal', $portNamespace . '\\Inbound\\Internal'];
      $internalSelectors = array_map(static fn (string $namespace): SelectorInterface => Selector::inNamespace($namespace), $internalNamespaces);
      yield from $this->withExactExceptions(
        $portNamespace . '\\',
        [Selector::AllOf(Selector::inNamespace($portNamespace), Selector::Not(Selector::AnyOf(...$internalSelectors)))],
        $this->publicTargets(),
        array_map(static fn (string $namespace): string => $namespace . '\\', $internalNamespaces),
      );
    }
  }

  /**
   * @param Module $module Consumer owner
   * @param ArchitectureLayer $layer Consumer layer
   *
   * @return list<SelectorInterface> Legitimate dependencies
   */
  public function allowedTargets(Module $module, ArchitectureLayer $layer): array
  {
    $allowed = [Selector::inNamespace($module->layerNamespace($layer))];
    if (ArchitectureLayer::DOMAIN === $layer) {
      $allowed[] = Selector::inNamespace('Shared\\Domain');

      return $allowed;
    }
    $allowed[] = Selector::inNamespace($module->namespace . '\\Domain');
    $allowed[] = Selector::inNamespace($module->namespace . '\\Application');
    $allowed = [...$allowed, ...$this->publicTargets()];
    if (ArchitectureLayer::INFRASTRUCTURE === $layer || ArchitectureLayer::PRESENTATION === $layer) {
      $owned = array_map(
        static fn (Module $candidate): SelectorInterface => Selector::inNamespace($candidate->namespace),
        ModuleCollection::all(),
      );
      $owned[] = Selector::inNamespace('App');
      $allowed[] = Selector::Not(Selector::AnyOf(...$owned));
      // The exact Composer-owned prefix is vendor code, not the Intervention module.
      $allowed[] = Selector::inNamespace('Intervention\\Image');
    }

    return $allowed;
  }

  /**
   * @return list<SelectorInterface> The owner-published cross-module surfaces
   */
  private function publicTargets(): array
  {
    $targets = [Selector::inNamespace('Shared\\Domain'), Selector::inNamespace('Shared\\Application\\Message')];
    foreach (ModuleCollection::all() as $module) {
      $portNamespace = $module->namespace . '\\Application\\Port';
      $targets[] = Selector::AllOf(
        Selector::inNamespace($portNamespace),
        Selector::Not(Selector::AnyOf(
          Selector::inNamespace($portNamespace . '\\Outbound\\Internal'),
          Selector::inNamespace($portNamespace . '\\Inbound\\Internal'),
        )),
      );
      $targets[] = Selector::inNamespace($module->namespace . '\\Application\\Contract');
    }

    return $targets;
  }

  /**
   * Split baseline subjects out of the general rule, then check each with only its literal targets.
   *
   * @param non-empty-string $prefix Subject namespace
   * @param list<SelectorInterface> $subjects General subject selectors
   * @param list<SelectorInterface> $allowed Legitimate dependencies
   * @param list<non-empty-string> $privatePrefixes Owner-private ports excluded from a public-surface rule
   *
   * @return iterable<non-empty-string, Rule> General and per-subject rules
   */
  private function withExactExceptions(string $prefix, array $subjects, array $allowed, array $privatePrefixes = []): iterable
  {
    $exceptions = [];
    foreach ($this->baseline->pairs as $subject => $targets) {
      if (!str_starts_with($subject, $prefix)) {
        continue;
      }
      foreach ($privatePrefixes as $privatePrefix) {
        if (str_starts_with($subject, $privatePrefix)) {
          continue 2;
        }
      }
      $exceptions[$subject] = $targets;
    }
    $excluded = array_map(
      static fn (string $subject): SelectorInterface => Selector::classname($subject),
      array_keys($exceptions),
    );
    yield $prefix . '*' => PHPat::rule()
      ->classes(...$subjects)
      ->excluding(...$excluded)
      ->canOnlyDependOn()
      ->classes(...$allowed)
      ->because('Dependencies must follow layer direction and published owner contracts.');
    foreach ($exceptions as $subject => $targets) {
      $legacyTargets = array_map(
        static fn (string $target): SelectorInterface => Selector::classname($target),
        $targets,
      );
      yield $subject => PHPat::rule()
        ->classes(Selector::classname($subject))
        ->canOnlyDependOn()
        ->classes(...$allowed, ...$legacyTargets)
        ->because('Only the existing literal dependency pairs are transitional migration debt.');
    }
  }
}
