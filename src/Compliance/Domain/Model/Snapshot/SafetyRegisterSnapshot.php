<?php

declare(strict_types=1);

namespace Compliance\Domain\Model\Snapshot;

use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use DateTimeImmutable;

/**
 * Model SafetyRegisterSnapshot.
 *
 * A dated, immutable archive of the regulatory "registre de sécurité" PDF:
 * who generated it, when, for which scope (whole organization or a single
 * facility), plus the SHA-256 content hash and the byte size proving the
 * stored document has not been altered since generation. The PDF bytes
 * themselves live in file storage under `storagePath` — never in the
 * database. A snapshot is never updated: the archive history is append-only.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SafetyRegisterSnapshot
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshotId $id the snapshot identifier
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide register
   * @param string $generatedAt ISO 8601 datetime the register read-model was generated at
   * @param string $generatedByUserId the user who requested the snapshot
   * @param string $contentHash the SHA-256 hash of the stored PDF bytes
   * @param int $sizeBytes the stored PDF size in bytes
   * @param string $storagePath the file storage key holding the PDF bytes
   * @param DateTimeImmutable $createdAt the persistence timestamp
   */
  private function __construct(
    private SafetyRegisterSnapshotId $id,
    private string $organizationId,
    private ?string $facilityId,
    private string $generatedAt,
    private string $generatedByUserId,
    private string $contentHash,
    private int $sizeBytes,
    private string $storagePath,
    private DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new safety register snapshot.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshotId $id the snapshot identifier
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide register
   * @param string $generatedAt ISO 8601 datetime the register read-model was generated at
   * @param string $generatedByUserId the user who requested the snapshot
   * @param string $contentHash the SHA-256 hash of the stored PDF bytes
   * @param int $sizeBytes the stored PDF size in bytes
   * @param string $storagePath the file storage key holding the PDF bytes
   *
   * @return self the created snapshot
   */
  public static function create(
    SafetyRegisterSnapshotId $id,
    string $organizationId,
    ?string $facilityId,
    string $generatedAt,
    string $generatedByUserId,
    string $contentHash,
    int $sizeBytes,
    string $storagePath,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      facilityId: $facilityId,
      generatedAt: $generatedAt,
      generatedByUserId: $generatedByUserId,
      contentHash: $contentHash,
      sizeBytes: $sizeBytes,
      storagePath: $storagePath,
      createdAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a snapshot from persisted state.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshotId $id the snapshot identifier
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide register
   * @param string $generatedAt ISO 8601 datetime the register read-model was generated at
   * @param string $generatedByUserId the user who requested the snapshot
   * @param string $contentHash the SHA-256 hash of the stored PDF bytes
   * @param int $sizeBytes the stored PDF size in bytes
   * @param string $storagePath the file storage key holding the PDF bytes
   * @param DateTimeImmutable $createdAt the persistence timestamp
   *
   * @return self the reconstituted snapshot
   */
  public static function reconstitute(
    SafetyRegisterSnapshotId $id,
    string $organizationId,
    ?string $facilityId,
    string $generatedAt,
    string $generatedByUserId,
    string $contentHash,
    int $sizeBytes,
    string $storagePath,
    DateTimeImmutable $createdAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      facilityId: $facilityId,
      generatedAt: $generatedAt,
      generatedByUserId: $generatedByUserId,
      contentHash: $contentHash,
      sizeBytes: $sizeBytes,
      storagePath: $storagePath,
      createdAt: $createdAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return SafetyRegisterSnapshotId the snapshot identifier
   */
  public function id(): SafetyRegisterSnapshotId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @return string the organization identifier
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method facilityId.
   *
   * @since 1.0.0
   *
   * @return ?string the facility identifier, or null for an organization-wide register
   */
  public function facilityId(): ?string
  {
    return $this->facilityId;
  }

  /**
   * Method scope.
   *
   * @since 1.0.0
   *
   * @return string `organization` or `facility`
   */
  public function scope(): string
  {
    return null === $this->facilityId ? 'organization' : 'facility';
  }

  /**
   * Method generatedAt.
   *
   * @since 1.0.0
   *
   * @return string ISO 8601 generation datetime
   */
  public function generatedAt(): string
  {
    return $this->generatedAt;
  }

  /**
   * Method generatedByUserId.
   *
   * @since 1.0.0
   *
   * @return string the user who requested the snapshot
   */
  public function generatedByUserId(): string
  {
    return $this->generatedByUserId;
  }

  /**
   * Method contentHash.
   *
   * @since 1.0.0
   *
   * @return string the SHA-256 hash of the stored PDF bytes
   */
  public function contentHash(): string
  {
    return $this->contentHash;
  }

  /**
   * Method sizeBytes.
   *
   * @since 1.0.0
   *
   * @return int the stored PDF size in bytes
   */
  public function sizeBytes(): int
  {
    return $this->sizeBytes;
  }

  /**
   * Method storagePath.
   *
   * @since 1.0.0
   *
   * @return string the file storage key holding the PDF bytes
   */
  public function storagePath(): string
  {
    return $this->storagePath;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the persistence timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }
  // #endregion
}
