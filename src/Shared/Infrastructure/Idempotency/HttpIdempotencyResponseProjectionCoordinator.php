<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use JsonException;
use LogicException;
use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;
use Shared\Infrastructure\Exception\NoHandlerResultException;
use Shared\Infrastructure\Idempotency\ResponseProjection\{HttpIdempotencyResponseProjectionCodec, HttpIdempotencyResponseProjectionState};
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

use function array_is_list;
use function array_values;
use function count;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Adapter HttpIdempotencyResponseProjectionCoordinator.
 *
 * Captures processor prechecks and original postwrite read results inside the primary owning transaction.
 * Authorization precedes activation and replay; cached query objects are never instantiated.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HttpIdempotencyResponseProjectionCoordinator
{
  // #region Constants
  /**
   * Request-local projection boundary.
   */
  private const string ATTRIBUTE = '_fireguard_http_idempotency_projections';

  /**
   * Maximum unique query messages in each receipt phase.
   */
  private const int MAX_QUERIES = 16;

  /**
   * Maximum complete projection bundle bytes.
   */
  private const int MAX_BYTES = 1048576;
  // #endregion

  // #region Properties
  /**
   * @var list<HttpIdempotencyResponseProjectionPort> owner-local factories for postonly output reads
   */
  private array $contributors;
  // #endregion

  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param RequestStack $requests the authorized current HTTP boundary
   * @param HttpIdempotencyResponseProjectionCodec $codec the exact immutable value whitelist codec
   * @param MessageBusInterface $queryBus the underlying read bus, bypassing receipt interception during capture
   * @param iterable<HttpIdempotencyResponseProjectionPort> $contributors owner output query factories
   */
  public function __construct(
    private RequestStack $requests,
    private HttpIdempotencyResponseProjectionCodec $codec,
    private MessageBusInterface $queryBus,
    iterable $contributors = [],
  ) {
    $owners = [];
    foreach ($contributors as $contributor) {
      $owners[] = $contributor;
    }
    $this->contributors = $owners;
  }
  // #endregion

  // #region Methods
  /**
   * Starts collection only after the boundary has applied the live owner authorization gate.
   *
   * @since 1.0.0
   *
   * @param HttpIdempotencyOperation $operation the verified mutation and read schemas
   *
   * @return void
   */
  public function activate(HttpIdempotencyOperation $operation): void
  {
    $request = $this->requests->getCurrentRequest() ?? throw new LogicException('HTTP response projection requires a current authorized request.');
    if (null !== $this->state()) {
      throw new LogicException('HTTP response projection is already active.');
    }
    $state = new HttpIdempotencyResponseProjectionState($operation);
    foreach ($operation->queryProjections as $schema) {
      if (isset($state->schemas[$schema->queryClass])) {
        throw new LogicException('HTTP response projection query ownership is duplicated.');
      }
      if (in_array($schema->queryClass, $operation->freshAuthorizationQueries, true)) {
        throw new LogicException('HTTP response projections and fresh authorization reads must be disjoint.');
      }
      $state->schemas[$schema->queryClass] = $schema;
    }
    if (count($state->schemas) > self::MAX_QUERIES) {
      throw new LogicException('HTTP response projection query schema budget exceeded.');
    }
    $request->attributes->set(self::ATTRIBUTE, $state);
  }

  /**
   * Restores opaque results after activation's current owner gate, without decoding any private result yet.
   *
   * @since 1.0.0
   *
   * @param string $projectionPayload the authenticated receipt's bounded projection payload
   *
   * @return void
   */
  public function replay(string $projectionPayload): void
  {
    $state = $this->state() ?? throw new LogicException('HTTP projection replay requires current owner authorization.');
    if (strlen($projectionPayload) > self::MAX_BYTES) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    try {
      $document = json_decode($projectionPayload, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (!is_array($document) || 1 !== ($document['version'] ?? null) || $state->operation->name !== ($document['operation'] ?? null)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $state->before = $this->restoreEntries($document['before'] ?? null, $state, true);
    $state->after = $this->restoreEntries($document['after'] ?? null, $state, false);
    $state->replay = true;
  }

  /**
   * Intercepts only exact owner-declared queries for the current processor phase.
   *
   * @since 1.0.0
   *
   * @param QueryMessage $query the read requested by the processor or provider
   * @param callable():ResultMessage $dispatch its ordinary read dispatch
   *
   * @return ResultMessage the live precheck or exact original captured query result
   */
  public function dispatch(QueryMessage $query, callable $dispatch): ResultMessage
  {
    $state = $this->state();
    if (null === $state || 'executing' === $state->phase || in_array($query::class, $state->operation->freshAuthorizationQueries, true)) {
      return $dispatch();
    }
    $schema = $state->schemas[$query::class] ?? null;
    if (null === $schema) {
      if ($state->replay || 'after' === $state->phase) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return $dispatch();
    }
    $queryBytes = $this->codec->encodeQuery($query, $schema);
    $key = hash('sha256', $queryBytes);
    if ($state->replay || 'after' === $state->phase) {
      $result = $this->decodeCapturedQuery($state, $query, $key, $queryBytes, $schema);
    } else {
      $result = $dispatch();
      if ($schema->captureBeforePrimary) {
        $this->assertBudget($state->before, $key);
        $state->observed[$key] = $query;
        $state->before[$key] = ['class' => $query::class, 'query' => $queryBytes, 'result' => $this->codec->encodeResult($result, $schema)];
      }
    }

    return $result;
  }

  /**
   * Suspends processor capture during internal mutation handler reads.
   *
   * @since 1.0.0
   *
   * @param CommandMessage $command the exact primary mutation
   *
   * @return void
   */
  public function primaryStarted(CommandMessage $command): void
  {
    $state = $this->state();
    if (null !== $state) {
      if ($command::class !== $state->operation->primaryCommand) {
        throw new LogicException('HTTP response projection requires the exact primary command.');
      }
      $state->command = $command;
      $state->phase = 'executing';
    }
  }

  /**
   * Runs exact declared response reads inside the owning transaction before its receipt is persisted.
   *
   * @since 1.0.0
   *
   * @param ResultMessage $result the primary result captured by the owning handler
   *
   * @return string the bounded opaque precheck and response bundle
   */
  public function beforeCommit(ResultMessage $result): string
  {
    $state = $this->state() ?? throw new LogicException('HTTP response projection requires an active owning transaction.');
    if ($state->replay || 'executing' !== $state->phase || null === $state->command) {
      throw new LogicException('HTTP response projection capture must precede the primary owning commit.');
    }
    $queries = [];
    foreach ($state->observed as $key => $query) {
      if ($state->schemas[$query::class]->refreshBeforeCommit) {
        $queries[$key] = $query;
      }
    }
    $queries = $this->withContributedQueries($queries, $state, $state->command, $result);
    foreach ($queries as $key => $query) {
      $schema = $state->schemas[$query::class];
      $envelope = $this->queryBus->dispatch($query);
      $stamp = $envelope->last(HandledStamp::class);
      if (!$stamp instanceof HandledStamp || !($queryResult = $stamp->getResult()) instanceof ResultMessage) {
        throw NoHandlerResultException::forMessage($query);
      }
      $state->after[$key] = ['class' => $query::class, 'query' => $this->codec->encodeQuery($query, $schema), 'result' => $this->codec->encodeResult($queryResult, $schema)];
    }
    $body = json_encode(['version' => 1, 'operation' => $state->operation->name, 'before' => array_values($state->before), 'after' => array_values($state->after)], JSON_THROW_ON_ERROR);
    if (strlen($body) > self::MAX_BYTES) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $body;
  }

  /**
   * Switches processor output reads to the original postwrite snapshot after command execution or replay.
   *
   * @since 1.0.0
   *
   * @return void
   */
  public function primaryFinished(): void
  {
    $state = $this->state();
    if (null !== $state) {
      $state->phase = 'after';
    }
  }

  /**
   * Removes only the current request's projection state.
   *
   * @since 1.0.0
   *
   * @return void
   */
  public function deactivate(): void
  {
    $this->requests->getCurrentRequest()?->attributes->remove(self::ATTRIBUTE);
  }

  /**
   * Method withContributedQueries
   *
   * Adds the unique owner's declared reads without exceeding the same receipt budget.
   *
   * @access private
   *
   * @param array<string, QueryMessage> $queries observed reads to refresh
   * @param HttpIdempotencyResponseProjectionState $state the authorized projection state
   * @param CommandMessage $command the exact executing primary command
   * @param ResultMessage $result the primary application result
   *
   * @return array<string, QueryMessage> the exact reads to capture before commit
   */
  private function withContributedQueries(array $queries, HttpIdempotencyResponseProjectionState $state, CommandMessage $command, ResultMessage $result): array
  {
    $owner = null;
    foreach ($this->contributors as $contributor) {
      if (!$contributor->supports($state->operation->name)) {
        continue;
      }
      if (null !== $owner) {
        throw new LogicException('HTTP response projection operation ownership is duplicated.');
      }
      $owner = $contributor;
      $contributed = $contributor->queries($state->operation->name, $command, $result);
      if (count($contributed) > self::MAX_QUERIES) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      foreach ($contributed as $query) {
        $schema = $state->schemas[$query::class] ?? throw new IdempotencyDeniedException('result_unavailable');
        $key = hash('sha256', $this->codec->encodeQuery($query, $schema));
        $this->assertBudget($queries, $key);
        $queries[$key] = $query;
      }
    }

    return $queries;
  }

  /**
   * Method decodeCapturedQuery
   *
   * Restores only the exact query identity captured in the current authorized phase.
   *
   * @access private
   *
   * @param HttpIdempotencyResponseProjectionState $state the authorized projection state
   * @param QueryMessage $query the current processor read
   * @param string $key its canonical query digest
   * @param string $queryBytes its encoded query identity
   * @param HttpIdempotencyQueryProjection $schema the owner's explicit result whitelist
   *
   * @return ResultMessage the originally captured read result
   */
  private function decodeCapturedQuery(HttpIdempotencyResponseProjectionState $state, QueryMessage $query, string $key, string $queryBytes, HttpIdempotencyQueryProjection $schema): ResultMessage
  {
    $entry = 'before' === $state->phase ? ($state->before[$key] ?? null) : ($state->after[$key] ?? null);
    if (null === $entry || $entry['class'] !== $query::class || $entry['query'] !== $queryBytes) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $this->codec->decodeResult($entry['result'], $schema);
  }

  /**
   * @since 1.0.0
   *
   * @param mixed $entries one receipt phase's encoded entries
   * @param HttpIdempotencyResponseProjectionState $state the currently authorized schemas
   * @param bool $before whether these entries restore processor prechecks
   *
   * @return array<string, array{class: string, query: string, result: string}> validated opaque exact query entries
   */
  private function restoreEntries(mixed $entries, HttpIdempotencyResponseProjectionState $state, bool $before): array
  {
    if (!is_array($entries) || !array_is_list($entries) || count($entries) > self::MAX_QUERIES) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $restored = [];
    foreach ($entries as $entry) {
      if (!is_array($entry) || !is_string($entry['class'] ?? null) || !is_string($entry['query'] ?? null) || !is_string($entry['result'] ?? null)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $schema = $state->schemas[$entry['class']] ?? null;
      if (null === $schema || ($before && !$schema->captureBeforePrimary)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $key = hash('sha256', $entry['query']);
      if (isset($restored[$key])) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $restored[$key] = ['class' => $entry['class'], 'query' => $entry['query'], 'result' => $entry['result']];
    }

    return $restored;
  }

  /**
   * @since 1.0.0
   *
   * @param array<string, mixed> $entries existing unique entries
   * @param string $key the exact encoded query hash
   *
   * @return void
   */
  private function assertBudget(array $entries, string $key): void
  {
    if (!isset($entries[$key]) && count($entries) >= self::MAX_QUERIES) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * @since 1.0.0
   *
   * @return HttpIdempotencyResponseProjectionState|null the current request's authorized projection context
   */
  private function state(): ?HttpIdempotencyResponseProjectionState
  {
    $state = $this->requests->getCurrentRequest()?->attributes->get(self::ATTRIBUTE);

    return $state instanceof HttpIdempotencyResponseProjectionState ? $state : null;
  }
  // #endregion
}
