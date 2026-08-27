<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Template\DeleteInterventionTemplate;

use DateTimeImmutable;
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\UseCase\Command\Template\DeleteInterventionTemplate\{DeleteInterventionTemplateCommand, DeleteInterventionTemplateHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;

/**
 * Test DeleteInterventionTemplateHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteInterventionTemplateHandler::class)]
final class DeleteInterventionTemplateHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itThrowsWhenTheTemplateCannotBeFound(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);
    $templates->expects(self::never())->method('delete');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new DeleteInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::never())->method('delete');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    new DeleteInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::never())->method('delete');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(InterventionNotFoundException::withId(self::TEMPLATE_ID)->getMessage());

    new DeleteInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function itDeletesTheTemplateForAnAuthorizedUser(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::once())->method('delete')->with(self::TEMPLATE_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $result = new DeleteInterventionTemplateHandler($templates, $authorization)(self::command());

    self::assertInstanceOf(VoidResult::class, $result);
  }

  private static function command(): DeleteInterventionTemplateCommand
  {
    return new DeleteInterventionTemplateCommand(
      userId: self::USER_ID,
      templateId: self::TEMPLATE_ID,
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
