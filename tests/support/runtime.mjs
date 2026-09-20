import fs from 'node:fs';
import path from 'node:path';

const root=path.resolve('.');
const outputDir=path.resolve(process.env.TEST_OUTPUT_DIR ?? '.runtime/test-results');
fs.mkdirSync(outputDir,{recursive:true});

export function workspaceContainerPath(file) {
  const relative=path.relative(root,path.resolve(file));
  if(relative.startsWith('..') || path.isAbsolute(relative)) throw new Error('Test output must remain inside the workspace');
  return '/workspace/'+relative.split(path.sep).join('/');
}

export const runtime={
  baseURL:(process.env.TEST_BASE_URL ?? 'http://127.0.0.1:8232').replace(/\/$/,''),
  publicURL:(process.env.EXPECTED_PUBLIC_URL ?? 'https://tio2products.com').replace(/\/$/,'')+'/',
  expectedRelease:process.env.EXPECTED_RELEASE ?? '',
  outputDir,
  envFile:process.env.TEST_ENV_FILE ?? '.env',
  composeFile:process.env.TEST_COMPOSE_FILE ?? 'compose.yaml',
};
