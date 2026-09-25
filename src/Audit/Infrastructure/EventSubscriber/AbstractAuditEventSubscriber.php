<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\RecordAuditEventCommand;
use Audit\Infrastructure\Service\{AuditActorResolver, AuditPiiSanitizer};
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{DurableEventContextPort, IdempotentConsumerPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

/** Shared request context, actor resolution, PII policy and durable ledger dispatch. */
abstract readonly class AbstractAuditEventSubscriber
{
  private AuditActorResolver $actorResolver;

  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * audit subscriber classes.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param AuditPiiSanitizer $sanitizer the PII sanitizer
   * @param RequestStack $requestStack the request stack
   * @param Security $security the security helper resolving the current actor
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    protected CommandBusPort $commandBus,
    protected AuditPiiSanitizer $sanitizer,
    protected RequestStack $requestStack,
    Security $security,
    #[Autowire(service: 'monolog.logger.security')]
    protected LoggerInterface $logger,
    protected DurableEventContextPort $eventContext,
    #[Autowire(service: 'shared.auth_event_consumer')]
    protected IdempotentConsumerPort $eventConsumer,
  ) {
    $this->actorResolver = new AuditActorResolver($security, $eventContext);
  }

  /**
   * Method recordOrganizationAudit.
   *
   * Builds and dispatches an organization-scoped audit event.
   * The organization ID is always appended to the metadata (the
   * hash-covered source of truth) and mirrored into the dedicated
   * organizationId column so every organization action can be
   * filtered uniformly on an indexed column.
   *
   * @since 1.0.0
   *
   * @param string $action the audit action name
   * @param string $organizationId the organization ID
   * @param string $subjectType the audited subject type
   * @param string $subjectId the audited subject ID
   * @param array<string, mixed> $metadata the action metadata
   * @param DateTimeImmutable $occurredAt when the action happened
   * @param ?ExplicitAuditActor $actor explicit actor carried by the domain event
   */
  protected function recordOrganizationAudit(
    string $action,
    string $organizationId,
    string $subjectType,
    string $subjectId,
    array $metadata,
    DateTimeImmutable $occurredAt,
    ?ExplicitAuditActor $actor = null,
  ): void {
    $context = $this->requestContext();
    $resolvedActor = $this->currentActor($actor?->userId, $actor?->email);
    $metadata['organization_id'] = $organizationId;

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: $action,
      actorType: $resolvedActor['type'],
      actorId: $resolvedActor['id'],
      actorEmail: $this->sanitizer->email($resolvedActor['email']),
      actorEmailHash: $this->sanitizer->emailHash($resolvedActor['email']),
      subjectType: $subjectType,
      subjectId: $subjectId,
      organizationId: $organizationId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta($metadata),
      occurredAt: $occurredAt,
    ));
  }

  /**
   * Method currentActor.
   *
   * Resolves the acting principal: the explicit user ID when the
   * domain event carries one, otherwise the authenticated security
   * user, falling back to system (CLI and async paths).
   *
   * @since 1.0.0
   *
   * @param string|null $explicitUserId actor user ID carried by the domain event
   * @param string|null $explicitEmail actor email carried by the domain event
   *
   * @return array{type: string, id: ?string, email: ?string} the resolved actor
   */
  protected function currentActor(?string $explicitUserId = null, ?string $explicitEmail = null): array
  {
    return $this->actorResolver->resolve($explicitUserId, $explicitEmail);
  }

  /**
   * Method dispatchAuditEvent.
   *
   * Dispatches a RecordAuditEventCommand and swallows errors.
   *
   * @since 1.0.0
   *
   * @param RecordAuditEventCommand $command the command to dispatch
   */
  protected function dispatchAuditEvent(RecordAuditEventCommand $command): void
  {
    try {
      $eventId = $this->eventContext->eventId();
      if (null === $eventId) {
        $this->commandBus->dispatch(command: $command);
      } else {
        $this->eventConsumer->consume(
          $eventId,
          'audit:' . $command->action . ':' . $command->subjectType . ':' . $command->subjectId,
          function () use ($command): void { $this->commandBus->dispatch(command: $command); },
        );
      }
    } catch (Throwable $exception) {
      $this->logger->error('Failed to record audit event', [
        'error' => $exception->getMessage(),
        'action' => $command->action,
      ]);
      if (null !== $this->eventContext->eventId()) {
        throw $exception;
      }
    }
  }

  /**
   * Method requestContext.
   *
   * Extracts request context (ip, user agent, request id).
   *
   * @since 1.0.0
   *
   * @return array{ip: ?string, user_agent: ?string, request_id: ?string}
   */
  protected function requestContext(): array
  {
    $request = $this->requestStack->getCurrentRequest();

    return [
      'ip' => $request?->getClientIp(),
      'user_agent' => $request?->headers->get('User-Agent'),
      'request_id' => $request?->headers->get('X-Request-Id')
        ?? $request?->headers->get('X-Correlation-Id'),
    ];
  }

  /**
   * Method withRequestMeta.
   *
   * Appends request metadata to the event payload.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $metadata the base metadata
   *
   * @return array<string, mixed> the enriched metadata
   */
  protected function withRequestMeta(array $metadata): array
  {
    $context = $this->requestContext();
    if (null !== $context['request_id']) {
      $metadata['request_id'] = $context['request_id'];
    }

    return $metadata;
  }
}
