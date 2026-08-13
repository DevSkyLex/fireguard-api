<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output\Attachment;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionAttachmentOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAttachmentOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property interventionId.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $interventionId = '';

  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $fileName = '';

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $mimeType = '';

  /**
   * Property size.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public int $size = 0;

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public ?string $label = null;

  /**
   * Property workItemId.
   *
   * The intervention work item this attachment is scoped to, when uploaded
   * with one. Null for a plain intervention-level attachment.
   *
   * @since 1.1.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public ?string $workItemId = null;

  /**
   * Property kind.
   *
   * `file` or `signature` — the typed completion signature captured at
   * submission time (Phase 5d.2). At most one `signature` attachment exists
   * per intervention.
   *
   * @since 1.2.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $kind = 'file';

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public int $revision = 1;

  /**
   * Property uploadedAt.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $uploadedAt = '';
  // #endregion
}
