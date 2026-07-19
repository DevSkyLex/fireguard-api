<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Storage;

use AsyncAws\S3\S3Client;
use InvalidArgumentException;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\{Filesystem, FilesystemOperator};
use League\Flysystem\Local\LocalFilesystemAdapter;

use function array_key_exists;
use function in_array;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function rawurldecode;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Factory FlysystemFactory.
 *
 * Translates the env-driven `STORAGE_DSN` into a configured Flysystem
 * {@see FilesystemOperator}. Two schemes are supported: `local://` for the
 * on-disk fallback used in dev/test, and `s3://` for S3/MinIO-compatible
 * object storage in staging/prod.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FlysystemFactory
{
  // #region Constants
  /**
   * Constant SCHEME_LOCAL.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string SCHEME_LOCAL = 'local';

  /**
   * Constant SCHEME_S3.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string SCHEME_S3 = 's3';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $projectDir the application root, used to resolve relative `local://` paths
   */
  public function __construct(
    private string $projectDir,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Builds the Flysystem operator described by the given DSN.
   *
   * @since 1.0.0
   *
   * @param string $dsn the storage DSN (`local://...` or `s3://...`)
   *
   * @throws InvalidArgumentException if the DSN is malformed or the scheme is unsupported
   *
   * @return FilesystemOperator the configured filesystem operator
   */
  public function create(string $dsn): FilesystemOperator
  {
    if (str_starts_with($dsn, self::SCHEME_LOCAL . '://')) {
      return $this->createLocal(
        rawPath: substr($dsn, strlen(self::SCHEME_LOCAL . '://')),
      );
    }

    if (str_starts_with($dsn, self::SCHEME_S3 . '://')) {
      return $this->createS3(
        dsn: $dsn,
      );
    }

    throw new InvalidArgumentException(
      sprintf('Unsupported or malformed storage DSN "%s".', $dsn),
    );
  }
  // #endregion

  // #region Local Helpers
  /**
   * Method createLocal.
   *
   * Builds a local-disk filesystem operator rooted at the resolved path.
   *
   * @since 1.0.0
   *
   * @param string $rawPath the path segment following `local://`
   *
   * @throws InvalidArgumentException if the path segment is empty
   *
   * @return FilesystemOperator the local filesystem operator
   */
  private function createLocal(string $rawPath): FilesystemOperator
  {
    $path = trim($rawPath);

    if ('' === $path) {
      throw new InvalidArgumentException('Invalid local storage DSN: missing path.');
    }

    return new Filesystem(
      adapter: new LocalFilesystemAdapter(
        location: $this->resolveLocalPath($path),
      ),
    );
  }

  /**
   * Method resolveLocalPath.
   *
   * Resolves a `local://` path against the project root when relative,
   * so the default `local://var/storage` form stays identical to the
   * legacy `shared.file_storage.base_path` location.
   *
   * @since 1.0.0
   *
   * @param string $path the raw path segment
   *
   * @return string the resolved absolute path
   */
  private function resolveLocalPath(string $path): string
  {
    if ($this->isAbsolutePath($path)) {
      return $path;
    }

    return rtrim($this->projectDir, '/\\') . DIRECTORY_SEPARATOR . $path;
  }

  /**
   * Method isAbsolutePath.
   *
   * @since 1.0.0
   *
   * @param string $path the path to inspect
   *
   * @return bool true if the path is already absolute (unix or Windows)
   */
  private function isAbsolutePath(string $path): bool
  {
    return str_starts_with($path, '/') || 1 === preg_match('#^[A-Za-z]:[/\\\\]#', $path);
  }
  // #endregion

  // #region S3 Helpers
  /**
   * Method createS3.
   *
   * Builds an AsyncAws S3 filesystem operator from an `s3://key:secret@bucket?region=...`
   * DSN. Optional `endpoint` (URL-encoded) and `use_path_style_endpoint` query
   * parameters support MinIO and other S3-compatible services.
   *
   * @since 1.0.0
   *
   * @param string $dsn the full `s3://` DSN
   *
   * @throws InvalidArgumentException if a required DSN component is missing
   *
   * @return FilesystemOperator the S3 filesystem operator
   */
  private function createS3(string $dsn): FilesystemOperator
  {
    $parts = parse_url($dsn);

    if (false === $parts || !isset($parts['host']) || '' === $parts['host']) {
      throw new InvalidArgumentException(
        sprintf('Invalid S3 storage DSN "%s": missing bucket.', $dsn),
      );
    }

    $bucket = $parts['host'];
    $accessKeyId = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $secretAccessKey = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';

    if ('' === $accessKeyId || '' === $secretAccessKey) {
      throw new InvalidArgumentException(
        sprintf('Invalid S3 storage DSN "%s": missing credentials.', $dsn),
      );
    }

    /** @var array<string, string> $query */
    $query = [];
    if (isset($parts['query'])) {
      parse_str($parts['query'], $query);
    }

    $region = $query['region'] ?? '';

    if (!is_string($region) || '' === $region) {
      throw new InvalidArgumentException(
        sprintf('Invalid S3 storage DSN "%s": missing "region" query parameter.', $dsn),
      );
    }

    $configuration = [
      'region' => $region,
      'accessKeyId' => $accessKeyId,
      'accessKeySecret' => $secretAccessKey,
    ];

    if (array_key_exists('endpoint', $query) && is_string($query['endpoint']) && '' !== $query['endpoint']) {
      $configuration['endpoint'] = $query['endpoint'];
    }

    if (array_key_exists('use_path_style_endpoint', $query)) {
      $configuration['pathStyleEndpoint'] = $this->isTruthy($query['use_path_style_endpoint']) ? '1' : '0';
    }

    return new Filesystem(
      adapter: new AsyncAwsS3Adapter(
        client: new S3Client($configuration),
        bucket: $bucket,
      ),
    );
  }

  /**
   * Method isTruthy.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw query parameter value
   *
   * @return bool the parsed boolean value
   */
  private function isTruthy(mixed $value): bool
  {
    return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
  }
  // #endregion
}
