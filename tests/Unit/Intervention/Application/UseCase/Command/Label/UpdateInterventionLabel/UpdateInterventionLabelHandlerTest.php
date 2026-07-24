<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel;

use DateTimeImmutable;
use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel\{UpdateInterventionLabelCommand, UpdateInterventionLabelHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UpdateInterventionLabelHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateInterventionLabelHandler::class)]
final class UpdateInterventionLabelHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  private const string LABEL_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c17';

  #[Test]
  public function itUpdatesTheTrimmedNameAndColorWhenTheUserHasThePermission(): void
  {
    $updated = new InterventionLabelView(self::LABEL_ID, self::ORGANIZATION_ID, 'Critical', '#00ff00', new DateTimeImmutable(), new DateTimeImmutable());
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn($this->existingLabel());
    $labels->expects(self::once())
      ->method('update')
      ->with(self::LABEL_ID, 'Critical', '#00ff00', true, true)
      ->willReturn($updated);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new UpdateInterventionLabelHandler($labels, $authorization)(
      new UpdateInterventionLabelCommand(self::USER_ID, self::LABEL_ID, '  Critical  ', '#00ff00', true, true),
    );

    self::assertSame($updated, $result->label);
  }

  #[Test]
  public function itLeavesTheNameNullWhenTheNameFieldIsAbsent(): void
  {
    $updated = new InterventionLabelView(self::LABEL_ID, self::ORGANIZATION_ID, 'Urgent', '#123456', new DateTimeImmutable(), new DateTimeImmutable());
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn($this->existingLabel());
    $labels->expects(self::once())
      ->method('update')
      ->with(self::LABEL_ID, null, '#123456', false, true)
      ->willReturn($updated);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new UpdateInterventionLabelHandler($labels, $authorization)(
      new UpdateInterventionLabelCommand(self::USER_ID, self::LABEL_ID, null, '#123456', false, true),
    );

    self::assertSame($updated, $result->label);
  }

  #[Test]
  public function itThrowsWhenTheLabelDoesNotExist(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn(null);
    $labels->expects(self::never())->method('update');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionNotFoundException::class);

    new UpdateInterventionLabelHandler($labels, $authorization)(
      new UpdateInterventionLabelCommand(self::USER_ID, self::LABEL_ID, 'Critical', '#00ff00', true, true),
    );
  }

  #[Test]
  public function itRejectsAUserMissingTheWritePermission(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn($this->existingLabel());
    $labels->expects(self::never())->method('update');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    new UpdateInterventionLabelHandler($labels, $authorization)(
      new UpdateInterventionLabelCommand(self::USER_ID, self::LABEL_ID, 'Critical', '#00ff00', true, true),
    );
  }

  #[Test]
  public function itRejectsABlankNameWhenTheNameFieldIsPresent(): void
  {
    $labels = $this->createMock(InterventionLabelPort::class);
    $labels->method('find')->willReturn($this->existingLabel());
    $labels->expects(self::never())->method('update');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionValidationException::class);

    new UpdateInterventionLabelHandler($labels, $authorization)(
      new UpdateInterventionLabelCommand(self::USER_ID, self::LABEL_ID, '   ', '#00ff00', true, true),
    );
  }

  private function existingLabel(): InterventionLabelView
  {
    return new InterventionLabelView(self::LABEL_ID, self::ORGANIZATION_ID, 'Urgent', '#ff0000', new DateTimeImmutable(), new DateTimeImmutable());
  }
}
