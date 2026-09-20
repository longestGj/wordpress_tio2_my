import {defineConfig} from '@playwright/test';
export default defineConfig({
  testDir: './tests', testMatch: '*.spec.mjs', fullyParallel: false, workers: 1,
  timeout: 45000, expect: {timeout: 5000},
  use: {baseURL: 'http://127.0.0.1:8232', channel: 'chrome', headless: true, reducedMotion: 'reduce', colorScheme: 'light'},
  reporter: [['list'], ['json', {outputFile: '.runtime/playwright-results.json'}]],
});
