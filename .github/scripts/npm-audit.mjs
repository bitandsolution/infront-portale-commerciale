#!/usr/bin/env node
// npm audit with temporary, expiring exceptions (.github/npm-audit-exceptions.json).
//
// Fails when an advisory of severity high or critical is not in the exceptions, when an exception has
// expired, or when an exception lacks a field. An exception no longer reported by npm is only a warning:
// it is time to remove it. npm audit has no way to ignore a single advisory, hence this script.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const LEVELS = ['info', 'low', 'moderate', 'high', 'critical'];
const THRESHOLD = LEVELS.indexOf('high');
const today = new Date().toISOString().slice(0, 10);

const exceptions = JSON.parse(readFileSync(new URL('../npm-audit-exceptions.json', import.meta.url), 'utf8'));
let failed = false;

for (const exception of exceptions) {
    for (const field of ['advisory', 'package', 'expires', 'reason', 'approved']) {
        if (typeof exception[field] !== 'string' || exception[field] === '') {
            console.log(`::error title=npm audit::Exception without "${field}": ${JSON.stringify(exception)}`);
            failed = true;
        }
    }
    if (typeof exception.expires === 'string' && exception.expires < today) {
        console.log(`::error title=npm audit::The exception for ${exception.advisory} (${exception.package}) expired on ${exception.expires}: update the package or renew the exception with a new decision.`);
        failed = true;
    }
}

let output;
try {
    output = execFileSync('npm', ['audit', '--json'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
} catch (error) {
    // npm audit exits with 1 when it finds vulnerabilities: the report is still on stdout.
    output = error.stdout;
}

const report = JSON.parse(output);
const advisories = new Map();
for (const [name, vulnerability] of Object.entries(report.vulnerabilities ?? {})) {
    for (const via of vulnerability.via ?? []) {
        if (typeof via === 'object' && via !== null && typeof via.url === 'string') {
            const id = via.url.split('/').pop();
            advisories.set(id, { id, package: via.name ?? name, severity: via.severity, title: via.title, url: via.url });
        }
    }
}

const excepted = new Set(exceptions.map((exception) => exception.advisory));
for (const advisory of advisories.values()) {
    const blocking = LEVELS.indexOf(advisory.severity) >= THRESHOLD;
    const status = excepted.has(advisory.id) ? 'EXCEPTION' : blocking ? 'BLOCKING' : 'below threshold';
    console.log(`${advisory.severity.padEnd(9)} ${advisory.package.padEnd(24)} ${advisory.id}  ${status}  ${advisory.title}`);
    if (blocking && !excepted.has(advisory.id)) {
        console.log(`::error title=npm audit::${advisory.severity} ${advisory.package}: ${advisory.title} (${advisory.url})`);
        failed = true;
    }
}

for (const exception of exceptions) {
    if (!advisories.has(exception.advisory)) {
        console.log(`::warning title=npm audit::${exception.advisory} (${exception.package}) is no longer reported: remove its exception.`);
    }
}

if (advisories.size === 0) {
    console.log('No vulnerabilities found.');
}

process.exit(failed ? 1 : 0);
