import assert from 'node:assert/strict';
import path from 'node:path';

process.env.TEST_BASE_URL='http://127.0.0.1:8233/';
process.env.EXPECTED_PUBLIC_URL='https://tio2products.com';
process.env.EXPECTED_RELEASE='0123456789abcdef0123456789abcdef01234567';
process.env.TEST_OUTPUT_DIR='.runtime/test-results/node-settings';

const {runtime,workspaceContainerPath}=await import('./support/runtime.mjs');
assert.equal(runtime.baseURL,'http://127.0.0.1:8233');
assert.equal(runtime.publicURL,'https://tio2products.com/');
assert.equal(runtime.expectedRelease,'0123456789abcdef0123456789abcdef01234567');
assert.equal(workspaceContainerPath(path.resolve('.runtime/test-results/content-before.json')),'/workspace/.runtime/test-results/content-before.json');
console.log('node runtime settings contract passed');
