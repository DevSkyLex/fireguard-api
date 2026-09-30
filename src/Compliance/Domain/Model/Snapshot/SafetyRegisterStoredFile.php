<?php

declare(strict_types=1);

namespace Compliance\Domain\Model\Snapshot;

/** Persisted PDF identity and integrity metadata for a safety register snapshot. */
final readonly class SafetyRegisterStoredFile
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Records the stored PDF path, byte size, and content hash used to identify a safety-register snapshot.
   *
   * @access public
   *
   * @param string $contentHash digest used to verify the stored PDF contents
   * @param int $sizeBytes stored PDF size in bytes
   * @param string $storagePath storage key used to retrieve the PDF
   *
   * @return void
   */
  public function __construct(
    public string $contentHash,
    public int $sizeBytes,
    public string $storagePath,
  ) {
  }
  // #endregion
}
