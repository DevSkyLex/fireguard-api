<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\PublicationView;
use Intervention\Presentation\Api\Factory\PublicationOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PublicationOutputFactoryTest.
 *
 * A publication that is still running has no completion date and no error;
 * both must stay null rather than degrading to an empty string, since the
 * client uses them to decide whether to keep polling.
 *
 * @category Factory Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PublicationOutputFactory::class)]
final class PublicationOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string PUBLICATION_ID = '550e8400-e29b-41d4-a716-446655483001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655483002';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromViewMapsACompletedPublication(): void
  {
    $output = new PublicationOutputFactory()->fromView($this->view(
      status: 'completed',
      completedAt: new DateTimeImmutable('2026-06-02T10:00:00+00:00'),
    ));

    self::assertSame(self::PUBLICATION_ID, $output->id);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $output->intervention);
    self::assertSame(7, $output->interventionRevision);
    self::assertSame('completed', $output->status);
    self::assertNull($output->error);
    self::assertSame('2026-06-01T10:00:00+00:00', $output->createdAt);
    self::assertSame('2026-06-02T10:00:00+00:00', $output->completedAt);
  }

  #[Test]
  public function testFromViewLeavesTheCompletionDateNullWhileTheJobIsPending(): void
  {
    $output = new PublicationOutputFactory()->fromView($this->view());

    self::assertSame('pending', $output->status);
    self::assertNull($output->completedAt);
  }

  #[Test]
  public function testFromViewCarriesTheFailureReason(): void
  {
    $output = new PublicationOutputFactory()->fromView($this->view(
      status: 'failed',
      error: 'Conflicting revision.',
      completedAt: new DateTimeImmutable('2026-06-02T10:00:00+00:00'),
    ));

    self::assertSame('failed', $output->status);
    self::assertSame('Conflicting revision.', $output->error);
  }

  private function view(
    string $status = 'pending',
    ?string $error = null,
    ?DateTimeImmutable $completedAt = null,
  ): PublicationView {
    return new PublicationView(
      id: self::PUBLICATION_ID,
      interventionId: self::INTERVENTION_ID,
      interventionRevision: 7,
      status: $status,
      error: $error,
      createdAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
      completedAt: $completedAt,
    );
  }
  // #endregion
}
