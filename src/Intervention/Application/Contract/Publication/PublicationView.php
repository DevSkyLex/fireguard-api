<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

use DateTimeImmutable;

/**
 * Domain PublicationView.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PublicationView
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the PublicationView class.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $interventionId the intervention id value
   * @param int $interventionRevision the intervention revision value
   * @param string $status the status value
   * @param ?string $error the error value
   * @param DateTimeImmutable $createdAt the created at value
   * @param ?DateTimeImmutable $completedAt the completed at value
   */
  public function __construct(
    public string $id,
    public string $interventionId,
    public int $interventionRevision,
    public string $status,
    public ?string $error,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $completedAt,
  ) {
  }
}
