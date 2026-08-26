<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\InspectionResponse;

use ApiPlatform\Metadata\{Delete, Patch, Post, Put};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse\DeleteInspectionResponseCommand;
use Inspection\Application\UseCase\Query\Response\GetInspectionResponse\GetInspectionResponseResult;
use Inspection\Domain\Exception\InspectionResponseClientIdAlreadyExistsException;
use Inspection\Presentation\Api\Dto\Input\InspectionResponse\{CreateInspectionResponseInput, PatchInspectionResponseInput};
use Inspection\Presentation\Api\Processor\InspectionResponse\InspectionResponseProcessor;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Test InspectionResponseProcessorTest.
 *
 * The processor no longer persists anything, so what is worth pinning here is
 * what it still decides: the HTTP status of the one condition whose answer
 * depends on the request shape, and the ORDER of its three gates.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionResponseProcessor::class)]
final class InspectionResponseProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';
  // #endregion

  // #region Tests
  /**
   * Method testExistingClientUuidPutReturnsPreconditionFailed.
   *
   * The duplicate arrives wrapped twice — `MessengerRuntimeException` around
   * `HandlerFailedException` around the domain exception — which is exactly
   * how the real bus delivers it. Throwing the bare exception would pass
   * without proving the unwrapping works.
   *
   * @return void no return value
   */
  #[Test]
  public function testExistingClientUuidPutReturnsPreconditionFailed(): void
  {
    $processor = $this->processor(
      $this->requestStack(Request::create('/api/inspection-responses/' . self::RESPONSE_ID, 'PUT', server: ['HTTP_IF_NONE_MATCH' => '*'])),
      commandBus: $this->failingCommandBus(),
    );

    try {
      $processor->process($this->createInput(), new Put(), ['id' => self::RESPONSE_ID]);
      self::fail('Expected ClientResourceAlreadyExistsHttpException.');
    } catch (ClientResourceAlreadyExistsHttpException $exception) {
      self::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatus());
      self::assertSame('A resource with this client identifier already exists.', $exception->getMessage());
    }
  }

  /**
   * Method testExistingClientUuidPostReturnsConflict.
   *
   * Same domain failure, no client-chosen identifier: 409, not 412. This is
   * the whole reason the processor keeps one catch.
   *
   * @return void no return value
   */
  #[Test]
  public function testExistingClientUuidPostReturnsConflict(): void
  {
    $processor = $this->processor(
      $this->requestStack(Request::create('/api/inspection-responses', 'POST')),
      commandBus: $this->failingCommandBus(),
    );

    $input = $this->createInput();
    $input->clientId = self::RESPONSE_ID;

    try {
      $processor->process($input, new Post());
      self::fail('Expected ClientResourceAlreadyExistsHttpException.');
    } catch (ClientResourceAlreadyExistsHttpException $exception) {
      self::assertSame(Response::HTTP_CONFLICT, $exception->getStatus());
    }
  }

  /**
   * Method testUnknownResponseAnswersNotFoundBeforeTheRevisionGuard.
   *
   * A PATCH with no `If-Match` on an id that does not exist must answer 404,
   * not the 428 the revision guard would raise. The order — read, gate,
   * `If-Match` — is a published contract, and reading `If-Match` earlier is
   * the natural way to get it wrong.
   *
   * @return void no return value
   */
  #[Test]
  public function testUnknownResponseAnswersNotFoundBeforeTheRevisionGuard(): void
  {
    $processor = $this->processor(
      $this->requestStack(Request::create('/api/inspection-responses/' . self::RESPONSE_ID, 'PATCH')),
      queryBus: $this->queryBus(new GetInspectionResponseResult()),
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Inspection response not found.');

    $processor->process(new PatchInspectionResponseInput(), new Patch(), ['id' => self::RESPONSE_ID]);
  }

  /**
   * Method testDeleteCarriesTheStoredRevisionIntoTheCommand.
   *
   * The handler re-checks the revision inside its own transaction, so the
   * value it receives has to be the one the processor validated rather than
   * whatever the header said.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteCarriesTheStoredRevisionIntoTheCommand(): void
  {
    $commandBus = new class () implements CommandBusPort {
      /**
       * @var list<CommandMessage>
       */
      public array $dispatched = [];

      public function dispatch(CommandMessage $command): ResultMessage
      {
        $this->dispatched[] = $command;

        return new class () implements ResultMessage {
        };
      }
    };

    $request = Request::create('/api/inspection-responses/' . self::RESPONSE_ID, 'DELETE');
    $request->headers->set('If-Match', '"revision-4"');

    $processor = $this->processor(
      $this->requestStack($request),
      commandBus: $commandBus,
      queryBus: $this->queryBus(new GetInspectionResponseResult($this->view(revision: 4))),
    );

    self::assertNull($processor->process(null, new Delete(), ['id' => self::RESPONSE_ID]));
    self::assertCount(1, $commandBus->dispatched);
    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(DeleteInspectionResponseCommand::class, $command);
    self::assertSame(self::RESPONSE_ID, $command->responseId);
    self::assertSame(4, $command->expectedRevision);
  }
  // #endregion

  // #region Helpers
  /**
   * Method processor.
   *
   * @param RequestStack $requestStack the request stack
   * @param ?CommandBusPort $commandBus the command bus
   * @param ?QueryBusPort $queryBus the query bus
   *
   * @return InspectionResponseProcessor the processor under test
   */
  private function processor(
    RequestStack $requestStack,
    ?CommandBusPort $commandBus = null,
    ?QueryBusPort $queryBus = null,
  ): InspectionResponseProcessor {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return new InspectionResponseProcessor(
      $commandBus ?? $this->createStub(CommandBusPort::class),
      $queryBus ?? $this->createStub(QueryBusPort::class),
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($this->createStub(InterventionResourceGatewayPort::class)),
      new CreationPreconditionGuard($requestStack),
      new RevisionGuard($requestStack),
    );
  }

  /**
   * Method requestStack.
   *
   * @param Request $request the current request
   *
   * @return RequestStack the stack holding it
   */
  private function requestStack(Request $request): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push($request);

    return $requestStack;
  }

  /**
   * Method failingCommandBus.
   *
   * @return CommandBusPort a bus that fails the way the real one does
   */
  private function failingCommandBus(): CommandBusPort
  {
    $domain = InspectionResponseClientIdAlreadyExistsException::withClientId(self::RESPONSE_ID);
    $wrapped = MessengerRuntimeException::wrap(
      new HandlerFailedException(new Envelope(new PatchInspectionResponseInput()), [$domain]),
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($wrapped);

    return $commandBus;
  }

  /**
   * Method queryBus.
   *
   * @param GetInspectionResponseResult $result the result to answer with
   *
   * @return QueryBusPort the stubbed query bus
   */
  private function queryBus(GetInspectionResponseResult $result): QueryBusPort
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($result);

    return $queryBus;
  }

  /**
   * Method view.
   *
   * @param int $revision the stored revision
   *
   * @return InspectionResponseView a stored response
   */
  private function view(int $revision): InspectionResponseView
  {
    $now = new DateTimeImmutable('2026-08-26T10:00:00+00:00');

    return new InspectionResponseView(
      id: self::RESPONSE_ID,
      organizationId: self::ORGANIZATION_ID,
      interventionId: null,
      inspectionId: self::INSPECTION_ID,
      clientId: null,
      recordStatus: 'draft',
      revision: $revision,
      itemKey: 'pressure',
      value: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method createInput.
   *
   * @return CreateInspectionResponseInput a minimal creation payload
   */
  private function createInput(): CreateInspectionResponseInput
  {
    $input = new CreateInspectionResponseInput();
    $input->organization = '/api/organizations/' . self::ORGANIZATION_ID;
    $input->inspection = '/api/inspections/' . self::INSPECTION_ID;
    $input->itemKey = 'pressure';

    return $input;
  }
  // #endregion
}
