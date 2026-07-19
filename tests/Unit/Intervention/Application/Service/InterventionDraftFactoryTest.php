<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Draft\{
  CreateInterventionDraftRequest,
  InterventionDraftWorkItem
};
use Intervention\Application\Contract\Workflow\{InterventionWorkflowMutation, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Application\Service\InterventionDraftFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

use function count;

#[CoversClass(InterventionDraftFactory::class)]
final class InterventionDraftFactoryTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440042';

  private const string ACTOR_USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCreateRoutesInterventionAndWorkItemsThroughTheGateway(): void
  {
    /** @var list<InterventionWorkflowMutation> $mutations */
    $mutations = [];
    $gateway = $this->gatewayCapturing($mutations);

    $factory = new InterventionDraftFactory($gateway, new NullLogger());

    $created = $factory->create(new CreateInterventionDraftRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'inspection_campaign',
      name: 'Campagne extincteurs T3',
      origin: 'maintenance:campaign',
      priority: 'high',
      siteId: 'site-1',
      responsibleId: 'member-1',
      plannedStartAt: new DateTimeImmutable('2026-08-01T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-08-15T18:00:00+00:00'),
      labelIds: ['label-1'],
      workItems: [
        new InterventionDraftWorkItem(action: 'inspection', target: '{"equipmentId":"eq-1"}'),
        new InterventionDraftWorkItem(action: 'inspection', target: '{"equipmentId":"eq-2"}', required: false, assigneeId: 'member-2'),
      ],
      actorUserId: self::ACTOR_USER_ID,
    ));

    self::assertSame(self::INTERVENTION_ID, $created->interventionId);
    self::assertSame(7, $created->number);
    self::assertSame(2, $created->workItemsCount);

    self::assertCount(3, $mutations);

    $interventionMutation = $mutations[0];
    self::assertSame('intervention', $interventionMutation->resource);
    self::assertSame('create', $interventionMutation->action);
    self::assertSame(self::ACTOR_USER_ID, $interventionMutation->userId);
    self::assertSame(self::ORGANIZATION_ID, $interventionMutation->payload['organizationId']);
    self::assertSame('inspection_campaign', $interventionMutation->payload['type']);
    self::assertSame('high', $interventionMutation->payload['priority']);
    self::assertSame('2026-08-01T08:00:00+00:00', $interventionMutation->payload['plannedStartAt']);
    self::assertSame(['label-1'], $interventionMutation->payload['labelIds']);

    $firstItem = $mutations[1];
    self::assertSame('work_item', $firstItem->resource);
    self::assertSame('create', $firstItem->action);
    self::assertSame(self::INTERVENTION_ID, $firstItem->payload['interventionId']);
    self::assertSame('inspection', $firstItem->payload['action']);
    self::assertSame('planned', $firstItem->payload['source']);
    self::assertTrue($firstItem->payload['required']);

    $secondItem = $mutations[2];
    self::assertFalse($secondItem->payload['required']);
    self::assertSame('member-2', $secondItem->payload['assigneeId']);
  }

  #[Test]
  public function testCreateWithoutActorActsAsSystem(): void
  {
    /** @var list<InterventionWorkflowMutation> $mutations */
    $mutations = [];
    $gateway = $this->gatewayCapturing($mutations);

    $factory = new InterventionDraftFactory($gateway, new NullLogger());

    $factory->create(new CreateInterventionDraftRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'inventory',
      name: 'Corrective auto',
      origin: 'automation:auto_create_intervention_on_critical_nc',
    ));

    self::assertSame('system', $mutations[0]->userId, 'a platform actor never matches a member, so the activity is attributed to system');
  }

  #[Test]
  public function testCreateThrowsWhenGatewayReturnsNoView(): void
  {
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('mutate')->willReturn(null);

    $factory = new InterventionDraftFactory($gateway, new NullLogger());

    $this->expectException(RuntimeException::class);

    $factory->create(new CreateInterventionDraftRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'inventory',
      name: 'Sans vue',
      origin: 'test',
    ));
  }

  /**
   * Builds a gateway stub that captures every mutation and answers creations
   * with a minimal intervention view.
   *
   * @param list<InterventionWorkflowMutation> $mutations captured mutations (by reference)
   */
  private function gatewayCapturing(array &$mutations): InterventionWorkflowGatewayPort
  {
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('mutate')->willReturnCallback(
      static function (InterventionWorkflowMutation $mutation) use (&$mutations): InterventionWorkflowView {
        $mutations[] = $mutation;

        if ('intervention' === $mutation->resource) {
          return new InterventionWorkflowView('intervention', self::ORGANIZATION_ID, [
            'id' => self::INTERVENTION_ID,
            'number' => 7,
          ]);
        }

        return new InterventionWorkflowView('work_item', self::ORGANIZATION_ID, [
          'id' => 'work-item-' . count($mutations),
        ]);
      },
    );

    return $gateway;
  }
}
