<?php

/*
| Audit trail (M10 §4; D-128, D-210, D-211, D-283).
|
| The retention of the events (5 years, RB-A2) is fixed: it is also written in the delete trigger
| of audit_events (App\Models\AuditEvent::RETENTION_YEARS).
*/

return [

    /*
    | Default of the days after which the IP address of an access event is cleared, the event
    | staying (D-211): 5 years (1826 days), as the events. From PR-07 the value in force is the
    | setting "audit_ip_retention_days" (Settings page, D-286); this one is used only while the row
    | is missing, and the system data seeder copies it into the row the first time it runs. After
    | that, changing AUDIT_IP_RETENTION_DAYS has no effect: the value changes from the Settings page.
    */
    'ip_retention_days' => (int) env('AUDIT_IP_RETENTION_DAYS', 1826),

    /*
    | Database connection of the retention command (audit:prune). In production a dedicated MariaDB
    | user that can only delete expired events and clear IP addresses (requirement R7,
    | database/sql/audit-users.sql). Without DB_AUDIT_MAINTENANCE_USERNAME (development, CI) the
    | command uses the default connection.
    */
    'maintenance_connection' => env('DB_AUDIT_MAINTENANCE_USERNAME') ? 'audit_maintenance' : null,

];
