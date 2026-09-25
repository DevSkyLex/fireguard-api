<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate;

use DateTimeImmutable;
use Intervention\Application\Contract\Template\{InterventionTemplateUpdateRequest, InterventionTemplateView};
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate\{UpdateInterventionTemplateCommand, UpdateInterventionTemplateHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UpdateInterventionTemplateHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateInterventionTemplateHandler::class)]
final class UpdateInterventionTemplateHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itThrowsWhenTheTemplateCannotBeFound(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);
    $templates->expects(self::never())->method('update');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new UpdateInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::never())->method('update');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    new UpdateInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::never())->method('update');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(InterventionNotFoundException::withId(self::TEMPLATE_ID)->getMessage());

    new UpdateInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function itOnlyPatchesFieldsPresentInTheMergePatchBody(): void
  {
    $updated = self::view();
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::once())
      ->method('update')
      ->with(self::callback(static fn (InterventionTemplateUpdateRequest $request): bool => self::TEMPLATE_ID === $request->id
        && 'Renamed template' === $request->identity->name
        && $request->identity->hasName
        && !$request->identity->hasDescription
        && !$request->identity->hasType
        && !$request->planning->hasPriority
        && !$request->planning->hasDuration
        && !$request->defaults->hasSiteId
        && !$request->defaults->hasResponsibleId
        && !$request->collections->hasLabelIds
        && !$request->collections->hasItems))
      ->willReturn($updated);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $result = new UpdateInterventionTemplateHandler($templates, $authorization)(
      new UpdateInterventionTemplateCommand(
        userId: self::USER_ID,
        templateId: self::TEMPLATE_ID,
        name: 'Renamed template',
        description: null,
        type: null,
        priority: null,
        defaultSiteId: null,
        defaultResponsibleId: null,
        duration: null,
        labelIds: null,
        items: null,
        hasName: true,
      ),
    );

    self::assertSame($updated, $result->template);
  }

  #[Test]
  public function itValidatesEveryPatchedFieldAndLeavesAbsentOnesNull(): void
  {
    $updated = self::view();
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::once())
      ->method('update')
      ->willReturnCallback(static function (InterventionTemplateUpdateRequest $request) use ($updated): InterventionTemplateView {
        self::assertSame(self::TEMPLATE_ID, $request->id);
        self::assertNull($request->identity->name);
        self::assertSame('inspection_campaign', $request->identity->type);
        self::assertSame('high', $request->planning->priority);
        self::assertSame('PT2H', $request->planning->duration);
        self::assertSame([[
          'action' => 'Check extinguisher',
          'target' => null,
          'resultResource' => null,
          'required' => true,
          'defaultAssigneeId' => null,
          'estimatedMinutes' => null,
        ]], $request->collections->items);

        return $updated;
      });

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $result = new UpdateInterventionTemplateHandler($templates, $authorization)(
      new UpdateInterventionTemplateCommand(
        userId: self::USER_ID,
        templateId: self::TEMPLATE_ID,
        name: null,
        description: null,
        type: 'inspection_campaign',
        priority: 'high',
        defaultSiteId: null,
        defaultResponsibleId: null,
        duration: 'PT2H',
        labelIds: null,
        items: [[
          'action' => '  Check extinguisher  ',
          'target' => null,
          'resultResource' => null,
          'required' => true,
          'defaultAssigneeId' => null,
        ]],
        hasName: false,
        hasType: true,
        hasPriority: true,
        hasDuration: true,
        hasItems: true,
      ),
    );

    self::assertSame($updated, $result->template);
  }

  private static function command(): UpdateInterventionTemplateCommand
  {
    return new UpdateInterventionTemplateCommand(
      userId: self::USER_ID,
      templateId: self::TEMPLATE_ID,
      name: 'Renamed template',
      description: null,
      type: null,
      priority: null,
      defaultSiteId: null,
      defaultResponsibleId: null,
      duration: null,
      labelIds: null,
      items: null,
      hasName: true,
    );
  }

  private static function view(): InterventionTemplateView
  {
    return new InterventionTemplateView(
      self::TEMPLATE_ID,
      self::ORGANIZATION_ID,
      'Fire safety audit',
      null,
      'inspection_campaign',
      'high',
      null,
      null,
      null,
      [],
      [],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }
}
