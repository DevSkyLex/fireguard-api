<?php

declare(strict_types=1);

namespace Tests\Support\Import;

use Import\Application\Contract\{ImportKindDescriptor, ImportRowInput, ImportRowOutcome, ImportSessionContext};
use Import\Application\Port\Outbound\{ImportKindProcessorPort, ImportRowSessionPort};

/**
 * Fixture RecordingImportKindProcessor.
 *
 * Represents an independently supplied kind and records its per-job sessions.
 *
 * @category Test Support
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RecordingImportKindProcessor implements ImportKindProcessorPort
{
  /**
   * @var list<ImportSessionContext>
   */
  public array $contexts = [];

  /**
   * @var list<ImportRowSessionPort>
   */
  public array $sessions = [];

  /**
   * @var list<ImportRowInput>
   */
  public array $processed = [];

  /**
   * @var list<ImportRowInput>
   */
  public array $restored = [];

  public function __construct(private readonly string $key = 'test_asset')
  {
  }

  public function descriptor(): ImportKindDescriptor
  {
    return new ImportKindDescriptor($this->key, 'organization.test_assets.read', 'organization.test_assets.write', 'test-assets-template.csv', "name\r\n");
  }

  public function startSession(ImportSessionContext $context): ImportRowSessionPort
  {
    $this->contexts[] = $context;
    $session = new class ($this, $context) implements ImportRowSessionPort {
      public function __construct(private readonly RecordingImportKindProcessor $processor, private readonly ImportSessionContext $context)
      {
      }

      public function processRow(ImportRowInput $input): ImportRowOutcome
      {
        $this->processor->processed[] = $input;

        return $this->context->dryRun
          ? new ImportRowOutcome(true, code: 'would_create', message: 'Would create a test asset.')
          : new ImportRowOutcome(true, resourceId: 'test-asset-' . $input->rowNumber);
      }

      public function restoreConfirmedSimulationRow(ImportRowInput $input): void
      {
        $this->processor->restored[] = $input;
      }
    };
    $this->sessions[] = $session;

    return $session;
  }
}
