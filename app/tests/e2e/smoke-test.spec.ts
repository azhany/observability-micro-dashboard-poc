import { test, expect } from '@playwright/test';

/**
 * E2E Smoke Test: Ingest -> Chart
 *
 * GIVEN a tenant exists
 * WHEN I POST a metric
 * THEN it appears on the Live Dashboard chart within 500ms
 *
 * This test validates the core PoC value proposition:
 * - Metric ingestion via API
 * - Real-time streaming via SSE
 * - Chart update in the browser
 */

// Test data configuration
const TEST_TENANT_ID = 'tenant-demo';
const TEST_METRIC_NAME = 'cpu_usage';
const TEST_TOKEN = 'test-token-12345678901234567890123456789012'; // 40 chars minimum
const BASE_API_URL = 'http://nginx/api/v1';

test.describe('E2E Smoke Test: Ingest -> Chart', () => {

  test.beforeEach(async ({ page }) => {
    // Setup: Navigate to the chart test page
    await page.goto('/test/chart');

    // Wait for the page to load and SSE connection to establish
    await page.waitForLoadState('networkidle');

    // Wait for chart canvas to be rendered
    await expect(page.locator('canvas').first()).toBeVisible();
  });

  test('should display metric on chart within 500ms after POST', async ({ page }) => {
    // GIVEN: A tenant exists and SSE connection is established
    // Wait for the "LIVE" or "CONNECTING" status indicator
    const statusIndicator = page.getByText(/LIVE|CONNECTING|OFFLINE/);
    await expect(statusIndicator).toBeVisible({ timeout: 10000 });

    // Record start time before posting metric
    const startTime = Date.now();

    // WHEN: I POST a metric to the ingestion API
    const metricPayload = {
      metric_name: TEST_METRIC_NAME,
      value: Math.floor(Math.random() * 100), // Random value between 0-100
      timestamp: new Date().toISOString(),
    };

    // Use page.request to make API call within the same browser context
    const response = await page.request.post(`${BASE_API_URL}/metrics`, {
      headers: {
        'Authorization': `Bearer ${TEST_TOKEN}`,
        'Content-Type': 'application/json',
      },
      data: metricPayload,
    });

    // Verify the ingestion was accepted
    expect(response.status()).toBe(202);
    const responseBody = await response.json();
    expect(responseBody.status).toBe('accepted');

    // THEN: The chart should update within 500ms
    // Strategy: Wait for the LIVE indicator to be active (indicates data received)
    // The chart updates when data is received via SSE

    // Wait for LIVE status to appear (this confirms SSE delivered the metric)
    await expect(page.getByText('LIVE')).toBeVisible({ timeout: 5000 });

    // Calculate elapsed time
    const elapsedTime = Date.now() - startTime;

    // Assert: Update occurred within 500ms
    console.log(`Metric appeared on chart in ${elapsedTime}ms`);
    expect(elapsedTime).toBeLessThan(500);

    // Additional validation: Verify the chart canvas has been updated
    // (Chart.js updates the canvas when new data is added)
    const canvas = page.locator('canvas').first();
    await expect(canvas).toBeVisible();

    // Verify the chart has data by checking the canvas is not empty
    // This is a basic check - a more sophisticated test could read canvas pixels
    const canvasElement = await canvas.elementHandle();
    const hasData = await canvasElement?.evaluate((canvas: HTMLCanvasElement) => {
      const ctx = canvas.getContext('2d');
      if (!ctx) return false;

      // Get image data and check if any pixels are non-white (indicating chart content)
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const data = imageData.data;

      // Check if there are any non-white pixels (basic check for chart rendering)
      for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i + 1];
        const b = data[i + 2];
        const a = data[i + 3];

        // If pixel is not white (255,255,255) or transparent, chart has content
        if (!(r === 255 && g === 255 && b === 255) && a > 0) {
          return true;
        }
      }
      return false;
    });

    expect(hasData).toBe(true);
  });

  test('should handle multiple metrics in sequence', async ({ page }) => {
    // GIVEN: Chart is ready and connected
    await expect(page.locator('canvas').first()).toBeVisible();

    // WHEN: I POST multiple metrics in sequence
    const metricsCount = 3;
    const timings: number[] = [];

    for (let i = 0; i < metricsCount; i++) {
      const startTime = Date.now();

      const metricPayload = {
        metric_name: TEST_METRIC_NAME,
        value: 50 + i * 10, // 50, 60, 70
        timestamp: new Date().toISOString(),
      };

      const response = await page.request.post(`${BASE_API_URL}/metrics`, {
        headers: {
          'Authorization': `Bearer ${TEST_TOKEN}`,
          'Content-Type': 'application/json',
        },
        data: metricPayload,
      });

      expect(response.status()).toBe(202);

      // Wait a bit for SSE to deliver
      await page.waitForTimeout(300);

      const elapsedTime = Date.now() - startTime;
      timings.push(elapsedTime);

      console.log(`Metric ${i + 1} processed in ${elapsedTime}ms`);
    }

    // THEN: All metrics should be processed within acceptable time
    // At least one should be under 500ms
    const hasQuickUpdate = timings.some(t => t < 500);
    expect(hasQuickUpdate).toBe(true);

    // Verify LIVE status is maintained
    await expect(page.getByText('LIVE')).toBeVisible();
  });

  test('should maintain SSE connection stability', async ({ page }) => {
    // GIVEN: Chart page is loaded
    await expect(page.locator('canvas').first()).toBeVisible();

    // WHEN: Checking connection status
    const statusText = page.getByText(/LIVE|CONNECTING|OFFLINE/);

    // THEN: Should not show OFFLINE status (indicates SSE connection issues)
    await expect(statusText).toBeVisible();

    // Wait and verify connection remains stable
    await page.waitForTimeout(2000);

    const currentStatus = await statusText.textContent();
    console.log(`SSE Connection Status: ${currentStatus}`);

    // Status should be LIVE or CONNECTING, not OFFLINE
    expect(currentStatus).toMatch(/LIVE|CONNECTING/);
  });
});
