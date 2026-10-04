<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">

        <title>Energy Monitor</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body">
        <div id="energy-dashboard" class="dashboard-shell" data-api-base="/api/v1">
            <aside class="dashboard-sidebar" aria-label="Device navigation">
                <div class="brand-lockup">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M13.2 2 5 13h6l-.8 9L19 10h-6l.2-8Z" fill="currentColor" />
                        </svg>
                    </span>
                    <span>
                        <strong>Energy Monitor</strong>
                        <small>IoT operations</small>
                    </span>
                </div>

                <div class="sidebar-section-heading">
                    <span>Devices</span>
                    <span id="device-count" class="count-pill">0</span>
                </div>

                <nav id="device-list" class="device-list" aria-label="Available devices" aria-live="polite">
                    <div class="device-list-state">
                        <span class="loading-dot" aria-hidden="true"></span>
                        Loading devices…
                    </div>
                </nav>

                <div class="sidebar-footer">
                    <span class="pulse-dot" aria-hidden="true"></span>
                    <span>
                        <strong>Live polling active</strong>
                        <small>Refreshes every 10 seconds</small>
                    </span>
                </div>
            </aside>

            <main class="dashboard-main">
                <header class="dashboard-header">
                    <div>
                        <p class="eyebrow">Energy overview</p>
                        <div class="header-title-row">
                            <h1 id="selected-device-name">Select a device</h1>
                            <span id="selected-device-transport" class="transport-badge is-neutral">—</span>
                            <span id="header-status" class="status-badge is-neutral">
                                <span class="status-dot" aria-hidden="true"></span>
                                <span id="header-status-label">Waiting</span>
                            </span>
                        </div>
                        <p class="device-meta">
                            <span id="selected-device-uid">—</span>
                            <span aria-hidden="true">•</span>
                            Last seen <span id="header-last-seen">—</span>
                        </p>
                    </div>

                    <div class="sync-status" aria-live="polite">
                        <span>Last dashboard sync</span>
                        <strong id="last-sync">Not yet synced</strong>
                    </div>
                </header>

                <div id="dashboard-alert" class="dashboard-alert" role="status" hidden></div>

                <section class="realtime-layout" aria-label="Realtime energy metrics">
                    <article class="panel usage-panel">
                        <div class="panel-heading panel-heading-on-dark">
                            <div>
                                <p class="eyebrow">Live load</p>
                                <h2>Realtime Usage</h2>
                            </div>
                            <span id="live-chip" class="live-chip"><span></span><span id="live-chip-label">Live</span></span>
                        </div>

                        <div class="power-gauge" aria-label="Current power usage gauge">
                            <svg viewBox="0 0 240 145" role="img" aria-hidden="true">
                                <defs>
                                    <linearGradient id="gauge-gradient" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#30d5c8" />
                                        <stop offset="1" stop-color="#b8f34a" />
                                    </linearGradient>
                                </defs>
                                <path class="gauge-track" pathLength="100" d="M25 120a95 95 0 0 1 190 0" />
                                <path id="power-gauge-value" class="gauge-value" pathLength="100" d="M25 120a95 95 0 0 1 190 0" />
                            </svg>
                            <div class="gauge-reading">
                                <strong><span id="power-value">—</span><small> W</small></strong>
                                <span>Current demand</span>
                            </div>
                            <span class="gauge-min">0 W</span>
                            <span class="gauge-max">1.2 kW</span>
                        </div>

                        <div class="usage-secondary-grid">
                            <div>
                                <span>Voltage</span>
                                <strong><span id="voltage-value">—</span> <small>V</small></strong>
                            </div>
                            <div>
                                <span>Current</span>
                                <strong><span id="current-value">—</span> <small>A</small></strong>
                            </div>
                        </div>
                    </article>

                    <article class="panel trend-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Recent telemetry</p>
                                <h2>Realtime Power Trend</h2>
                            </div>
                            <span id="chart-point-count" class="subtle-pill">0 readings</span>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="power-trend-chart" aria-label="Power measurements over time"></canvas>
                            <div id="chart-empty-state" class="chart-empty-state">
                                Select a device to load power history.
                            </div>
                        </div>
                    </article>
                </section>

                <section class="information-grid" aria-label="Device information">
                    <article class="info-card">
                        <span class="info-icon status-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v9m6.36-6.36a9 9 0 1 1-12.72 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                        </span>
                        <div>
                            <span>Device Status</span>
                            <strong id="info-status">—</strong>
                            <small id="info-status-detail">Waiting for device</small>
                        </div>
                    </article>
                    <article class="info-card">
                        <span class="info-icon time-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8" /><path d="M12 7.5V12l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                        </span>
                        <div>
                            <span>Last Seen</span>
                            <strong id="info-last-seen">—</strong>
                            <small id="info-last-seen-detail">No recent contact</small>
                        </div>
                    </article>
                    <article class="info-card">
                        <span class="info-icon voltage-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="m13.5 2.5-7 11h5l-1 8 7-11h-5l1-8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /></svg>
                        </span>
                        <div>
                            <span>Latest Voltage</span>
                            <strong><span id="info-voltage">—</span> <small>V</small></strong>
                            <small>Line potential</small>
                        </div>
                    </article>
                    <article class="info-card">
                        <span class="info-icon current-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M4 12h16M15 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <div>
                            <span>Latest Current</span>
                            <strong><span id="info-current">—</span> <small>A</small></strong>
                            <small>Active flow</small>
                        </div>
                    </article>
                </section>

                <section class="analytics-section" aria-labelledby="energy-consumption-heading">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Integrated power history</p>
                            <h2 id="energy-consumption-heading">Energy Consumption</h2>
                        </div>
                        <span class="analytics-refresh-note">Updates every 60 seconds</span>
                    </div>

                    <div class="energy-summary-grid">
                        <article class="energy-card">
                            <span>Total Today</span>
                            <strong><span id="energy-today">—</span> <small>kWh</small></strong>
                            <small id="energy-today-detail">Select a device</small>
                        </article>
                        <article class="energy-card">
                            <span>Daily Average</span>
                            <strong><span id="energy-daily-average">—</span> <small>kWh</small></strong>
                            <small id="energy-daily-average-detail">Select a device</small>
                        </article>
                        <article class="energy-card">
                            <span>Total This Month</span>
                            <strong><span id="energy-current-month">—</span> <small>kWh</small></strong>
                            <small id="energy-current-month-detail">Select a device</small>
                        </article>
                        <article class="energy-card">
                            <span>Monthly Average</span>
                            <strong><span id="energy-monthly-average">—</span> <small>kWh</small></strong>
                            <small id="energy-monthly-average-detail">Select a device</small>
                        </article>
                    </div>

                    <div class="historical-layout">
                        <article class="panel historical-panel">
                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">Daily consumption</p>
                                    <h2>Last 30 Days</h2>
                                </div>
                                <span class="subtle-pill">kWh / day</span>
                            </div>
                            <div class="historical-chart-wrap">
                                <canvas id="daily-energy-chart" aria-label="Energy consumption during the last 30 days"></canvas>
                                <div id="daily-energy-empty" class="chart-empty-state">
                                    Select a device to load daily energy history.
                                </div>
                            </div>
                        </article>

                        <article class="panel historical-panel">
                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">Monthly consumption</p>
                                    <h2>Last 12 Months</h2>
                                </div>
                                <span class="subtle-pill">kWh / month</span>
                            </div>
                            <div class="historical-chart-wrap">
                                <canvas id="monthly-energy-chart" aria-label="Energy consumption during the last 12 months"></canvas>
                                <div id="monthly-energy-empty" class="chart-empty-state">
                                    Select a device to load monthly energy history.
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="panel measurements-panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Live data feed</p>
                            <h2>Recent Measurements</h2>
                        </div>
                        <span class="table-caption">Newest first · latest 10</span>
                    </div>

                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Voltage</th>
                                    <th scope="col">Current</th>
                                    <th scope="col">Power</th>
                                </tr>
                            </thead>
                            <tbody id="measurements-table-body">
                                <tr class="table-state-row">
                                    <td colspan="4">Select a device to load measurements.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
