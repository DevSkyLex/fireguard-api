<?php

declare(strict_types=1);

namespace Shared\Domain\Attachment;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception InvalidAttachmentException.
 *
 * Thrown by {@see AttachmentConstraints} when an uploaded attachment
 * violates the shared MIME-type or size policy.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidAttachmentException extends DomainException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $message the exception message
   * @param string $reason the violation reason (`mime`, `size` or `count`)
   */
  private function __construct(
    string $message,
    private readonly string $reason,
  ) {
    parent::__construct($message);
  }
  // #endregion

  // #region Methods
  /**
   * Method forMimeType.
   *
   * @static
   *
   * Creates an exception for a disallowed MIME type.
   *
   * @since 1.0.0
   *
   * @param string $mimeType the rejected MIME type
   *
   * @return self the created exception instance
   */
  public static function forMimeType(string $mimeType): self
  {
    return new self(
      sprintf('MIME type "%s" is not allowed for attachments.', $mimeType),
      'mime',
    );
  }

  /**
   * Method forSize.
   *
   * @static
   *
   * Creates an exception for an oversized attachment.
   *
   * @since 1.0.0
   *
   * @param int $size the rejected size in bytes
   * @param int $maxSize the maximum allowed size in bytes
   *
   * @return self the created exception instance
   */
  public static function forSize(int $size, int $maxSize): self
  {
    return new self(
      sprintf('Attachment size %d bytes exceeds the maximum of %d bytes.', $size, $maxSize),
      'size',
    );
  }

  /**
   * Method forCount.
   *
   * @static
   *
   * Creates an exception for a parent record that already carries the
   * maximum number of attachments.
   *
   * @since 1.0.0
   *
   * @param int $currentCount the number of attachments already carried
   * @param int $maxCount the maximum allowed number of attachments
   *
   * @return self the created exception instance
   */
  public static function forCount(int $currentCount, int $maxCount): self
  {
    return new self(
      sprintf(
        'This record already carries %d attachments and may not exceed the maximum of %d.',
        $currentCount,
        $maxCount,
      ),
      'count',
    );
  }

  /**
   * Method reason.
   *
   * Returns the violation reason (`mime`, `size` or `count`).
   *
   * @since 1.0.0
   *
   * @return string the violation reason
   */
  public function reason(): string
  {
    return $this->reason;
  }
  // #endregion
}
