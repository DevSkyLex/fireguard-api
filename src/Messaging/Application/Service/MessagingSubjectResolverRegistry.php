<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Domain\Exception\MessagingSubjectNotFoundException;
use Messaging\Domain\ValueObject\MessagingSubjectType;

use function iterator_to_array;

/**
 * Service MessagingSubjectResolverRegistry.
 *
 * Routes a subject type to the tagged resolver adapter that supports it
 * (`!tagged_iterator messaging.subject_resolver`), mirroring
 * `DoctrineInterventionResourceGatewayAdapter::owner()`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingSubjectResolverRegistry
{
  // #region Properties
  /**
   * Property resolvers.
   *
   * @since 1.0.0
   *
   * @var list<MessagingSubjectResolverPort>
   */
  private array $resolvers;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<MessagingSubjectResolverPort> $resolvers the tagged subject resolvers
   */
  public function __construct(iterable $resolvers)
  {
    $this->resolvers = iterator_to_array($resolvers, false);
  }
  // #endregion

  // #region Methods
  /**
   * Method resolve.
   *
   * Resolves a subject through its supporting adapter, throwing when no
   * adapter is registered for the type or when the subject does not exist
   * in the requesting organization.
   *
   * @since 1.0.0
   *
   * @param MessagingSubjectType $subjectType the subject type value
   * @param string $organizationId the requesting organization identifier
   * @param string $subjectId the subject identifier
   *
   * @return MessagingSubjectResolution the resolution result
   */
  public function resolve(MessagingSubjectType $subjectType, string $organizationId, string $subjectId): MessagingSubjectResolution
  {
    foreach ($this->resolvers as $resolver) {
      if ($resolver->supports($subjectType)) {
        $resolution = $resolver->resolve($organizationId, $subjectId);
        if (!$resolution->exists) {
          throw MessagingSubjectNotFoundException::withId($subjectType->value, $subjectId);
        }

        return $resolution;
      }
    }

    throw MessagingSubjectNotFoundException::withId($subjectType->value, $subjectId);
  }
  // #endregion
}
