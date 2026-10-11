<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Support;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use function dirname;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;

/**
 * Shares the existing exact module exceptions with every architecture gate.
 *
 * @category Architecture Tests Support
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExactDependencyBaseline
{
  /**
   * @param array<non-empty-string, list<non-empty-string>> $pairs Existing class pairs
   */
  public function __construct(public array $pairs)
  {
  }

  /**
   * Load only literal class pairs. Wildcards would turn migration debt into an exemption.
   *
   * @return self Existing module exceptions
   */
  public static function load(): self
  {
    $definition = Yaml::parseFile(dirname(__DIR__) . '/deptrac/modules.baseline.yaml');
    if (!is_array($definition) || !is_array($definition['deptrac'] ?? null)) {
      throw new RuntimeException('The module dependency baseline must declare deptrac.');
    }
    $pairs = $definition['deptrac']['skip_violations'] ?? [];
    if (!is_array($pairs)) {
      throw new RuntimeException('The module dependency baseline must contain class pairs.');
    }
    $validated = [];
    foreach ($pairs as $subject => $targets) {
      if (!self::isClassName($subject) || !is_array($targets)) {
        throw new RuntimeException('A dependency exception must name a literal subject class.');
      }
      $validatedTargets = [];
      foreach ($targets as $target) {
        if (!self::isClassName($target)) {
          throw new RuntimeException('A dependency exception must name a literal target class.');
        }
        $validatedTargets[] = $target;
      }
      $validated[$subject] = $validatedTargets;
    }

    return new self($validated);
  }

  /**
   * Select only exceptions relevant to the layer gate; the module gate still checks every pair.
   *
   * @param array<string, list<string>|null> $ruleset Allowed layer transitions
   *
   * @return array<non-empty-string, list<non-empty-string>> Literal forbidden-layer exceptions
   */
  public function forLayerRuleset(array $ruleset): array
  {
    $filtered = [];
    foreach ($this->pairs as $subject => $targets) {
      $sourceLayer = self::layer($subject);
      if (null === $sourceLayer) {
        continue;
      }
      foreach ($targets as $target) {
        $targetLayer = self::layer($target);
        if (null !== $targetLayer && $targetLayer !== $sourceLayer
          && !in_array($targetLayer, $ruleset[$sourceLayer] ?? [], true)) {
          $filtered[$subject][] = $target;
        }
      }
    }

    return $filtered;
  }

  /**
   * @param non-empty-string $class Literal module class
   *
   * @return string|null Its layer name in deptrac.yaml
   */
  private static function layer(string $class): ?string
  {
    $segments = explode('\\', $class);
    foreach (ModuleCollection::all() as $module) {
      if ($segments[0] !== $module->namespace || !isset($segments[1])) {
        continue;
      }
      foreach (ArchitectureLayer::cases() as $layer) {
        if ($segments[1] === $layer->value) {
          return ('Shared' === $module->namespace ? 'Shared' : '') . $layer->value;
        }
      }
    }

    return null;
  }

  /**
   * @param mixed $value Candidate class name
   *
   * @phpstan-assert-if-true non-empty-string $value
   *
   * @return bool Whether the name is a literal qualified class
   */
  private static function isClassName(mixed $value): bool
  {
    return is_string($value) && 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/D', $value);
  }
}
