# Schema migrations

Every deploy that changes the database ships a migration script here.
**The migration is applied to live separately, and BEFORE the code deploy.**

This is the same discipline used for the Rockbell menu import, applied every
time rather than only when a change feels risky.

## Why migration first, then code

The old code must keep working against the new schema for the few minutes
between the two steps. Additive changes (new column, new table, new index)
satisfy that; the running code simply ignores what it does not know about.

This is why **destructive changes are split across two deploys**:

| | Deploy N | Deploy N+1 |
|---|---|---|
| Rename a column | add new column, write to both | drop the old column |
| Drop a column | stop writing to it | drop it |
| Narrow a type | add new column, backfill | swap and drop |

Never drop or rename in the same deploy that changes the code using it. If the
code deploy has to be rolled back, a dropped column is not coming back without
the backup.

## The order, every time

1. **Back up live** — full dump, verified non-empty, kept until the deploy is
   confirmed good. Not optional, not "it's only an index".
2. **Apply the migration** to live, on its own, and check the verification
   query at the bottom of the script.
3. **Deploy the code.**
4. **Smoke-test** live.

If step 2 fails, stop. Do not deploy the code — the running site is still
consistent with the old schema, which is the whole point of this ordering.

## Naming

    YYYY-MM-DD_NN_short-description.sql
    2026-08-29_01_add-loyalty-tier-to-customers.sql

Numbered within the day so the apply order is unambiguous.

## Rules

- One logical change per file. Not a grab-bag.
- **Idempotent where the syntax allows** (`IF NOT EXISTS`), so a half-applied
  run can be re-run safely.
- Every file states its rollback in the header. "Restore from backup" is a
  legitimate answer, but it has to be written down as the answer.
- Wrap multi-statement DDL in a transaction only if you have verified it is
  transactional in MySQL — **most DDL is not, and will silently auto-commit.**
  Assume you cannot roll back DDL; that is what the backup is for.
- No `SELECT`-and-eyeball verification. Put a query at the bottom that returns
  a clear pass/fail.
