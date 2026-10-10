import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const backend = resolve(root, 'backend');
const phase = process.argv[2] || 'demo';
const allowed = ['baseline', 'regression', 'corrected', 'demo', 'full'];
if (!allowed.includes(phase)) throw new Error(`Phase must be one of: ${allowed.join(', ')}`);
const output = resolve(root, process.env.TAREA5_REPORT_DIR || 'tests/automation/reports');
mkdirSync(output, { recursive: true });
const target = resolve(backend, 'app/Http/Requests/SaveCaregiverScheduleRequest.php');
const original = readFileSync(target, 'utf8');
const fingerprint = value => createHash('sha256').update(value).digest('hex');
const before = fingerprint(original);
const results = [];

function phpString(value) { return `'${value.replaceAll('\\', '/').replaceAll("'", "\\'")}'`; }
function run(label, mutate = false, full = false) {
  const args = [];
  if (mutate) {
    const mutation = resolve(output, 'mutation');
    mkdirSync(mutation, { recursive: true });
    const needle = ", 'after:start_time'";
    if (original.split(needle).length !== 2) throw new Error('Mutation target changed: expected exactly one time-order validation');
    const mutant = resolve(mutation, 'SaveCaregiverScheduleRequest.php');
    writeFileSync(mutant, original.replace(needle, ''));
    const prepend = resolve(mutation, 'bootstrap.php');
    writeFileSync(prepend, `<?php\nrequire ${phpString(resolve(backend, 'vendor/autoload.php'))};\nrequire ${phpString(mutant)};\n`);
    writeFileSync(resolve(output, 'mutation.diff'), "--- original/SaveCaregiverScheduleRequest.php\n+++ mutant/SaveCaregiverScheduleRequest.php\n@@ validation @@\n- 'end_time' => ['required', 'date_format:H:i', 'after:start_time'],\n+ 'end_time' => ['required', 'date_format:H:i'],\n");
    args.push('-d', `auto_prepend_file=${prepend}`);
  }
  args.push('vendor/phpunit/phpunit/phpunit', '--configuration', 'phpunit.xml', '--colors=never',
    '--log-junit', resolve(output, `${label}.xml`), '--testdox');
  if (!full) args.push('tests/Feature/Tarea5');
  const started = performance.now();
  const runResult = spawnSync(process.env.PHP_BINARY || 'php', args, { cwd: backend, encoding: 'utf8', timeout: 240000 });
  const log = (runResult.stdout || '') + (runResult.stderr || '') + (runResult.error ? String(runResult.error) : '');
  writeFileSync(resolve(output, `${label}.log`), log);
  process.stdout.write(`\n=== ${label} ===\n${log}`);
  const result = { phase: label, exit_code: runResult.status ?? 2, elapsed_seconds: +(performance.now() - started).toFixed(0) / 1000,
    command: ['php', ...args], source_sha256: before };
  results.push(result);
  return result;
}

let status = 0;
try {
  if (phase === 'demo') {
    const baseline = run('baseline');
    if (baseline.exit_code !== 0) throw new Error('Baseline failed; regression experiment cannot begin');
    const regression = run('regression', true);
    const xml = readFileSync(resolve(output, 'regression.xml'), 'utf8');
    // A crash or an unrelated failing test is not evidence of detecting the planned regression.
    if (regression.exit_code !== 1 || !xml.includes('test_r01_schedule_rejects_reversed_times_without_persisting') ||
        !/failures="1"/.test(xml) || !/errors="0"/.test(xml) ||
        !readFileSync(resolve(output, 'regression.log'), 'utf8').includes('received 201')) {
      throw new Error('Expected exactly one assertion failure: R01 received HTTP 201 instead of 422');
    }
    const corrected = run('corrected');
    if (corrected.exit_code !== 0) throw new Error('Corrected execution failed');
    console.log('\nExperiment verified: baseline PASS -> regression DETECTED -> corrected PASS.');
  } else {
    status = run(phase, phase === 'regression', phase === 'full').exit_code;
  }
} catch (error) {
  console.error(error.message);
  status = 2;
} finally {
  const after = fingerprint(readFileSync(target, 'utf8'));
  const manifest = { executed_at: new Date().toISOString(), mode: phase, results,
    source_restored: before === after, source_sha256_before: before, source_sha256_after: after,
    experiment_success: phase === 'demo' && status === 0 };
  writeFileSync(resolve(output, `${phase}-manifest.json`), JSON.stringify(manifest, null, 2));
  if (before !== after) { console.error('Original source changed during execution'); status = 2; }
}
process.exitCode = status;
