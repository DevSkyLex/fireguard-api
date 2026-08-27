<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Label\CreateInterventionLabel;

use DateTimeImmutable;
use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Application\UseCase\Command\Label\CreateInterventionLabel\{CreateInterventionLabelCommand, CreateInterventionLabelHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test CreateInterventionLabelHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateInterventionLabelHandler::class)]
final class CreateInterventionLabelHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itCreatesATrimmedLabelWhenTheUserHasThePermission(): void
  {
    $view = new InterventionLabelView('label-1', self::ORGANIZATION_ID, 'Urgent', '#ff0000', new DateTimeImmutable(), new DateTimeImmutable());
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->expects(self::once())
      ->method('create')
      ->with(self::ORGANIZATION_ID, 'Urgent', '#ff0000')
      ->willReturn($view);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $result = new CreateInterventionLabelHandler($labels, $authorization)(
      new CreateInterventionLabelCommand(self::USER_ID, self::ORGANIZATION_ID, '  Urgent  ', '#ff0000'),
    );

    self::assertSame($view, $result->label);
  }

  #[Test]
  public function itRejectsAUserMissingTheWritePermission(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    new CreateInterventionLabelHandler($labels, $authorization)(
      new CreateInterventionLabelCommand(self::USER_ID, self::ORGANIZATION_ID, 'Urgent', '#ff0000'),
    );
  }

  #[Test]
  public function itRejectsABlankName(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $this->expectException(InterventionValidationException::class);

    new CreateInterventionLabelHandler($labels, $authorization)(
      new CreateInterventionLabelCommand(self::USER_ID, self::ORGANIZATION_ID, '   ', '#ff0000'),
    );
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->expects(self::never())->method('create');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Organization with ID "%s" not found.', self::ORGANIZATION_ID));

    new CreateInterventionLabelHandler($labels, $authorization)(
      new CreateInterventionLabelCommand(self::USER_ID, self::ORGANIZATION_ID, 'Urgent', '#ff0000'),
    );
  }
}
