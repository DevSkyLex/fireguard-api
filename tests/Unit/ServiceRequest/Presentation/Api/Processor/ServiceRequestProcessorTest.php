<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceRequest\Presentation\Api\Processor;

use ApiPlatform\Metadata\Patch;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ServiceRequest\Application\Contract\ServiceRequestView;
use ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\{ChangeServiceRequestCommand, ChangeServiceRequestResult};
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\{ServiceRequestContent, ServiceRequestTarget};
use ServiceRequest\Presentation\Api\Dto\Input\UpdateServiceRequestInput;
use ServiceRequest\Presentation\Api\Operation\ServiceRequestOperations;
use ServiceRequest\Presentation\Api\Processor\ServiceRequestProcessor;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

/**
 * Class ServiceRequestProcessorTest
 *
 * Partial patch fields and revision preconditions retain their exact application meaning.
 *
 * @category UnitTest
 */
final class ServiceRequestProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method partialPatchesPreserveOmittedAndExplicitNullFields
   *
   * @access public
   *
   * @param string $body exact merge-patch body
   * @param string|null $title deserialized optional title
   * @param string|null $priority deserialized optional priority
   * @param array<string,mixed> $changes expected supplied fields
   * @param string|null $header supplied optimistic precondition
   * @param int|null $revision expected parsed precondition
   *
   * @return void
   */
  #[Test]
  #[DataProvider('patchDeclarations')]
  public function partialPatchesPreserveOmittedAndExplicitNullFields(string $body, ?string $title, ?string $priority, array $changes, ?string $header, ?int $revision): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (ChangeServiceRequestCommand $command): bool => 'actor' === $command->actorId && 'organization' === $command->organizationId && 'request' === $command->requestId && 'patch' === $command->action && $revision === $command->expectedRevision && $changes === $command->changes && null === $command->note && null === $command->reason && null === $command->equipmentId))->willReturn($this->requestResult());
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $input = new UpdateServiceRequestInput();
    $input->title = $title;
    $input->priority = $priority;
    $stack = $this->request($body, $header);
    $output = new ServiceRequestProcessor($commands, $actor, $stack)->process($input, new Patch(name:ServiceRequestOperations::PATCH), ['organizationId' => 'organization', 'id' => 'request']);

    self::assertSame('Repair', $output->title);
    self::assertSame(1, $output->revision);
  }

  /**
   * Method patchDeclarations
   *
   * @access public
   *
   * @return iterable<string,array{string,string|null,string|null,array<string,mixed>,string|null,int|null}> exact declared patch and parsed precondition
   */
  public static function patchDeclarations(): iterable
  {
    yield 'priority alone with current revision' => ['{"priority":"high"}', null, 'high', ['priority' => 'high'], '"revision-2"', 2];
    yield 'explicit null title' => ['{"title":null}', null, null, ['title' => null], '"revision-1"', 1];
    yield 'missing revision remains absent' => ['{}', null, null, [], null, null];
    yield 'malformed revision remains stale' => ['{"title":"Changed"}', 'Changed', null, ['title' => 'Changed'], 'revision-2', -1];
    yield 'revision zero remains explicit' => ['{}', null, null, [], '"revision-0"', 0];
  }

  #[Test]
  public function unknownPatchFieldsNeverDispatch(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $processor = new ServiceRequestProcessor($commands, $actor, $this->request('{"equipmentId":"replacement"}', '"revision-1"'));

    $this->expectException(ServiceRequestException::class);
    $this->expectExceptionMessage('Unknown repair request field.');
    $processor->process(new UpdateServiceRequestInput(), new Patch(name:ServiceRequestOperations::PATCH), ['organizationId' => 'organization', 'id' => 'request']);
  }

  /**
   * Method request
   *
   * @access private
   *
   * @param string $body exact merge-patch body
   * @param string|null $header supplied optimistic precondition
   *
   * @return RequestStack current transport context
   */
  private function request(string $body, ?string $header): RequestStack
  {
    $request = Request::create('/service-requests/request', 'PATCH', server:['CONTENT_TYPE' => 'application/merge-patch+json'], content:$body);
    if (null !== $header) {
      $request->headers->set('If-Match', $header);
    }
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  /**
   * Method requestResult
   *
   * @access private
   *
   * @return ChangeServiceRequestResult retained request view
   */
  private function requestResult(): ChangeServiceRequestResult
  {
    $request = ServiceRequest::create('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440002', new ServiceRequestTarget('550e8400-e29b-41d4-a716-446655440003', null, []), new ServiceRequestContent('Repair', 'Repair needed'), new DateTimeImmutable('2026-10-07T12:00:00Z'));

    return new ChangeServiceRequestResult(ServiceRequestView::fromRequest($request));
  }
  // #endregion
}
