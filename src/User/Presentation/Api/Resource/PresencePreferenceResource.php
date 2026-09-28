<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Patch};
use User\Presentation\Api\Dto\Input\Presence\UpdatePresencePreferenceInput;
use User\Presentation\Api\Dto\Output\Presence\{PresencePreferenceOutput, PresencePreferenceSubscriptionOutput};
use User\Presentation\Api\Operation\PresencePreferenceOperations;
use User\Presentation\Api\Processor\Presence\UpdatePresencePreferenceProcessor;
use User\Presentation\Api\Provider\Presence\{GetPresencePreferenceProvider, GetPresencePreferenceSubscriptionProvider};

/**
 * Service PresencePreferenceResource.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'PresencePreference',
  operations: [
    new Get(name: PresencePreferenceOperations::GET, uriTemplate: '/me/presence-preference', output: PresencePreferenceOutput::class, provider: GetPresencePreferenceProvider::class),
    new Patch(name: PresencePreferenceOperations::UPDATE, uriTemplate: '/me/presence-preference', input: UpdatePresencePreferenceInput::class, output: PresencePreferenceOutput::class, processor: UpdatePresencePreferenceProcessor::class, read: false, status: 200),
    new Get(name: PresencePreferenceOperations::SUBSCRIPTION, uriTemplate: '/me/presence-preference/subscription', output: PresencePreferenceSubscriptionOutput::class, provider: GetPresencePreferenceSubscriptionProvider::class),
  ],
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['presence-preference:read']],
  denormalizationContext: ['groups' => ['presence-preference:write']],
)]
final class PresencePreferenceResource
{
}
