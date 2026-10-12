<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use InvalidArgumentException;
use LogicException;
use Organization\Application\Contract\Settings\{OrganizationSettingsPolicyDefinition, OrganizationSettingsValueType};
use Organization\Application\Port\Inbound\OrganizationSettingsPolicyCatalogPort;
use Organization\Application\Port\Outbound\OrganizationSettingsPolicyContributionPort;

use function array_is_list;
use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Class OrganizationSettingsPolicyRegistry
 *
 * Composes typed owner fields and applies structural validation while leaving historical unknown fields intact.
 *
 * @category Service
 */
final readonly class OrganizationSettingsPolicyRegistry implements OrganizationSettingsPolicyCatalogPort
{
  // #region Properties
  /**
   * @var array<string, OrganizationSettingsPolicyDefinition>
   */
  private array $fields;

  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<OrganizationSettingsPolicyContributionPort> $contributions registered policy owners
   */
  public function __construct(iterable $contributions)
  {
    $fields = [];
    foreach ($contributions as $contribution) {
      foreach ($contribution::definitions() as $definition) {
        if (isset($fields[$definition->key])) {
          throw new LogicException(sprintf('Duplicate organization settings policy "%s".', $definition->key));
        }
        if (null !== $definition->default) {
          $this->validateValue($definition, $definition->default);
        }
        $fields[$definition->key] = $definition;
      }
    }
    $this->fields = $fields;
  }

  // #endregion

  // #region Methods
  /**
   * Method get
   *
   * @access public
   *
   * @return OrganizationSettingsPolicyDefinition
   */
  public function get(string $key): OrganizationSettingsPolicyDefinition
  {
    return $this->fields[$key] ?? throw new InvalidArgumentException(sprintf('Unknown organization settings field "%s".', $key));
  }

  /**
   * Method allDefaults
   *
   * @access public
   *
   * @return array<string, array<string, mixed>>
   */
  public function allDefaults(): array
  {
    $defaults = [];
    foreach ($this->fields as $definition) {
      [$section, $field] = explode('.', $definition->key, 2);
      $defaults[$section][$field] = $definition->default;
    }

    return $defaults;
  }

  /**
   * Method defaults
   *
   * @access public
   *
   * @return array<string, mixed>
   */
  public function defaults(string $section): array
  {
    $defaults = [];
    foreach ($this->fields as $definition) {
      [$ownerSection, $field] = explode('.', $definition->key, 2);
      if ($ownerSection === $section) {
        $defaults[$field] = $definition->default;
      }
    }

    return $defaults;
  }

  /**
   * Method validatePartial
   *
   * @access public
   *
   * @param array<string, mixed> $partial
   */
  public function validatePartial(string $section, array $partial): void
  {
    if ([] === $this->defaults($section)) {
      throw new InvalidArgumentException(sprintf('Unknown organization settings section "%s".', $section));
    }
    foreach ($partial as $field => $value) {
      $definition = $this->get($section . '.' . $field);
      if (null !== $value) {
        $this->validateValue($definition, $value);
      }
    }
  }

  /**
   * Method merge
   *
   * @access public
   *
   * @param array<string, mixed> $current
   * @param array<string, mixed> $partial
   *
   * @return array<string, mixed>
   */
  public function merge(string $section, array $current, array $partial): array
  {
    $this->validatePartial($section, $partial);
    foreach ($partial as $field => $value) {
      $definition = $this->get($section . '.' . $field);
      if (null === $value) {
        if ($definition->nullResets) {
          $current[$field] = $definition->default;
        }

        continue;
      }
      if (is_array($value)) {
        $current[$field] = $this->mergeMap(is_array($current[$field] ?? null) ? $current[$field] : [], $value);
      } else {
        $current[$field] = $value;
      }
    }

    return $current;
  }

  /**
   * Method mergeMap
   *
   * Preserves existing entries and applies null as an explicit map-key removal.
   *
   * @access private
   *
   * @param array<array-key, mixed> $current persisted owner map
   * @param array<array-key, mixed> $partial submitted map changes
   *
   * @return array<array-key, mixed> merged owner map
   */
  private function mergeMap(array $current, array $partial): array
  {
    foreach ($partial as $key => $entry) {
      if (null === $entry) {
        unset($current[$key]);
      } else {
        $current[$key] = $entry;
      }
    }

    return $current;
  }

  /**
   * Method validateValue
   *
   * @access private
   */
  private function validateValue(OrganizationSettingsPolicyDefinition $definition, mixed $value): void
  {
    $valid = match ($definition->type) {
      OrganizationSettingsValueType::BOOLEAN => is_bool($value),
      OrganizationSettingsValueType::INTEGER => is_int($value),
      OrganizationSettingsValueType::NUMBER => is_int($value) || is_float($value),
      OrganizationSettingsValueType::TEXT => is_string($value),
      OrganizationSettingsValueType::INTEGER_MAP, OrganizationSettingsValueType::TEXT_MAP, OrganizationSettingsValueType::OBJECT_MAP => is_array($value) && ([] === $value || !array_is_list($value)),
    };
    if (!$valid) {
      throw new InvalidArgumentException(sprintf('Invalid value for organization settings field "%s".', $definition->key));
    }
    if (is_array($value)) {
      $this->validateMap($definition, $value);
    } else {
      $this->validateBounds($definition, $value);
    }
  }

  /**
   * Method validateMap
   *
   * Checks registered keys before nullable map entries and their numeric bounds.
   *
   * @access private
   *
   * @param OrganizationSettingsPolicyDefinition $definition owner field contract
   * @param array<array-key, mixed> $value submitted owner map
   *
   * @return void
   */
  private function validateMap(OrganizationSettingsPolicyDefinition $definition, array $value): void
  {
    foreach ($value as $key => $entry) {
      if (null !== $definition->allowedKeys && !in_array((string) $key, $definition->allowedKeys, true)) {
        throw new InvalidArgumentException(sprintf('Unknown key "%s" for organization settings field "%s".', (string) $key, $definition->key));
      }
      if (null === $entry) {
        continue;
      }
      $entryValid = match ($definition->type) {
        OrganizationSettingsValueType::INTEGER_MAP => is_int($entry),
        OrganizationSettingsValueType::TEXT_MAP => is_string($entry),
        OrganizationSettingsValueType::OBJECT_MAP => is_array($entry),
        default => true,
      };
      if (!$entryValid) {
        throw new InvalidArgumentException(sprintf('Invalid map entry for organization settings field "%s".', $definition->key));
      }
      $this->validateBounds($definition, $entry);
    }
  }

  /**
   * Method validateBounds
   *
   * @access private
   */
  private function validateBounds(OrganizationSettingsPolicyDefinition $definition, mixed $value): void
  {
    if ((is_int($value) || is_float($value)) && ((null !== $definition->minimum && $value < $definition->minimum) || (null !== $definition->maximum && $value > $definition->maximum))) {
      throw new InvalidArgumentException(sprintf('Value outside bounds for organization settings field "%s".', $definition->key));
    }
  }
  // #endregion
}
