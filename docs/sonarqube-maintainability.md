# Triage des issues Maintenability SonarQube — API `develop`

Le scan du commit `a0b87097508456e28bde666e96b6ff9c206451b6` (23 septembre 2026)
comptait 889 issues Maintenability ouvertes. Ce relevé est un point de départ :
les issues et leurs emplacements sont à revérifier après chaque analyse de `develop`.

Chaque PR de correction cite les clés des issues traitées et les tests qui protègent
le comportement. Les décisions ci-dessous concernent uniquement les alertes qui
restent dans le code :

- **False Positive** : le diagnostic de la règle est contredit par le code ou par
  le contrat vérifié. La justification explique le cas particulier et apporte
  une preuve reproductible.
- **Accepted** : le diagnostic est exact, mais la modification affaiblirait un
  invariant ou rendrait le code moins clair. La justification décrit ce risque
  concret et les protections existantes.

Une fréquence élevée, une note A ou le coût de correction ne suffisent pour
aucune de ces décisions. Ne pas changer la sévérité, désactiver la règle ou
ajouter `NOSONAR` pour solder une issue. Appliquer les décisions uniquement au
projet `fireguard-api-develop`, après revue du cas et commentaire dans SonarQube.

## Décisions justifiées

| Clé Sonar | Règle et emplacement | Décision | Preuve et justification | Commit / revue |
| --- | --- | --- | --- | --- |

Aucune décision **False Positive** ou **Accepted** n'est établie sur l'API
`develop` au 25 septembre 2026.

## Inventaire `php:S107` à examiner individuellement

Les cas ci-dessous restent **ouverts** dans SonarQube. La signature longue est
réelle : aucun n'est un faux positif démontré et **aucune résolution Accepted
n'est proposée à ce stade**. Chaque ligne relève seulement les champs à
préserver pendant une correction. Les méthodes `Inspection::edit` et
`Intervention::edit` doivent être réexaminées avec un patch typé ; les
`reconstitute` ne valident pas nécessairement les combinaisons de champs déjà
persistées. Les lignes sont celles du scan initial de `develop`, avant
déplacement par les corrections.

| Clé Sonar | Emplacement et méthode | Champs à préserver | Statut |
| --- | --- | --- | --- |
| `3900029f-ee43-46a2-ae0c-59da51b649e1` | `src/Approval/Domain/Model/ApprovalRequest/ApprovalRequest.php:160`, `reconstitute` | Restaure simultanément le demandeur, le décideur, l'échéance, les dates de décision et d'exécution et l'erreur d'exécution. | En attente de correction/revue |
| `10587750-9b24-429f-8d37-a5278b494404` | `src/Assistant/Domain/Model/Message/AssistantMessage.php:178`, `reconstitute` | Restaure le statut de génération et les champs de tentative (`attemptId`, numéro, séquence, échéance), en plus du corps et de l'erreur. | En attente de correction/revue |
| `a9a3a4a8-e593-4255-9d27-25954fe05210` | `src/Assistant/Domain/Model/Thread/AssistantThread.php:120`, `reconstitute` | Réhydrate l'identité du fil avec son organisation et son membre propriétaire, puis les dates de création, mise à jour et dernier message. | En attente de correction/revue |
| `d85ee7d0-e8c7-4f04-9169-60e19086a41e` | `src/Billing/Domain/Model/Subscription/Subscription.php:117`, `reconstitute` | Restaure ensemble statut Stripe, identifiant d'abonnement, plan, cadence, fin de période et `cancelAtPeriodEnd`. | En attente de correction/revue |
| `860119cc-60ae-422c-8e28-f3e05943053e` | `src/Calendar/Domain/Model/Event/CalendarEvent.php:145`, `reconstitute` | Conserve séparément début, fin nullable, `allDay`, rattachement facultatif à un site et dates de persistance. | En attente de correction/revue |
| `9983b11a-54cd-441b-92ca-ea66e7a2748e` | `src/Compliance/Domain/Model/Snapshot/SafetyRegisterSnapshot.php:119`, `reconstitute` | Réhydrate une référence immuable vers un PDF avec son `contentHash` SHA-256, sa taille et sa clé de stockage, ainsi que son périmètre organisation/site. | En attente de correction/revue |
| `85a2084c-85a7-427b-955a-cc4501f71a4a` | `src/Equipment/Domain/Model/Attachment/EquipmentAttachment.php:106`, `reconstitute` | Restaure l'identifiant de l'équipement propriétaire, le nom et la clé du fichier, son type MIME, sa taille et sa date de dépôt. | En attente de correction/revue |
| `190d899f-9085-4e86-a208-853318e89200` | `src/Equipment/Domain/Model/Equipment/CanonicalEquipment.php:145`, `reconstitute` | Restitue le statut du record (`draft`/`published`), son `interventionId`, sa révision et ses champs d'inventaire dans un seul instantané. | En attente de correction/revue |
| `a138a119-bc1f-40da-aac3-6286cb0dfe06` | `src/Equipment/Domain/Model/Equipment/Equipment.php:152`, `reconstitute` | Préserve ensemble l'état opérationnel, l'installation, la mise en service, la position sur plan et le rattachement à un site. | En attente de correction/revue |
| `3b04e799-65c5-48ff-b9f5-611be8591f59` | `src/Equipment/Domain/Model/MaintenanceLog/EquipmentMaintenanceLog.php:160`, `reconstitute` | Un log issu d'une intervention conserve l'ID, le numéro et l'action de celle-ci avec la source et les instants de maintenance. | En attente de correction/revue |
| `b910d415-0a85-4286-85f5-984a1ee97ce6` | `src/Facility/Domain/Model/Attachment/FacilityAttachment.php:133`, `reconstitute` | Restaure type de pièce jointe, dimensions d'image et `isPrimaryPlan` en plus du fichier stocké. | En attente de correction/revue |
| `fa6745ae-12c7-46bd-80da-8cd04fa92a7b` | `src/Facility/Domain/Model/Facility/CanonicalFacility.php:126`, `reconstitute` | Restitue hiérarchie (`parentFacilityId`, `levelIndex`), coordonnées, statut du record, `interventionId` et révision dans l'instantané canonique. | En attente de correction/revue |
| `8350a00c-ad5b-47b6-90aa-490a05cd0f5c` | `src/Facility/Domain/Model/Facility/Facility.php:156`, `reconstitute` | Réhydrate le site avec son parent, son niveau, ses coordonnées et sa géométrie sur plan en conservant les normalisations `code`, `address`, `metadata`, `levelIndex`. | En attente de correction/revue |
| `b90f56ca-6547-47e2-bcf8-ed4da01fb8fc` | `src/Facility/Domain/Model/MetadataField/FacilityMetadataField.php:141`, `reconstitute` | Conserve type de champ, indicateur obligatoire, options, unité et restriction de type de site. | En attente de correction/revue |
| `ed939f6a-2408-4a7e-ade9-035c3dd603a5` | `src/Inspection/Domain/Model/Attachment/InspectionAttachment.php:117`, `reconstitute` | Réhydrate la pièce avec son inspection parente et son éventuelle non-conformité liée, plus la clé, le MIME, la taille et la date du fichier. | En attente de correction/revue |
| `84766d2d-77ce-421e-a3d1-f05d8919920f` | `src/Inspection/Domain/Model/Checklist/Checklist.php:138`, `reconstitute` | La version, la liste des items, le statut archivé, le code de référence et `previousChecklistId` constituent la version exacte utilisée par les inspections historiques. | En attente de correction/revue |
| `06ced301-1e08-49be-8fb5-7b292ff2d731` | `src/Inspection/Domain/Model/Inspection/CanonicalInspection.php:136`, `reconstitute` | Conserve séparément `recordStatus` (brouillon d'intervention ou publié), statut d'inspection, résultat et révision. | En attente de correction/revue |
| `c4317fde-e647-4fb9-8e05-b8b0e6356a6a` | `src/Inspection/Domain/Model/Inspection/Inspection.php:153`, `reconstitute` | Restaure inspecteur, équipement, site, checklist, résultat, statut et instant réel d'inspection, indépendamment des dates de création/modification. | En attente de correction/revue |
| `fdf92560-f1d4-4235-be90-dee3963f3911` | `src/Inspection/Domain/Model/NonConformity/NonConformity.php:138`, `reconstitute` | Préserve gravité, statut, échéance et date de résolution liés à l'inspection. | En attente de correction/revue |
| `8ad8b66e-dac6-4895-b156-2972d5582609` | `src/Inspection/Domain/Model/Response/InspectionResponse.php:140`, `reconstitute` | Réhydrate la réponse avec son item, sa valeur mixte, son éventuel `clientId` de synchronisation, son `interventionId`, son statut et sa révision. | En attente de correction/revue |
| `0ce15456-a0e9-4d8c-a1c6-e147582a642f` | `src/Intervention/Domain/Model/Attachment/InterventionAttachment.php:123`, `reconstitute` | Conserve le rattachement facultatif à un `workItemId` et le type (`FILE` ou signature de complétion) avec l'intervention et les métadonnées du fichier. | En attente de correction/revue |
| `33acacc2-97e5-4f87-a928-3d41939a4e39` | `src/Intervention/Domain/Model/Intervention/Intervention.php:150`, `reconstitute` | Restaure les participants, le responsable, les bornes du planning, la priorité, le statut, la note de revue et la révision dans le même état persistant. | En attente de correction/revue |
| `d0b8d375-a323-48de-ba66-85ece7f48730` | `src/Inspection/Domain/Model/Inspection/Inspection.php:257`, `edit` | Les sept couples `has*`/valeur distinguent un champ absent d'un `null` explicite lors de l'édition d'un brouillon ; `null` efface notamment site, checklist, notes ou signature. | En attente de correction/revue |
| `52c7e0b6-150b-45b4-9f2b-ad71cabbc5b1` | `src/Intervention/Domain/Model/Intervention/Intervention.php:279`, `edit` | Les champs d'édition comprennent les drapeaux de présence pour nom, description, site, responsable, participants, priorité, dates, note et statut demandé ; la politique de transition reste un argument distinct. | En attente de correction/revue |
| `f0db98dd-7b7c-44ca-a699-07a0ebda6bee` | `src/Messaging/Domain/Model/Attachment/MessagingAttachment.php:130`, `reconstitute` | Une pièce de message conserve à la fois `messageId`, `conversationId`, `organizationId` et l'auteur de dépôt, en plus de la clé du fichier. | En attente de correction/revue |
| `5d645677-6b9f-48c0-968b-e77d5327c30b` | `src/Messaging/Domain/Model/Conversation/Conversation.php:181`, `reconstitute` | La conversation peut être liée à un sujet, un canal, une équipe ou un parent, avec visibilité, archivage, compteur et dernier message persistés. | En attente de correction/revue |
| `409efd94-c603-4997-8a52-171848bcb4a5` | `src/Messaging/Domain/Model/Message/Message.php:167`, `reconstitute` | Réhydrate édition, suppression avec auteur, épinglage avec auteur, réponse parente, compteur de réponses et références de message. | En attente de correction/revue |
| `a15590ad-bd12-4b08-a0d8-bc0f056baf17` | `src/Notification/Domain/Model/Notification/Notification.php:133`, `reconstitute` | Les canaux, destinataires utilisateur/email, organisation, payload, état lu et `readAt` restent ceux de la ligne persistée. | En attente de correction/revue |
| `2c9e7b3c-a6ff-49c8-806f-39d91100b373` | `src/Onboarding/Domain/Model/OrganizationOnboardingSession/OrganizationOnboardingSession.php:149`, `reconstitute` | La reprise d'un onboarding dépend des étapes terminées, sautées, de la pile de rollback, de l'historique, de l'étape suivante et de la raison de blocage. | En attente de correction/revue |
| `964e4d46-7648-4d7f-8de3-6e0b19e4584c` | `src/Organization/Domain/Model/Organization/Organization.php:178`, `reconstitute` | Réhydrate statut et activité historiques, propriétaire/créateur, slug, plan, paramètres et identité légale (pays, type, immatriculation, TVA). | En attente de correction/revue |
| `4ca149bd-0e50-40a7-983a-6c3b9462ca0b` | `src/Organization/Domain/Model/OrganizationInvitation/OrganizationInvitation.php:126`, `reconstitute` | Conserve hash du jeton, échéance, statut et couples date/auteur d'acceptation ou de révocation. | En attente de correction/revue |
| `95beaf7c-abf3-4437-97e8-4f220d9cc65a` | `src/Organization/Domain/Model/Plan/Plan.php:147`, `reconstitute` | Préserve les quotas, activation, plan par défaut, ordre de tri et dates tels que stockés. | En attente de correction/revue |
| `4d206938-ca59-4efe-8849-65c6a1cab3cf` | `src/Authorization/Domain/Model/Role/Role.php:122`, `reconstitute` | Restaure le caractère système, le tenant facultatif et la liste de permissions dans un rôle existant. | En attente de correction/revue |
| `b01a3c75-ce48-46d3-85f7-b707dd7ecd21` | `src/Import/Domain/Model/ImportJob/ImportJob.php:158`, `reconstitute` | La reprise d'un import exige statut, compteurs de lignes, rapport d'erreurs, échéances de début/fin, mode `dryRun` et ID du job confirmé. | En attente de correction/revue |
| `56dc4358-ea43-4ce5-b4e6-4c2ae10486da` | `src/Otp/Domain/Model/Otp.php:576`, `reconstitute` | Réhydrate le hash du code, la finalité, le canal, le destinataire, l'expiration, le nombre d'essais et `verifiedAt` ; la méthode reconstruit `OtpCode` depuis le hash sans connaître le code clair. | En attente de correction/revue |
| `da2a3456-14d4-4240-832f-e0ac7c79d66c` | `src/Otp/Domain/Model/Totp/TotpEnrollment.php:145`, `reconstitute` | Distingue secret actif/confirmé, secret en attente, compteur d'essais et verrou de désactivation. | En attente de correction/revue |
| `63c101e4-6580-4afa-91b3-a49bfcfd5124` | `src/TrustedDevice/Domain/Model/TrustedDevice/TrustedDevice.php:225`, `reconstitute` | Restitue le hash du jeton, l'empreinte de l'appareil, expiration, dernière utilisation et révocation ; elle reconstruit `DeviceToken` depuis le hash. | En attente de correction/revue |
| `c346a1f9-0deb-4d4a-b839-9b78c5fa0a03` | `src/User/Domain/Model/EmailChange/EmailChangeRequest.php:140`, `restore` | Associe l'ancien et le nouvel email, le hash du jeton, l'échéance et `confirmedAt` à un utilisateur. | En attente de correction/revue |
| `b7fb572f-3696-4154-8a22-cde69a12c2aa` | `src/Webhook/Domain/Model/Delivery/WebhookDelivery.php:142`, `reconstitute` | Conserve payload, nombre d'essais, dernier statut HTTP, erreur, prochain retry et date de livraison. | En attente de correction/revue |
| `f7eef126-4f50-4dac-b7ef-bee9615fa1b1` | `src/Webhook/Domain/Model/Subscription/WebhookSubscription.php:125`, `reconstitute` | Préserve URL, secret chiffré, types d'événements, description et `isActive`. | En attente de correction/revue |
| `82950d88-fef9-456b-9722-12a547613fbb` | `src/Organization/Domain/ValueObject/OrganizationNotificationSettings.php:108`, constructeur | Les neuf booléens correspondent un à un aux clés plates de `toArray()` et `fromArray()` ; une clé absente conserve la valeur historique `true`. | En attente de correction/revue |
