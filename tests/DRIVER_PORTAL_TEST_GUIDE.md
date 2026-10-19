# Driver portal
Schema source: `database/transport_db.sql` (read only, never import). Includes `assignments.accepted_at`.

Safe tests (no MySQL connection):
- `C:\xampp\php\php.exe tests/driver-portal.test.php`
- `node tests/driver-portal-ui.test.cjs`
- `C:\xampp\php\php.exe tests/assignment.test.php`
- `node tests/assignment-ui.test.cjs`
- `node tests/vehicle-ui.test.cjs`

Do not run real database modes, seed, migration, SQL import or HTTP mutation probes.

Assignment `assigned` keeps all three resource locks. `accepted_at` distinguishes pending/accepted; Trip `IN_PROGRESS` means running. Driver start owns the transition to `on_trip`. Completion checks ownership and lifecycle only, writes Trip/Assignment atomically and never checks or updates Order. Driver identity comes from the active DRIVER account and drivers.user_id on every request. No user-supplied actor or driver ID is accepted.

Existing `start_time` is displayed/preserved as scheduled departure; only NULL is filled at start. There is no separate actual departure timestamp. `end_time` is written at successful completion. Counts use scheduled dates and assignments.completed_at (Monday-Sunday week); assignments without schedule times remain visible without inventing a date. Dashboard calendar boundaries use the database wall clock to match the stored DATETIME values; deployment timezone consistency still needs production verification.

STAFF replacement is blocked during IN_PROGRESS. STAFF complete retains the completed-Trip check, without an Order gate; normal completion runs through Driver. STAFF cancellation keeps existing Trip status unchanged, while the cancelled Assignment invalidates Driver actions.

No production writes or authenticated visual check were performed. Legacy inconsistent rows are preserved and reported; no automatic backfill of accepted_at or operational statuses.

Resource display regression (all memory-only):
- `C:\xampp\php\php.exe tests/resource-status.test.php`
- `node tests/driver-management-ui.test.cjs`
- `node tests/vehicle-ui.test.cjs`
- `node tests/assignment-ui.test.cjs`

Driver/Vehicle APIs preserve operational `status` and return `display_status` from the assigned row + accepted_at + trips.status. Filters and vehicle stats use the same SQL expression. Inactive/maintenance take priority. An assigned row with missing/cancelled/inconsistent Trip is reported as invalid assignment data (`unknown`), not ready, because unique locks still hold it. Driver stats count the unfiltered API roster using display_status. Refresh/reload requests bypass browser cache; no cross-tab push is claimed.

## Request and correction schema now supported
Verified the supplied transport_db.sql against live SHOW CREATE TABLE (read only). assignment_requests uses request_id, assignment_id, driver_id, request_type (decline_assignment/cancel_assignment/cancel_trip), reason, request_status, requested_at, reviewed_by, reviewed_at, review_reason. driver_status_corrections uses correction_id, driver_id, old_status, new_status, reason, changed_by, changed_at.

New safe tests:
- `C:\xampp\php\php.exe tests/assignment-requests.test.php`
- `C:\xampp\php\php.exe tests/driver-corrections.test.php`

All pending inserts serialize on the Assignment row, because the schema has no unique pending-request index. Review locks Trip/resources/Assignment/request, verifies pending status and current lifecycle, and writes review metadata in the same transaction. Stale requests can be rejected without reverting later Trip transitions, but stale approvals are blocked. Before-start approval leaves Trip PLANNED unless staff explicitly selects whole-trip cancellation; running cancellation updates Trip and Assignment atomically. Resource release preserves inactive/maintenance and any remaining assigned row. No Order updates.

Correction requires a fresh expected_status, mandatory reason, live STAFF/ADMIN actor and no effective assignment/running trip. Audit + profile changes share a transaction. Profile-only Driver/Vehicle updates omit status and preserve its latest locked value; the edit select shows display_status while locked. Quick-state Driver endpoint remains disabled.

No real database mutations were performed. Production transaction/concurrency and authenticated desktop/mobile remain unverified.
