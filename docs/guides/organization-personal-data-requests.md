# Organization personal data requests

This procedure governs manual handling of requests concerning personal data used
by an organization in Fireguard. It complements the
[Organization contract](../../src/Organization/MODULE.md) and
[security guidance](../../SECURITY.md); it does not create a request portal or
automated deletion flow.

## Contact and responsibility

An organization can configure `privacyContactEmail` in its legal settings.
Members can consult that contact on the organization's More page without needing
permission to edit settings. The contact is not a declaration that a DPO has been
appointed.

Publish an official contact through the organization's own notices or other
accessible channels as well. Former members and external people must be able to
submit requests without an active Fireguard account. Receive requests reaching
other official contact points too; the configured email is not an exclusive form
or channel.

For each request, identify the processing concerned and who determines its
purposes and means. Route organization-controlled business processing to the
organization's designated handler. Route processing controlled by the Fireguard
operator, such as its own account or billing administration, to that operator.
Where Fireguard acts as a processor, its operator assists the controller under
the applicable instructions and agreement. An application admin role alone does
not determine these legal responsibilities. See the
[EDPB explanation of controller and processor roles](https://www.edpb.europa.eu/sme/learn-the-basics/data-controller-or-data-processor_en).

## Handling a request

1. **Record receipt and assign a handler.** Keep a restricted manual record with
   receipt date, request scope, responsible handler, deadline, actions and final
   response. Acknowledge receipt and clarify ambiguous scope without imposing a
   mandatory form or requiring the requester to justify an access request.
2. **Check identity proportionately.** Use existing reliable information. Ask for
   additional information only where there are reasonable doubts; do not collect
   identity documents systematically. Do not disclose data while identity remains
   unresolved.
3. **Track the deadline.** Respond without undue delay and normally within one
   calendar month of receipt. Where justified by complexity or number of requests,
   an extension of up to two further months requires notice and reasons within
   the initial month. Record the applicable calendar deadline and any justified
   adjustment; Fireguard does not calculate or remind these deadlines.
4. **Locate and assess the data.** Review relevant Fireguard modules, exports and
   other systems used for the processing. Apply the requested right to its actual
   scope and legal conditions. Protect other people's data when preparing an
   access response. Organization archival retains data and is not erasure.
5. **Respond securely.** Use an appropriate authenticated or otherwise protected
   delivery channel and a commonly used electronic format where appropriate.
   Record delivery. Explain any refusal or partial refusal, including the relevant
   complaint and remedy information; do not send broad exports to an unverified
   recipient.
6. **Close and review retention.** Retain only the evidence needed to demonstrate
   handling, with restricted access and a documented, justified retention period.
   Set a review date and arrange deletion when that justification ends. Do not
   place request contents or identity documents in application logs or the
   append-only audit ledger. This procedure fixes no universal retention period.

The [EDPB practical guide to individuals' rights](https://www.edpb.europa.eu/sme/be-compliant/respect-individuals-rights_en)
explains response duties and timing. Its
[right of access guidelines](https://www.edpb.europa.eu/system/files/2023-04/edpb_guidelines_202201_data_subject_rights_access_v2_en.pdf)
cover communication channels, proportionate identification and secure responses.

The handler remains responsible for implementing the response. Configuring a
contact or following this recordkeeping procedure does not itself perform an
export, rectification, restriction or erasure.
# Données GeoIP liées au compte

Les localisations des sessions et des emails de sécurité relèvent du compte, y compris lorsque
la demande est reçue par une organisation. Vérifier l'identité du titulaire et l'habilitation
de l'intervenant ; le rôle administrateur d'organisation ne donne pas accès aux sessions d'autrui.
Ne communiquer que les données du titulaire par le canal sécurisé prévu par cette procédure.
Ne pas recopier les lieux dans les journaux ni dans un ticket de support.

Pour l'accès, inclure les pays/villes retenus avec les sessions du compte et expliquer qu'il
s'agit du lieu approximatif du réseau au moment de la connexion. Pour la contestation ou
l'effacement, utiliser la commande auth et les instructions de
[la procédure GeoIP](geoip-operations-and-privacy.md), en préservant les autres métadonnées.
Une révocation de session efface immédiatement son lieu dans la base courante.

Une opposition accueillie doit aussi arrêter les traitements futurs : la procédure GeoIP
décrit la désactivation globale nécessaire dans cette version sans exclusion par compte.
Documenter la décision, les durées chez le prestataire email et la réapplication des effacements
après restauration. Ne pas affirmer que la commande efface les emails déjà délivrés ou toutes
les sauvegardes. Aucun portail supplémentaire n'est créé.
