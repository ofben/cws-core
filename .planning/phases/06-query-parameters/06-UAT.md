---
status: complete
phase: 06-query-parameters
source: 06-01-SUMMARY.md
started: 2026-03-03T12:20:00Z
updated: 2026-03-03T12:25:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Query Parameters field visible in admin settings
expected: In WP Admin → Settings → CWS Core, the API Configuration section shows a "Query Parameters" field with a repeater UI — at minimum an "Add Parameter" button and (if any saved) rows of key/value inputs.
result: pass

### 2. Add a new query parameter row
expected: Clicking the "Add Parameter" button appends a new row with a Key input, a Value input, and a Remove (×) button — no page reload required.
result: pass

### 3. Remove a query parameter row
expected: Clicking the × button on a row removes that row from the list immediately (no reload). Other rows remain and their indices update so the form stays contiguous.
result: pass

### 4. Parameters persist after saving
expected: After entering one or more key/value pairs and clicking "Save Settings", reload the settings page — the configured rows reappear populated with the values you entered.
result: pass

### 5. Query parameters appear in API requests
expected: With at least one key/value pair saved, trigger a job fetch (e.g., visit a page that loads jobs). Inspect the outgoing request URL in browser DevTools (Network tab) — the API URL includes your configured param(s) as query string variables.
result: pass

## Summary

total: 5
passed: 5
issues: 0
pending: 0
skipped: 0

## Gaps

[none yet]
