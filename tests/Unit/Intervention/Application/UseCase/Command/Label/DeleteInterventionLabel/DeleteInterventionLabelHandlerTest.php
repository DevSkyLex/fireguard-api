<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Label\DeleteInterventionLabel;

use DateTimeImmutable;
use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Application\UseCase\Command\Label\DeleteInterventionLabel\{DeleteInterventionLabelCommand, DeleteInterventionLabelHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;

use function sprintf;

/**
 * Test DeleteInterventionLabelHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteInterventionLabelHandler::class)]
final class DeleteInterventionLabelHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  private const string LABEL_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c17';

  #[Test]
  public function itDeletesTheLabelWhenTheUserHasThePermission(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->with(self::LABEL_ID)->willReturn($this->view());
    $labels->expects(self::once())->method('delete')->with(self::LABEL_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $result = new DeleteInterventionLabelHandler($labels, $authorization)(
      new DeleteInterventionLabelCommand(self::USER_ID, self::LABEL_ID),
    );

    self::assertInstanceOf(VoidResult::class, $result);
  }

  #[Test]
  public function itThrowsWhenTheLabelIsNotFound(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn(null);
    $labels->expects(self::never())->method('delete');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $this->expectException(InterventionNotFoundException::class);

    new DeleteInterventionLabelHandler($labels, $authorization)(
      new DeleteInterventionLabelCommand(self::USER_ID, self::LABEL_ID),
    );
  }

  #[Test]
  public function itRejectsAUserMissingTheWritePermission(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn($this->view());
    $labels->expects(self::never())->method('delete');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    new DeleteInterventionLabelHandler($labels, $authorization)(
      new DeleteInterventionLabelCommand(self::USER_ID, self::LABEL_ID),
    );
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn($this->view());
    $labels->expects(self::never())->method('delete');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Intervention with ID "%s" not found.', self::LABEL_ID));

    new DeleteInterventionLabelHandler($labels, $authorization)(
      new DeleteInterventionLabelCommand(self::USER_ID, self::LABEL_ID),
    );
  }

  private function view(): InterventionLabelView
  {
    return new InterventionLabelView(
      self::LABEL_ID,
      self::ORGANIZATION_ID,
      'Urgent',
      '#ff0000',
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }
}
