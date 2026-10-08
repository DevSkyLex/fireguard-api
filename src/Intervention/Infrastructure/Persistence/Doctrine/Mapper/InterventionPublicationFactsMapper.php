<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use Intervention\Application\Contract\Publication\{InterventionEconomicContext, InterventionEquipmentSnapshot, InterventionFactsScopeTooLarge, InterventionPublishedWorkFact};
use UnexpectedValueException;

use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function preg_match;

/**
 * Class InterventionPublicationFactsMapper
 *
 * Decodes only owned immutable dossier fields and leaves incomplete historical identity explicit.
 *
 * @category Mapper
 */
final readonly class InterventionPublicationFactsMapper
{
  // #region Methods
  /**
   * Method context
   *
   * Current identity is accepted only for unpublished work; published snapshots never consult live allocation data.
   *
   * @access public
   *
   * @param array<string,mixed> $row owned intervention source row
   * @param ?array<string,mixed> $snapshot decoded supported dossier
   * @param list<InterventionPublishedWorkFact> $liveItems bounded current task facts
   * @param ?array{id:string,name:string} $liveSite unpublished root site identity
   * @param ?array{id:string,name:string} $liveCustomer unpublished internal client identity
   *
   * @return InterventionEconomicContext non-financial source context
   */
  public function context(array $row, ?array $snapshot, array $liveItems = [], ?array $liveSite = null, ?array $liveCustomer = null): InterventionEconomicContext
  {
    $published = 'published' === $row['status'];
    $report = is_array($snapshot['report'] ?? null) ? $snapshot['report'] : [];
    $items = $liveItems;
    $site = $liveSite;
    $customer = $liveCustomer;
    $state = 'live';
    $plannedStartAt = $row['planned_start_at'];
    $dueAt = $row['due_at'];
    $publishedAt = null;
    $publicationId = null;
    if ($published) {
      $items = null === $snapshot ? [] : $this->snapshotItems($snapshot);
      $site = $this->identity($snapshot['site'] ?? null);
      $customer = $this->identity($snapshot['customer'] ?? null);
      $state = null === $snapshot ? 'snapshot_missing' : 'available';
      $plannedStartAt = $snapshot['plannedStartAt'] ?? $report['plannedStartAt'] ?? null;
      $dueAt = $snapshot['dueAt'] ?? $report['dueAt'] ?? null;
      $publishedAt = $this->date($snapshot['publishedAt'] ?? $row['published_at'] ?? $snapshot['capturedAt'] ?? null);
      $publicationId = $this->nullableString($snapshot['publicationId'] ?? $row['publication_id'] ?? null);
    }

    return new InterventionEconomicContext(
      $this->requiredString($row['id']),
      $this->requiredString($row['organization_id']),
      $this->integer($snapshot['number'] ?? $report['number'] ?? $row['number']),
      $this->requiredString($snapshot['name'] ?? $report['name'] ?? $row['name']),
      $this->requiredString($snapshot['type'] ?? $report['type'] ?? $row['type']),
      $this->requiredString($row['status']),
      $this->integer($snapshot['revision'] ?? $row['revision']),
      $this->date($snapshot['createdAt'] ?? $row['created_at']) ?? throw new UnexpectedValueException('The owned intervention creation time is missing.'),
      $this->date($plannedStartAt),
      $this->date($dueAt),
      $publishedAt,
      $publicationId,
      $state,
      is_int($snapshot['version'] ?? null) ? $snapshot['version'] : null,
      $this->identityComplete($published, $snapshot, $row['site_id'] ?? null, $site, $items),
      $site,
      $customer,
      $items,
    );
  }

  /**
   * Method snapshot
   *
   * Accepts supported original versions and refuses to invent facts for absent or unsupported historical dossiers.
   *
   * @access public
   *
   * @param mixed $value owned JSON column
   *
   * @return ?array<string,mixed> supported retained dossier
   */
  public function snapshot(mixed $value): ?array
  {
    if (is_string($value)) {
      $value = json_decode($value, true);
    }
    if (!is_array($value) || !in_array($value['version'] ?? null, [1, 2], true)) {
      return null;
    }

    /** @var array<string,mixed> $value */
    return $this->minimizeIdentities($value);
  }

  /**
   * Method workFact
   *
   * Maps task identity without deriving validation from scheduling or completion alone.
   *
   * @access public
   *
   * @param array<string,mixed> $item owned current or captured task shape
   * @param ?InterventionEquipmentSnapshot $equipment original or live owner-supplied identity
   * @param ?array{id:string,name:string} $site explicit task site identity
   * @param ?array{id:string,name:string} $customer explicit task client identity
   * @param bool $published whether this task belongs to a supported immutable published dossier
   *
   * @return InterventionPublishedWorkFact task allocation and execution facts
   */
  public function workFact(array $item, ?InterventionEquipmentSnapshot $equipment = null, ?array $site = null, ?array $customer = null, bool $published = false): InterventionPublishedWorkFact
  {
    $target = $this->nullableString($item['target'] ?? null);
    $execution = is_array($item['executionResult'] ?? null) ? $item['executionResult'] : null;
    $resultResource = $this->nullableString($item['resultResource'] ?? null);
    $validationSource = 'validated' === ($execution['state'] ?? null) ? 'execution_result' : null;
    if (null === $validationSource && $published && 'inspection' === $item['action'] && 'completed' === $item['status'] && null !== $resultResource && 1 === preg_match('#^/api/inspections/[^/]+$#', $resultResource)) {
      $validationSource = 'published_inspection';
    }

    /** @var ?array<string,mixed> $execution */
    return new InterventionPublishedWorkFact(
      $this->requiredString($item['id']),
      $this->requiredString($item['action']),
      $this->requiredString($item['status']),
      $target,
      $resultResource,
      $equipment->id ?? $this->equipmentId($target),
      $equipment->site ?? $site,
      $equipment->customer ?? $customer,
      $equipment,
      $execution,
      null !== $validationSource,
      $this->integer($item['spentMinutes'] ?? 0),
      $this->integer($item['evidenceCount'] ?? 0),
      $this->date($item['createdAt'] ?? null),
      $this->date($item['updatedAt'] ?? null),
      $validationSource,
    );
  }

  /**
   * Method equipmentId
   *
   * Preserves both the historical offline JSON target and the canonical equipment IRI.
   *
   * @access public
   *
   * @param ?string $target retained target
   *
   * @return ?string identified asset without live lookup
   */
  public function equipmentId(?string $target): ?string
  {
    if (null === $target) {
      return null;
    }
    if (1 === preg_match('#^/api/equipment/([^/]+)$#', $target, $match)) {
      return $match[1];
    }
    $value = json_decode($target, true);

    return is_array($value) && is_string($value['equipmentId'] ?? null) ? $value['equipmentId'] : null;
  }

  /**
   * Method identity
   *
   * Exposes only stable identifier and name even if an old dossier contains additional contact fields.
   *
   * @access public
   *
   * @param mixed $value retained identity
   *
   * @return ?array{id:string,name:string} minimal valid identity
   */
  public function identity(mixed $value): ?array
  {
    return is_array($value) && is_string($value['id'] ?? null) && is_string($value['name'] ?? null) ? ['id' => $value['id'], 'name' => $value['name']] : null;
  }

  /**
   * Method identityComplete
   *
   * Requires the retained dossier and location identity when a source or task declares them.
   *
   * @access private
   *
   * @param bool $published whether live identity is forbidden
   * @param ?array<string,mixed> $snapshot supported retained dossier
   * @param mixed $siteId declared root location identifier
   * @param ?array{id:string,name:string} $site resolved original or current site
   * @param list<InterventionPublishedWorkFact> $items bounded task facts
   *
   * @return bool whether all declared operational identity is available
   */
  private function identityComplete(bool $published, ?array $snapshot, mixed $siteId, ?array $site, array $items): bool
  {
    if (($published && null === $snapshot) || (null !== $siteId && null === $site)) {
      return false;
    }
    foreach ($items as $item) {
      if (null !== $item->equipmentId && (null === $item->equipmentIdentity || (null !== $item->equipmentIdentity->facilityId && null === $item->equipmentIdentity->site))) {
        return false;
      }
    }

    return true;
  }

  /**
   * Method snapshotItems
   *
   * A version-one asset target remains identifiable but lacks complete captured equipment and per-task location identity.
   *
   * @access private
   *
   * @param array<string,mixed> $snapshot retained dossier
   *
   * @return list<InterventionPublishedWorkFact> immutable task facts
   */
  private function snapshotItems(array $snapshot): array
  {
    $items = $snapshot['workItems'] ?? [];
    if (!is_array($items)) {
      return [];
    }
    if (count($items) > 10000) {
      throw new InterventionFactsScopeTooLarge('The published dossier exceeds 10000 tasks; narrow the source scope.');
    }
    $result = [];
    foreach ($items as $item) {
      if (!is_array($item) || !is_string($item['id'] ?? null) || !is_string($item['action'] ?? null) || !is_string($item['status'] ?? null)) {
        continue;
      }
      $identity = $item['equipmentIdentity'] ?? null;
      $equipment = null;
      if (is_array($identity) && is_string($identity['id'] ?? null) && is_string($identity['type'] ?? null)) {
        $equipment = new InterventionEquipmentSnapshot($identity['id'], $this->nullableString($identity['name'] ?? null), $this->nullableString($identity['assetReference'] ?? null), $identity['type'], $this->nullableString($identity['brand'] ?? null), $this->nullableString($identity['model'] ?? null), $this->nullableString($identity['serialNumber'] ?? null), $this->nullableString($identity['facilityId'] ?? null), $this->identity($identity['site'] ?? null), $this->identity($identity['customer'] ?? null));
      }
      /** @var array<string,mixed> $item */
      $result[] = $this->workFact($item, $equipment, $this->identity($item['site'] ?? null), $this->identity($item['customer'] ?? null), true);
    }

    return $result;
  }

  /**
   * Method date
   *
   * Reads actual retained timestamps; missing fields stay null.
   *
   * @access private
   *
   * @param mixed $value retained time
   *
   * @return ?DateTimeImmutable parsed source time
   */
  private function date(mixed $value): ?DateTimeImmutable
  {
    return is_string($value) && '' !== $value ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : null;
  }

  /**
   * Method minimizeIdentities
   *
   * Preserves operational facts while removing legacy contact payloads from every site/client identity node, without altering stored JSON.
   *
   * @access private
   *
   * @template TKey of array-key
   *
   * @param array<TKey,mixed> $source retained JSON object or list
   *
   * @return array<TKey,mixed> retained operational facts with minimal identity nodes
   */
  private function minimizeIdentities(array $source): array
  {
    foreach ($source as $key => $value) {
      if ('customer' === $key || 'site' === $key) {
        $source[$key] = $this->identity($value);
      } elseif (is_array($value)) {
        $source[$key] = $this->minimizeIdentities($value);
      }
    }

    return $source;
  }

  /**
   * Method nullableString
   *
   * @access private
   *
   * @param mixed $value owned field
   *
   * @return ?string actual scalar value
   */
  private function nullableString(mixed $value): ?string
  {
    return is_string($value) ? $value : null;
  }

  /**
   * Method requiredString
   *
   * Refuses corrupt required fields instead of constructing a misleading source identity.
   *
   * @access private
   *
   * @param mixed $value owned required field
   *
   * @return string validated scalar
   */
  private function requiredString(mixed $value): string
  {
    return is_string($value) ? $value : throw new UnexpectedValueException('A required intervention source field is not a string.');
  }

  /**
   * Method integer
   *
   * @access private
   *
   * @param mixed $value database integer or JSON integer
   *
   * @return int normalized integer
   */
  private function integer(mixed $value): int
  {
    return is_int($value) || is_string($value) ? (int) $value : 0;
  }
  // #endregion
}
