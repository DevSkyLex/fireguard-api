<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{CreateInspectionCommand, CreateInspectionResult};
use Inspection\Presentation\Api\Dto\Input\Inspection\CreateInspectionInput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard};
use Inspection\Presentation\Api\Processor\Inspection\CreateInspectionProcessor;
use Intervention\Application\Contract\Resource\ClientResourceAlreadyExistsException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, PreconditionRequiredHttpException};

use const DATE_ATOM;

#[CoversClass(CreateInspectionProcessor::class)]
final class CreateInspectionProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440005';

  #[Test]
  public function requiresAuthenticationBeforeDispatch(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(AccessDeniedHttpException::class);
    $this->processor($bus, authenticated: false)->process(new CreateInspectionInput(), new Post(), ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function requiresAnOrganizationBeforeDispatch(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(BadRequestHttpException::class);
    $this->processor($bus)->process(new CreateInspectionInput(), new Post());
  }

  #[Test]
  public function passesActorAndUnresolvedInspectorToTheOwningCommand(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (CreateInspectionCommand $command): bool => self::ORG_ID === $command->organizationId && self::USER_ID === $command->actorUserId
      && null === $command->inspectorUserId && self::INTERVENTION_ID === $command->interventionId,
    ))->willReturn($this->creationResult());
    $input = new CreateInspectionInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;
    $output = $this->processor($bus)->process($input, new Post());
    self::assertSame(self::INSP_ID, $output->id);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $output->intervention);
    self::assertSame('draft', $output->recordStatus);
    self::assertSame(3, $output->revision);
  }

  #[Test]
  public function adoptsTheUriIdentityAndRequiresTheCreationHeader(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (CreateInspectionCommand $command): bool => self::INSP_ID === $command->resourceId && self::INSP_ID === $command->clientId,
    ))->willReturn($this->creationResult());
    $this->processor($bus, header: '*')->process(new CreateInspectionInput(), new Post(), ['id' => self::INSP_ID, 'organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function creationPreconditionPrecedesAuthentication(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(PreconditionRequiredHttpException::class);
    $this->processor($bus, authenticated: false)->process(new CreateInspectionInput(), new Post(), ['id' => self::INSP_ID]);
  }

  #[Test]
  #[DataProvider('duplicateStatuses')]
  public function mapsDuplicateIdentityAccordingToHttpCreationMode(bool $put, int $status): void
  {
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap(new ClientResourceAlreadyExistsException('Already exists.')));

    try {
      $this->processor($bus, header: '*')->process(new CreateInspectionInput(), new Post(), $put ? ['organizationId' => self::ORG_ID, 'id' => self::INSP_ID] : ['organizationId' => self::ORG_ID]);
      self::fail('A duplicate identity must fail.');
    } catch (ClientResourceAlreadyExistsHttpException $exception) {
      self::assertSame($status, $exception->getStatus());
    }
  }

  /**
   * @return iterable<string, array{bool, int}>
   */
  public static function duplicateStatuses(): iterable
  {
    yield 'post' => [false, 409];
    yield 'put' => [true, 412];
  }

  #[Test]
  public function leavesApplicationFailuresForTheCentralExceptionMapper(): void
  {
    $failure = new RuntimeException('Transport unavailable.');
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willThrowException($failure);
    $this->expectExceptionObject($failure);
    $this->processor($bus)->process(new CreateInspectionInput(), new Post(), ['organizationId' => self::ORG_ID]);
  }

  private function processor(CommandBusPort $bus, bool $authenticated = true, ?string $header = null): CreateInspectionProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($authenticated ? new SecurityUser(self::USER_ID, 'user@example.com', 'hash', ['ROLE_USER'], [], true) : null);
    $stack = new RequestStack();
    $request = Request::create('/api/inspections/' . self::INSP_ID, 'PUT');
    if (null !== $header) {
      $request->headers->set('If-None-Match', $header);
    }
    $stack->push($request);

    return new CreateInspectionProcessor($bus, new InspectionOutputFactory(), $security, new CreationPreconditionGuard($stack));
  }

  private function creationResult(): CreateInspectionResult
  {
    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    return new CreateInspectionResult(self::INSP_ID, self::ORG_ID, self::EQUIP_ID, null, 'pass', 'draft', $now->format(DATE_ATOM), 'user', 'John', self::USER_ID, null, null, null, null, $now, $now, self::INTERVENTION_ID, 'draft', 3);
  }
}
