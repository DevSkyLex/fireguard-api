<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Image;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Shared\Application\Port\Outbound\FileStoragePort;

use function sprintf;

/**
 * Service OrganizationLogoResizer.
 *
 * Scales an uploaded image down to a single canonical logo variant, preserving
 * aspect ratio, and stores it as WebP via the FileStoragePort.
 *
 * @category Infrastructure Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class OrganizationLogoResizer
{
  // #region Constants
  /**
   * Constant MAX_DIMENSION.
   *
   * Maximum bounding-box dimension in pixels for the stored logo.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int MAX_DIMENSION = 512;

  /**
   * Constant STORAGE_BASE.
   *
   * Base path inside the file storage for organization logo files.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string STORAGE_BASE = 'organization-logos';

  /**
   * Constant WEBP_QUALITY.
   *
   * WebP encoding quality (0–100).
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int WEBP_QUALITY = 85;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FileStoragePort $fileStorage port used to persist the image
   */
  public function __construct(
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method resize.
   *
   * Scales the source image down to the canonical bounding box and stores it
   * as a single WebP file.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier used as storage key
   * @param string $sourceContents the raw binary content of the uploaded image
   *
   * @return void no return value
   */
  public function resize(string $organizationId, string $sourceContents): void
  {
    $manager = new ImageManager(new Driver());
    $image = $manager->read($sourceContents);
    $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
    $encoded = $image->toWebp(self::WEBP_QUALITY);

    $this->fileStorage->write(
      path: self::pathFor($organizationId),
      contents: (string) $encoded,
    );
  }

  /**
   * Method delete.
   *
   * Removes the stored logo for an organization. Silently skips missing files.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return void no return value
   */
  public function delete(string $organizationId): void
  {
    $path = self::pathFor($organizationId);

    if ($this->fileStorage->exists($path)) {
      $this->fileStorage->delete($path);
    }
  }

  /**
   * Method pathFor.
   *
   * Builds the storage path of an organization logo.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return string the storage path
   */
  public static function pathFor(string $organizationId): string
  {
    return sprintf('%s/%s/logo.webp', self::STORAGE_BASE, $organizationId);
  }
  // #endregion
}
