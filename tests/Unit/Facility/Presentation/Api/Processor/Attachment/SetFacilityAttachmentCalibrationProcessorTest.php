<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Put;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration\{SetFacilityAttachmentCalibrationCommand, SetFacilityAttachmentCalibrationResult};
use Facility\Presentation\Api\Dto\Input\Attachment\SetFacilityAttachmentCalibrationInput;
use Facility\Presentation\Api\Processor\Attachment\SetFacilityAttachmentCalibrationProcessor;
use Facility\Presentation\Api\Service\FacilityRevisionGuard;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, PreconditionRequiredHttpException};

/**
 * Test SetFacilityAttachmentCalibrationProcessorTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetFacilityAttachmentCalibrationProcessor::class)]
final class SetFacilityAttachmentCalibrationProcessorTest extends TestCase
{
  #[Test]
  public function testDispatchesExpectedRevisionAndPreservesCalibrationOutput(): void
  {
    $input = new SetFacilityAttachmentCalibrationInput();
    $input->calibration = ['widthMeters' => 10.0, 'rotationDegrees' => 0.0, 'offsetXMeters' => 0.0, 'offsetZMeters' => 0.0];
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $command): bool => $command instanceof SetFacilityAttachmentCalibrationCommand && 4 === $command->expectedRevision && 'user-id' === $command->userId))->willReturn(
      new SetFacilityAttachmentCalibrationResult('attachment-id', 'floor-id', 'plan.png', 'image/png', 100, null, 5, 'floor_plan', true, 1000, 500, '2026-10-03T00:00:00+00:00', $input->calibration, calibrationBuildingId: 'building-id', calibrationIssue: 'building_changed'),
    );
    $output = $this->processor($bus, '"revision-4"')->process($input, new Put(), ['id' => 'attachment-id']);
    self::assertSame(5, $output->revision);
    self::assertSame($input->calibration, $output->calibration);
    self::assertSame('building-id', $output->calibrationBuildingId);
    self::assertSame('building_changed', $output->calibrationIssue);
  }

  #[Test]
  public function testRequiresCalibrationFieldInsteadOfImplicitlyClearing(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(BadRequestHttpException::class);
    $this->processor($bus, '"revision-1"')->process(new SetFacilityAttachmentCalibrationInput(), new Put(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testRequiresIfMatch(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $input = new SetFacilityAttachmentCalibrationInput();
    $input->calibration = null;
    $this->expectException(PreconditionRequiredHttpException::class);
    $this->processor($bus, null)->process($input, new Put(), ['id' => 'attachment-id']);
  }

  private function processor(CommandBusPort $bus, ?string $ifMatch): SetFacilityAttachmentCalibrationProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user-id', 'test@example.com', 'password', ['ROLE_USER']));
    $stack = new RequestStack();
    $request = new Request();
    if (null !== $ifMatch) {
      $request->headers->set('If-Match', $ifMatch);
    }
    $stack->push($request);

    return new SetFacilityAttachmentCalibrationProcessor($bus, $security, new FacilityRevisionGuard($stack));
  }
}
