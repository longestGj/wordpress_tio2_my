import {defineConfig} from '@playwright/test';
import {runtime} from './tests/support/runtime.mjs';
export default defineConfig({
  testDir: './tests', testMatch: '*.spec.mjs', fullyParallel: false, workers: 1,
  timeout: 45000, expect: {timeout: 5000}, outputDir: `${runtime.outputDir}/playwright`,
  use: {baseURL: runtime.baseURL, channel: process.env.CI ? undefined : 'chrome', headless: true, reducedMotion: 'reduce', colorScheme: 'light'},
  reporter: [['list'], ['json', {outputFile: `${runtime.outputDir}/playwright-results.json`}]],
});
