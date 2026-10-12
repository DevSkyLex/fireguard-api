<?php

declare(strict_types=1);

namespace Import\Application\Service;

use Import\Application\Contract\ImportKindDescriptor;
use Import\Application\Port\Outbound\ImportKindProcessorPort;
use Import\Application\Port\Outbound\Internal\ImportKindRegistryPort;
use LogicException;
use Shared\Domain\Exception\InvalidValueException;

use function array_values;

/**
 * Class ImportKindProcessorRegistry
 *
 * Fails registration when two owners claim the same persisted key.
 *
 * @category Service
 */
final readonly class ImportKindProcessorRegistry implements ImportKindRegistryPort
{
  // #region Properties
  /**
   * Property processors
   *
   * @var array<string, ImportKindProcessorPort>
   */
  private array $processors;

  /**
   * Property metadata
   *
   * @var array<string, ImportKindDescriptor>
   */
  private array $metadata;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<ImportKindProcessorPort> $processors owner contributions
   *
   * @return void
   */
  public function __construct(iterable $processors)
  {
    $registered = [];
    $metadata = [];
    foreach ($processors as $processor) {
      $descriptor = $processor->descriptor();
      if (isset($registered[$descriptor->key])) {
        throw new LogicException('Duplicate import contributor: ' . $descriptor->key);
      }
      $registered[$descriptor->key] = $processor;
      $metadata[$descriptor->key] = $descriptor;
    }
    $this->processors = $registered;
    $this->metadata = $metadata;
  }
  // #endregion

  // #region Methods
  /**
   * Method find
   *
   * @access public
   *
   * @param string $key persisted contributor key
   *
   * @return ?ImportKindProcessorPort available contribution
   */
  public function find(string $key): ?ImportKindProcessorPort
  {
    return $this->processors[$key] ?? null;
  }

  /**
   * Method get
   *
   * @access public
   *
   * @param string $key requested contributor key
   *
   * @return ImportKindProcessorPort available contribution
   */
  public function get(string $key): ImportKindProcessorPort
  {
    return $this->find($key) ?? throw InvalidValueException::because('Unsupported import kind: ' . $key);
  }

  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<ImportKindDescriptor> registered metadata
   */
  public function descriptors(): array
  {
    return array_values($this->metadata);
  }
  // #endregion
}
