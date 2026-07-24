<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateInterval;
use DateTimeImmutable;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft};
use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Application\Contract\Template\{InterventionTemplateItemView, InterventionTemplateView};
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Intervention\Application\Port\Outbound\{InterventionLabelPort, InterventionTemplatePort};
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionTemplateInstantiator};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionTemplateInstantiatorTest.
 *
 * Exercises the shared template-instantiation core (people-dropping,
 * label-filtering, `dueAt` derivation, work item mapping) common to BOTH the
 * API instantiation handler and the recurrence materializer.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionTemplateInstantiator::class)]
final class InterventionTemplateInstantiatorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const string ACTIVE_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c15';

  private const string INACTIVE_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c16';

  private const string EXISTING_LABEL_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c17';

  private const string MISSING_LABEL_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c18';

  #[Test]
  public function itDropsAnInactiveDefaultResponsibleAndAssigneeSilentlyButKeepsActiveOnes(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::once())
      ->method('create')
      ->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
        $captured = $request;

        return new CreatedInterventionDraft('intervention-1', 42, 2);
      });

    $result = $this->instantiator($templates, $draftFactory)->instantiate(self::TEMPLATE_ID, 'intervention:template');

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertNull($captured->responsibleId, 'The inactive default responsible must be dropped, not blocked.');
    self::assertCount(2, $captured->workItems);
    self::assertSame(self::ACTIVE_MEMBER_ID, $captured->workItems[0]->assigneeId, 'The active default assignee must be kept.');
    self::assertNull($captured->workItems[1]->assigneeId, 'The inactive default assignee must be dropped, not blocked.');
    self::assertSame('intervention-1', $result->interventionId);
    self::assertSame(42, $result->number);
  }

  #[Test]
  public function itMapsWorkItemsInPositionOrder(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 2);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(self::TEMPLATE_ID, 'intervention:template');

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame('site_setup', $captured->workItems[0]->action);
    self::assertSame('inspection', $captured->workItems[1]->action);
  }

  #[Test]
  public function itUsesTheGivenOriginAndActorUserId(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(
      self::TEMPLATE_ID,
      'intervention:recurrence',
      actorUserId: 'user-1',
    );

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame('intervention:recurrence', $captured->origin);
    self::assertSame('user-1', $captured->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame('inspection_campaign', $captured->type);
    self::assertSame('high', $captured->priority);
  }

  #[Test]
  public function itDerivesDueAtFromPlannedStartAtAndTemplateDuration(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $plannedStartAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $this->instantiator($templates, $draftFactory)->instantiate(
      self::TEMPLATE_ID,
      'intervention:template',
      plannedStartAt: $plannedStartAt,
    );

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame($plannedStartAt, $captured->plannedStartAt);
    self::assertEquals($plannedStartAt->add(new DateInterval('P14D')), $captured->dueAt);
  }

  #[Test]
  public function itDoesNotDeriveDueAtWhenPlannedStartAtIsMissing(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(self::TEMPLATE_ID, 'intervention:template');

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertNull($captured->dueAt);
  }

  #[Test]
  public function itPrefersOverridesOverTemplateDefaults(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(
      self::TEMPLATE_ID,
      'intervention:template',
      name: 'Overridden name',
      siteId: 'site-override',
      responsibleId: 'responsible-override',
    );

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame('Overridden name', $captured->name);
    self::assertSame('site-override', $captured->siteId);
    self::assertSame('responsible-override', $captured->responsibleId);
  }

  #[Test]
  public function itFallsBackToTheTemplateNameAndDefaultsWhenNoOverrideIsProvided(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(self::TEMPLATE_ID, 'intervention:template');

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame('Fire safety audit', $captured->name);
    self::assertSame('site-1', $captured->siteId);
  }

  #[Test]
  public function itFiltersOutLabelsThatNoLongerBelongToTheOrganization(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(self::TEMPLATE_ID, 'intervention:template');

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame([self::EXISTING_LABEL_ID], $captured->labelIds);
  }

  #[Test]
  public function itThrowsWhenTheTemplateDoesNotExist(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);

    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);

    $this->expectException(InterventionNotFoundException::class);

    $this->instantiator($templates, $draftFactory)->instantiate(self::TEMPLATE_ID, 'intervention:template');
  }

  #[Test]
  public function itTreatsAnUnparseableTemplateDurationAsAbsent(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->customTemplate('not-a-duration', null, null));

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(
      self::TEMPLATE_ID,
      'intervention:template',
      plannedStartAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertNull($captured->dueAt, 'An unparseable template duration must be treated as absent, not block instantiation.');
  }

  #[Test]
  public function itDoesNotDeriveDueAtWhenTheTemplateHasNoDurationDespiteAPlannedStart(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->customTemplate(null, null, null));

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(
      self::TEMPLATE_ID,
      'intervention:template',
      plannedStartAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertNull($captured->dueAt);
  }

  #[Test]
  public function itLeavesResponsibleAndAssigneeNullWhenTheTemplateHasNoDefaults(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->customTemplate('P14D', null, null));

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 1);
    });

    $this->instantiator($templates, $draftFactory)->instantiate(self::TEMPLATE_ID, 'intervention:template');

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertNull($captured->responsibleId, 'A null default responsible must stay null without touching the member policy.');
    self::assertCount(1, $captured->workItems);
    self::assertNull($captured->workItems[0]->assigneeId, 'A null default assignee must stay null without touching the member policy.');
  }

  private function customTemplate(
    ?string $duration,
    ?string $defaultResponsibleId,
    ?string $assigneeId,
  ): InterventionTemplateView {
    return new InterventionTemplateView(
      self::TEMPLATE_ID,
      self::ORGANIZATION_ID,
      'Fire safety audit',
      'A description',
      'inspection_campaign',
      'high',
      'site-1',
      $defaultResponsibleId,
      $duration,
      [],
      [
        new InterventionTemplateItemView('item-1', 0, 'site_setup', null, null, true, $assigneeId),
      ],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }

  private function template(): InterventionTemplateView
  {
    return new InterventionTemplateView(
      self::TEMPLATE_ID,
      self::ORGANIZATION_ID,
      'Fire safety audit',
      'A description',
      'inspection_campaign',
      'high',
      'site-1',
      self::INACTIVE_MEMBER_ID,
      'P14D',
      [self::EXISTING_LABEL_ID, self::MISSING_LABEL_ID],
      [
        new InterventionTemplateItemView('item-1', 0, 'site_setup', null, null, true, self::ACTIVE_MEMBER_ID),
        new InterventionTemplateItemView('item-2', 1, 'inspection', null, null, true, self::INACTIVE_MEMBER_ID),
      ],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }

  private function instantiator(
    InterventionTemplatePort $templates,
    InterventionDraftFactoryPort $draftFactory,
  ): InterventionTemplateInstantiator {
    return new InterventionTemplateInstantiator($templates, $draftFactory, $this->labels(), $this->memberPolicy());
  }

  private function labels(): InterventionLabelPort
  {
    $labels = $this->createStub(InterventionLabelPort::class);
    $labels->method('find')->willReturnCallback(
      fn (string $id): ?InterventionLabelView => self::EXISTING_LABEL_ID === $id
        ? new InterventionLabelView($id, self::ORGANIZATION_ID, 'Urgent', '#ff0000', new DateTimeImmutable(), new DateTimeImmutable())
        : null,
    );

    return $labels;
  }

  private function memberPolicy(): InterventionMemberPolicy
  {
    $active = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::ACTIVE_MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'user-active',
      true,
      new DateTimeImmutable(),
    );

    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findById')->willReturnCallback(
      static fn (OrganizationMemberId $id): ?OrganizationMember => self::ACTIVE_MEMBER_ID === $id->value ? $active : null,
    );

    return new InterventionMemberPolicy($repository);
  }
}
