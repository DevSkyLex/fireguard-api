<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ImportJobOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJobOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property organization.
   *
   * Organization IRI.
   *
   * @since 1.0.0
   */
  public string $organization = '';

  /**
   * Property kind.
   *
   * One of `equipment`, `facility`.
   *
   * @since 1.0.0
   */
  public string $kind = '';

  /**
   * Property status.
   *
   * One of `pending`, `processing`, `completed`, `failed`.
   *
   * @since 1.0.0
   */
  public string $status = '';

  /**
   * Property originalFilename.
   *
   * @since 1.0.0
   */
  public string $originalFilename = '';

  /**
   * Property dryRun.
   *
   * Whether this job validates and reports without provisioning anything.
   *
   * @since 1.0.0
   */
  public bool $dryRun = false;

  /**
   * Property totalRows.
   *
   * @since 1.0.0
   */
  public ?int $totalRows = null;

  /**
   * Property processedRows.
   *
   * @since 1.0.0
   */
  public int $processedRows = 0;

  /**
   * Property successfulRows.
   *
   * @since 1.0.0
   */
  public int $successfulRows = 0;

  /**
   * Property failedRows.
   *
   * @since 1.0.0
   */
  public int $failedRows = 0;

  /**
   * Property errorReport.
   *
   * @since 1.0.0
   *
   * @var list<ImportRowErrorOutput>
   */
  public array $errorReport = [];

  /**
   * Property jobError.
   *
   * @since 1.0.0
   */
  public ?string $jobError = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';

  /**
   * Property startedAt.
   *
   * @since 1.0.0
   */
  public ?string $startedAt = null;

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  public ?string $completedAt = null;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  public string $updatedAt = '';
}
