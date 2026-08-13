<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate;

use DateTimeImmutable;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft};
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Intervention\Application\Port\Outbound\{InterventionLabelPort, InterventionTemplatePort};
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionTemplateInstantiator};
use Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate\{
  InstantiateInterventionTemplateCommand,
  InstantiateInterventionTemplateHandler
};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InstantiateInterventionTemplateHandlerTest.
 *
 * The handler itself is now a thin authorization gate: the shared
 * instantiation logic (people-dropping, label-filtering, `dueAt` derivation)
 * is exercised by `InterventionTemplateInstantiatorTest` instead.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InstantiateInterventionTemplateHandler::class)]
final class InstantiateInterventionTemplateHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  private const string ACTIVE_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c15';

  #[Test]
  public function itThrowsWhenTheTemplateCannotBeFound(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);
    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $this->expectException(InterventionNotFoundException::class);

    $this->handler($templates, $draftFactory, $this->createStub(OrganizationAuthorizationPort::class))(self::command());
  }

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());
    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    $this->handler($templates, $draftFactory, $authorization)(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());
    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(InterventionNotFoundException::withId(self::TEMPLATE_ID)->getMessage());

    $this->handler($templates, $draftFactory, $authorization)(self::command());
  }

  #[Test]
  public function itDelegatesToTheInstantiatorWithTheTemplateOriginAndActorUserId(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 42, 0);
    });

    $result = $this->handler($templates, $draftFactory, $this->grantedAuthorization())(self::command());

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame('intervention:template', $captured->origin);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame('intervention-1', $result->interventionId);
    self::assertSame(42, $result->number);
  }

  #[Test]
  public function itPassesOverridesThroughToTheInstantiator(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($this->template());

    $captured = null;
    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
      $captured = $request;

      return new CreatedInterventionDraft('intervention-1', 1, 0);
    });

    $this->handler($templates, $draftFactory, $this->grantedAuthorization())(
      new InstantiateInterventionTemplateCommand(
        userId: self::USER_ID,
        templateId: self::TEMPLATE_ID,
        name: 'Overridden name',
        siteId: 'site-override',
        responsibleId: 'responsible-override',
      ),
    );

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame('Overridden name', $captured->name);
    self::assertSame('site-override', $captured->siteId);
    self::assertSame('responsible-override', $captured->responsibleId);
  }

  private static function command(): InstantiateInterventionTemplateCommand
  {
    return new InstantiateInterventionTemplateCommand(self::USER_ID, self::TEMPLATE_ID);
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
      null,
      'P14D',
      [],
      [],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }

  private function handler(
    InterventionTemplatePort $templates,
    InterventionDraftFactoryPort $draftFactory,
    OrganizationAuthorizationPort $authorization,
  ): InstantiateInterventionTemplateHandler {
    return new InstantiateInterventionTemplateHandler(
      $templates,
      new InterventionTemplateInstantiator($templates, $draftFactory, $this->labels(), $this->memberPolicy()),
      $authorization,
    );
  }

  private function grantedAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return $authorization;
  }

  private function labels(): InterventionLabelPort
  {
    return $this->createStub(InterventionLabelPort::class);
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
