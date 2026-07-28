<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\{
  GenerateInspectionCampaignCommand,
  GenerateInspectionCampaignResult
};
use Maintenance\Domain\Exception\MaintenanceValidationException;
use Maintenance\Presentation\Api\Dto\Input\GenerateInspectionCampaignInput;
use Maintenance\Presentation\Api\Dto\Output\GenerateInspectionCampaignOutput;
use Maintenance\Presentation\Api\Processor\GenerateInspectionCampaignProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

/**
 * Test GenerateInspectionCampaignProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateInspectionCampaignProcessor::class)]
final class GenerateInspectionCampaignProcessorTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testProcessDispatchesTheCommandAndMapsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (GenerateInspectionCampaignCommand $command): bool => self::ORG_ID === $command->organizationId
        && self::USER_ID === $command->actorUserId
        && 'Q1 Campaign' === $command->name
        && '2026-02-01T00:00:00+00:00' === $command->dueBefore->format('c')))
      ->willReturn(new GenerateInspectionCampaignResult('intervention-1', 42, 3));

    $processor = new GenerateInspectionCampaignProcessor($commandBus, $this->security());

    $input = new GenerateInspectionCampaignInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Q1 Campaign';
    $input->dueBefore = '2026-02-01T00:00:00+00:00';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(GenerateInspectionCampaignOutput::class, $output);
    self::assertSame('intervention-1', $output->interventionId);
    self::assertSame(42, $output->number);
    self::assertSame(3, $output->workItemsCount);
  }

  #[Test]
  public function testProcessThrowsBadRequestForAnInvalidDueBefore(): void
  {
    $processor = new GenerateInspectionCampaignProcessor($this->createStub(CommandBusPort::class), $this->security());

    $input = new GenerateInspectionCampaignInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Q1 Campaign';
    $input->dueBefore = 'not-a-date';

    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessMapsValidationExceptionToUnprocessableEntity(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MaintenanceValidationException('No due schedules.'));

    $processor = new GenerateInspectionCampaignProcessor($commandBus, $this->security());

    $input = new GenerateInspectionCampaignInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Q1 Campaign';
    $input->dueBefore = '2026-02-01T00:00:00+00:00';

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsBadRequestForAnUnexpectedBody(): void
  {
    $processor = new GenerateInspectionCampaignProcessor($this->createStub(CommandBusPort::class), $this->security());

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid request body.');

    $processor->process(new stdClass(), new Post());
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new GenerateInspectionCampaignProcessor($this->createStub(CommandBusPort::class), $security);

    $input = new GenerateInspectionCampaignInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Q1 Campaign';
    $input->dueBefore = '2026-02-01T00:00:00+00:00';

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process($input, new Post());
  }

  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
