<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Factory\InterventionActivityOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionActivityOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionActivityOutputFactory::class)]
final class InterventionActivityOutputFactoryTest extends TestCase
{
  #[Test]
  public function itMapsACommentActivity(): void
  {
    $view = new InterventionWorkflowView('activity', 'organization-1', [
      'id' => 'activity-1',
      'intervention' => '/api/interventions/intervention-1',
      'kind' => 'comment',
      'event' => 'comment',
      'actor' => '/api/organizations/organization-1/members/member-1',
      'body' => 'Please double-check the panel wiring.',
      'payload' => null,
      'createdAt' => '2026-07-09T13:00:00+00:00',
    ]);

    $output = new InterventionActivityOutputFactory()->fromView($view);

    self::assertSame('activity-1', $output->id);
    self::assertSame('/api/interventions/intervention-1', $output->intervention);
    self::assertSame('comment', $output->kind);
    self::assertSame('comment', $output->event);
    self::assertSame('/api/organizations/organization-1/members/member-1', $output->actor);
    self::assertSame('Please double-check the panel wiring.', $output->body);
    self::assertNull($output->payload);
    self::assertSame('2026-07-09T13:00:00+00:00', $output->createdAt);
  }

  #[Test]
  public function itMapsAStatusChangedSystemActivityWithAPayloadAndNoActor(): void
  {
    $view = new InterventionWorkflowView('activity', 'organization-1', [
      'id' => 'activity-2',
      'intervention' => '/api/interventions/intervention-1',
      'kind' => 'system',
      'event' => 'status_changed',
      'actor' => null,
      'body' => null,
      'payload' => ['from' => 'draft', 'to' => 'planned'],
      'createdAt' => '2026-07-09T13:05:00+00:00',
    ]);

    $output = new InterventionActivityOutputFactory()->fromView($view);

    self::assertSame('system', $output->kind);
    self::assertSame('status_changed', $output->event);
    self::assertNull($output->actor);
    self::assertNull($output->body);
    self::assertSame(['from' => 'draft', 'to' => 'planned'], $output->payload);
  }
}
