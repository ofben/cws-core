---
status: complete
phase: 07-field-groupings
source: 07-01-SUMMARY.md
started: 2026-03-03T13:00:00Z
updated: 2026-03-03T13:10:00Z
---

## Current Test
<!-- OVERWRITE each test - shows where we are -->

[testing complete]

## Tests

### 1. Field Groupings repeater renders in admin
expected: Go to Settings → CWS Core (or the API Configuration settings page). A "Field Groupings" section/field is visible with an "Add Grouping" button and a description explaining what it does. Any previously saved field names appear as rows with their values pre-filled.
result: pass

### 2. Add Grouping appends a new row
expected: Click the "Add Grouping" button. A new text input row appears immediately (no page reload) with an empty field name input and a "Remove" button alongside it.
result: pass

### 3. Remove button deletes a row
expected: With at least one row present, click "Remove" on it. The row disappears immediately (no page reload). Remaining rows stay intact and saving afterwards does not include the removed value.
result: pass

### 4. Save persists field names
expected: Add a field name (e.g. "primary_category"), click Save. Reload the settings page — the value "primary_category" is still present in the repeater. Blank rows are not saved.
result: pass

### 5. Etch template receives grouped data
expected: With "primary_category" saved as a grouping, open an Etch template and use `{options.cws_jobs_by_primary_category}` — it resolves to a keyed object where each key is a category slug (e.g. "product-management") and the value is an array of matching job objects.
result: pass

## Summary

total: 5
passed: 5
issues: 0
pending: 0
skipped: 0

## Gaps

[none yet]
