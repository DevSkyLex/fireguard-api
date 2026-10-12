<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

use InvalidArgumentException;
use Messaging\Application\Contract\Subject\{MessagingSubjectDescriptor, MessagingSubjectResolution};
use Messaging\Application\Port\Inbound\MessagingSubjectCatalogPort;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Domain\Exception\MessagingSubjectNotFoundException;
use Messaging\Domain\ValueObject\MessagingSubjectType;

use function array_values;
use function in_array;
use function ksort;
use function preg_match;
use function sprintf;
use function trim;

/**
 * Service MessagingSubjectResolverRegistry.
 *
 * Builds the registered subject catalog from owner contributors tagged
 * messaging.subject_resolver. Duplicate or malformed keys fail at composition;
 * unknown and out-of-scope subjects fail closed during contextual access.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingSubjectResolverRegistry implements MessagingSubjectCatalogPort
{
  // #region Properties
  /**
   * Property resolvers.
   *
   * @var array<string, MessagingSubjectResolverPort>
   */
  private array $resolvers;

  /**
   * Property subjects.
   *
   * @var array<string, MessagingSubjectDescriptor>
   */
  private array $subjects;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<MessagingSubjectResolverPort> $resolvers the tagged subject contributors
   *
   * @throws InvalidArgumentException when descriptors conflict or lack valid metadata
   */
  public function __construct(iterable $resolvers)
  {
    $indexed = [];
    $subjects = [];
    foreach ($resolvers as $resolver) {
      $descriptor = $resolver->descriptor();
      $type = MessagingSubjectType::from($descriptor->type)->value;
      if (in_array($type, [MessagingSubjectType::CHANNEL, MessagingSubjectType::DIRECT], true)
        || '' === trim($descriptor->label)
        || 1 !== preg_match('/\A[a-z][a-z0-9-]*\z/D', $descriptor->resourceSegment)
        || 1 !== preg_match('/\Aorganization\.[a-z_]+\.read\z/D', $descriptor->requiredReadPermission)) {
        throw new InvalidArgumentException(sprintf('Invalid messaging subject descriptor "%s".', $type));
      }
      if (isset($indexed[$type])) {
        throw new InvalidArgumentException(sprintf('Duplicate messaging subject type "%s".', $type));
      }
      $indexed[$type] = $resolver;
      $subjects[$type] = $descriptor;
    }
    ksort($subjects);
    $this->resolvers = $indexed;
    $this->subjects = $subjects;
  }
  // #endregion

  // #region Methods
  /**
   * Method descriptors.
   *
   * @access public
   *
   * @return list<MessagingSubjectDescriptor> registered descriptors sorted by key
   */
  public function descriptors(): array
  {
    return array_values($this->subjects);
  }

  /**
   * Method find.
   *
   * @access public
   *
   * @param string $type the unchanged subject key
   *
   * @return ?MessagingSubjectDescriptor registered metadata, or null for an unloaded key
   */
  public function find(string $type): ?MessagingSubjectDescriptor
  {
    return $this->subjects[$type] ?? null;
  }

  /**
   * Method resolve.
   *
   * Requires a registered contributor and a subject in the requested organization.
   * The catalog remains authoritative for the read permission.
   *
   * @since 1.0.0
   *
   * @param MessagingSubjectType $subjectType the validated subject key
   * @param string $organizationId the requesting organization identifier
   * @param string $subjectId the subject identifier
   *
   * @return MessagingSubjectResolution the scoped subject resolution
   *
   * @throws MessagingSubjectNotFoundException when no contributor or scoped subject exists
   */
  public function resolve(MessagingSubjectType $subjectType, string $organizationId, string $subjectId): MessagingSubjectResolution
  {
    $resolver = $this->resolvers[$subjectType->value] ?? null;
    if (null === $resolver) {
      throw MessagingSubjectNotFoundException::withId($subjectType->value, $subjectId);
    }
    $resolution = $resolver->resolve($organizationId, $subjectId);
    if (!$resolution->exists) {
      throw MessagingSubjectNotFoundException::withId($subjectType->value, $subjectId);
    }

    return new MessagingSubjectResolution(true, $resolution->label, $this->subjects[$subjectType->value]->requiredReadPermission);
  }
  // #endregion
}
