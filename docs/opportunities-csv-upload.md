# Bulk-uploading opportunities (CSV)

Admins can load many opportunities, tenders and RFQs at once from a CSV file
instead of adding them one at a time.

## Where

Admin panel → **Opportunities** → **Import CSV** (top-right, beside **New
opportunity**). Pick a `.csv` file, map the columns if the headers differ from
the names below, and start the import. Imports run on the queue, so a worker
must be running (it already is in production); you'll get a notification when it
finishes, with a count of imported and failed rows.

## Columns

Only `title` is required. Everything else is optional.

| Column         | Required | Notes |
| -------------- | :------: | ----- |
| `title`        | ✅ | The opportunity name. |
| `type`         |    | One of `tender`, `funding`, `grant`, `procurement`, `programme`, `competition`. Blank or unrecognised → `programme`. |
| `description`  |    | Free text. Blank is allowed. |
| `organisation` |    | Who is offering it. |
| `url`          |    | Link to the opportunity (must be a valid URL). |
| `source`       |    | Where the row came from (e.g. a feed name). Defaults to `CSV import`. Used with `source_ref` for de-duplication. |
| `source_url`   |    | Link to the source listing. |
| `source_ref`   |    | A stable ID for this opportunity in its source. **See de-duplication below.** |
| `is_official`  |    | `1`/`0`, `true`/`false`. |
| `is_sponsored` |    | `1`/`0`, `true`/`false`. |
| `sponsor_name` |    | Shown when sponsored. |
| `sponsor_url`  |    | Sponsor link (must be a valid URL). |
| `industry`     |    | Free text. |
| `province`     |    | Free text. |
| `amount`       |    | Free text (e.g. `Up to R500 000`) — not a number. |
| `closes_at`    |    | Deadline date, e.g. `2026-09-30`. Blank = no deadline. |
| `is_published` |    | `1`/`0`, `true`/`false`. Off keeps the row as a draft, hidden from members. |

## De-duplication

Rows are matched on the pair **(`source`, `source_ref`)**:

- If a row's `source` + `source_ref` matches an existing opportunity, that
  opportunity is **updated** with the row's values.
- If there's no match (or no `source_ref`), a **new** opportunity is created.

This means you can re-upload a corrected file and it will fix the existing
rows instead of creating duplicates — as long as `source_ref` stays the same.

## Example

```csv
type,title,description,organisation,province,amount,closes_at,source,source_ref,is_published
grant,Youth Innovation Grant,Up to R500k for youth-led ventures,SEDA,Gauteng,Up to R500 000,2026-09-30,SEDA-2026,GRANT-001,1
tender,Municipal ICT Tender,Supply and support of ICT equipment,City of Cape Town,Western Cape,,2026-08-15,eTenders,TND-4471,1
programme,Women in Business Accelerator,12-week cohort for women founders,,National,,,,WIB-ACC,0
```

The third row has no `type` match issues (it's `programme`) and is left as a
draft (`is_published` = `0`) until an admin publishes it.
