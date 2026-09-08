# Domain eligibility snapshots

The server uses these local lists before issuing DNS ownership challenges. Missing lists fail closed. Refresh both snapshots regularly as part of the server dependency/security maintenance process, review the changes, and run `OrganizationJoinRulesTest` after replacement.

- `public_suffix_list.dat`: Public Suffix List, https://publicsuffix.org/list/public_suffix_list.dat, downloaded 2026-09-07. Its embedded Mozilla Public License 2.0 notice is retained.
- `disposable_email_blocklist.conf`: disposable-email-domains community blocklist, https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf, downloaded 2026-09-07; upstream project https://github.com/disposable-email-domains/disposable-email-domains.

Common consumer providers are also explicitly denied by the adapter. Exact normalized email domains are matched; subdomains do not inherit an organization's TXT proof. These lists govern organization discovery only and do not reject consumer addresses for ordinary accounts or explicit invitations.
