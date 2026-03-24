# Regression Checklist

- Success path still passes with representative input
- Failure path proves the bug cannot recur
- Permission denial is covered when relevant
- Tenant or organization isolation is covered when relevant
- API-facing changes assert status code and response shape
- Persistence changes assert scoped reads and writes
