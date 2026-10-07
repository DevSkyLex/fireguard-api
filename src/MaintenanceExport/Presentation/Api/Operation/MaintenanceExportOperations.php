<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Operation;

/**
 * Class MaintenanceExportOperations
 * Versioned retained export and mapping operation names.
 *
 * @category Operation
 */
final readonly class MaintenanceExportOperations
{
  // #region Constants
  public const string EXPORTS = 'maintenance_exports';

  public const string CREATE = 'create_maintenance_export';

  public const string EXPORT = 'maintenance_export';

  public const string ADJUST = 'adjust_maintenance_export';

  public const string CONFIRM = 'confirm_maintenance_export';

  public const string FILE = 'maintenance_export_file';

  public const string SOURCES = 'maintenance_export_sources';

  public const string REFERENCES = 'maintenance_export_references';

  public const string WRITE_REFERENCE = 'write_maintenance_export_reference';
  // #endregion
}
