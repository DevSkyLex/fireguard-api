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
