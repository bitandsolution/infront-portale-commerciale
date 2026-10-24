<?php

/*
| Audit trail (M10 §4; D-128, D-210, D-211, D-283).
|
| The retention of the events (5 years, RB-A2) is fixed: it is also written in the delete trigger
| of audit_events (App\Models\AuditEvent::RETENTION_YEARS).
*/

return [

    /*
    | Days after which the IP address of an access event is cleared, the event staying (D-211).
    | Default 5 years (1826 days), as the events. From PR-07 the value moves to the settings table
    | (settings.audit_ip_retention_days) and this one remains the default.
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
