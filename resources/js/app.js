import Chart from 'chart.js/auto';

const dashboard = document.querySelector('#energy-dashboard');

if (dashboard) {
    const apiBase = dashboard.dataset.apiBase;
    const refreshInterval = 10_000;
    const analyticsRefreshInterval = 60_000;
    const elements = {
        alert: document.querySelector('#dashboard-alert'),
        chartEmpty: document.querySelector('#chart-empty-state'),
        chartPointCount: document.querySelector('#chart-point-count'),
        current: document.querySelector('#current-value'),
        dailyEnergyAverage: document.querySelector('#energy-daily-average'),
        dailyEnergyAverageDetail: document.querySelector('#energy-daily-average-detail'),
        dailyEnergyEmpty: document.querySelector('#daily-energy-empty'),
        deviceCount: document.querySelector('#device-count'),
        deviceList: document.querySelector('#device-list'),
        energyCurrentMonth: document.querySelector('#energy-current-month'),
        energyCurrentMonthDetail: document.querySelector('#energy-current-month-detail'),
        energyMonthlyAverage: document.querySelector('#energy-monthly-average'),
        energyMonthlyAverageDetail: document.querySelector('#energy-monthly-average-detail'),
        energyToday: document.querySelector('#energy-today'),
        energyTodayDetail: document.querySelector('#energy-today-detail'),
        gauge: document.querySelector('#power-gauge-value'),
        headerLastSeen: document.querySelector('#header-last-seen'),
        headerStatus: document.querySelector('#header-status'),
        headerStatusLabel: document.querySelector('#header-status-label'),
        infoCurrent: document.querySelector('#info-current'),
        infoLastSeen: document.querySelector('#info-last-seen'),
        infoLastSeenDetail: document.querySelector('#info-last-seen-detail'),
        infoStatus: document.querySelector('#info-status'),
        infoStatusDetail: document.querySelector('#info-status-detail'),
        infoVoltage: document.querySelector('#info-voltage'),
        lastSync: document.querySelector('#last-sync'),
        liveChip: document.querySelector('#live-chip'),
        liveChipLabel: document.querySelector('#live-chip-label'),
        monthlyEnergyEmpty: document.querySelector('#monthly-energy-empty'),
        power: document.querySelector('#power-value'),
        selectedName: document.querySelector('#selected-device-name'),
        selectedTransport: document.querySelector('#selected-device-transport'),
        selectedUid: document.querySelector('#selected-device-uid'),
        tableBody: document.querySelector('#measurements-table-body'),
        voltage: document.querySelector('#voltage-value'),
    };
    const state = {
        abortController: null,
        analyticsInFlight: false,
        chart: createPowerChart(),
        dailyEnergyChart: createEnergyChart('#daily-energy-chart', 'Daily energy', '#22b6a7'),
        devices: [],
        monthlyEnergyChart: createEnergyChart('#monthly-energy-chart', 'Monthly energy', '#7ea82e'),
        refreshInFlight: false,
        selectedDevice: null,
        selectionVersion: 0,
    };

    initializeDashboard();

    async function initializeDashboard() {
        await refreshDeviceList();

        window.setInterval(() => {
            if (! document.hidden) {
                refreshSelectedDevice();
            }
        }, refreshInterval);

        window.setInterval(() => {
            if (! document.hidden) {
                refreshDeviceList();
            }
        }, refreshInterval * 3);

        window.setInterval(() => {
            if (! document.hidden) {
                refreshEnergySummary();
            }
        }, analyticsRefreshInterval);

        document.addEventListener('visibilitychange', () => {
            if (! document.hidden) {
                refreshDeviceList();
                refreshSelectedDevice();
                refreshEnergySummary();
            }
        });
    }

    async function refreshDeviceList() {
        try {
            const response = await fetchJson(`${apiBase}/devices`);
            const devices = Array.isArray(response.data) ? response.data : [];
            const selectedUid = state.selectedDevice?.device_uid;

            state.devices = devices;
            elements.deviceCount.textContent = String(devices.length);

            if (devices.length === 0) {
                showNoDevices();

                return;
            }

            const currentDevice = devices.find((device) => device.device_uid === selectedUid);

            if (currentDevice) {
                state.selectedDevice = currentDevice;
                renderDeviceList();
                renderDeviceIdentity();
            } else {
                selectDevice(devices[0]);
            }
        } catch (error) {
            if (state.devices.length === 0) {
                elements.deviceList.replaceChildren(createListState('Unable to load devices.'));
            }

            showAlert(error.message, true);
        }
    }

    async function selectDevice(device) {
        state.abortController?.abort();
        state.abortController = new AbortController();
        state.selectedDevice = device;
        state.selectionVersion++;
        state.analyticsInFlight = false;
        state.refreshInFlight = false;

        renderDeviceList();
        renderDeviceIdentity();
        clearMeasurementUi('Loading device measurements…');
        clearEnergyUi('Loading energy history…');
        showAlert(`Loading ${device.name}…`);

        await Promise.all([refreshSelectedDevice(), refreshEnergySummary()]);
    }

    async function refreshEnergySummary() {
        if (! state.selectedDevice || state.analyticsInFlight) {
            return;
        }

        const version = state.selectionVersion;
        const deviceUid = state.selectedDevice.device_uid;
        const signal = state.abortController?.signal;
        state.analyticsInFlight = true;

        try {
            const encodedDeviceUid = encodeURIComponent(deviceUid);
            const response = await fetchJson(`${apiBase}/devices/${encodedDeviceUid}/energy-summary`, signal);

            if (version !== state.selectionVersion || deviceUid !== state.selectedDevice?.device_uid) {
                return;
            }

            renderEnergySummary(response.data ?? null);
        } catch (error) {
            if (error.name !== 'AbortError' && version === state.selectionVersion) {
                clearEnergyUi('Energy analytics are temporarily unavailable.');
                showAlert(error.message, true);
            }
        } finally {
            if (version === state.selectionVersion) {
                state.analyticsInFlight = false;
            }
        }
    }

    async function refreshSelectedDevice() {
        if (! state.selectedDevice || state.refreshInFlight) {
            return;
        }

        const version = state.selectionVersion;
        const deviceUid = state.selectedDevice.device_uid;
        const signal = state.abortController?.signal;
        state.refreshInFlight = true;

        try {
            const encodedDeviceUid = encodeURIComponent(deviceUid);
            const [latestResponse, historyResponse] = await Promise.all([
                fetchJson(`${apiBase}/devices/${encodedDeviceUid}/latest`, signal),
                fetchJson(`${apiBase}/devices/${encodedDeviceUid}/measurements`, signal),
            ]);

            if (version !== state.selectionVersion || deviceUid !== state.selectedDevice?.device_uid) {
                return;
            }

            const latest = latestResponse.data ?? null;
            const measurements = Array.isArray(historyResponse.data) ? historyResponse.data : [];

            renderLatest(latest);
            renderHistory(measurements);
            elements.lastSync.textContent = formatTime(new Date());

            if (latest === null) {
                showAlert(latestResponse.message ?? `No measurements found for ${deviceUid}.`);
            } else {
                hideAlert();
            }
        } catch (error) {
            if (error.name !== 'AbortError' && version === state.selectionVersion) {
                showAlert(error.message, true);
            }
        } finally {
            if (version === state.selectionVersion) {
                state.refreshInFlight = false;
            }
        }
    }

    async function fetchJson(url, signal = undefined) {
        const response = await fetch(url, {
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
            },
            signal,
        });
        const payload = await response.json().catch(() => ({}));

        if (! response.ok) {
            throw new Error(payload.message ?? `API request failed with HTTP ${response.status}.`);
        }

        return payload;
    }

    function renderDeviceList() {
        const fragment = document.createDocumentFragment();

        state.devices.forEach((device) => {
            const button = document.createElement('button');
            const stateDot = document.createElement('span');
            const copy = document.createElement('span');
            const name = document.createElement('strong');
            const metadata = document.createElement('span');
            const uid = document.createElement('small');
            const transport = document.createElement('span');
            const status = document.createElement('span');
            const isSelected = state.selectedDevice?.device_uid === device.device_uid;

            button.type = 'button';
            button.className = `device-button${isSelected ? ' is-selected' : ''}`;
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            button.addEventListener('click', () => selectDevice(device));

            stateDot.className = `device-state-dot ${statusClass(device.status)}`;
            copy.className = 'device-button-copy';
            name.textContent = safeText(device.name);
            metadata.className = 'device-button-meta';
            uid.textContent = safeText(device.device_uid);
            transport.className = `device-transport-badge is-${normalizedTransport(device.transport)}`;
            transport.textContent = normalizedTransport(device.transport).toUpperCase();
            status.className = 'device-button-status';
            status.textContent = normalizedStatus(device.status);

            metadata.append(uid, transport);
            copy.append(name, metadata);
            button.append(stateDot, copy, status);
            fragment.append(button);
        });

        elements.deviceList.replaceChildren(fragment);
    }

    function renderDeviceIdentity() {
        const device = state.selectedDevice;

        if (! device) {
            return;
        }

        elements.selectedName.textContent = safeText(device.name);
        elements.selectedUid.textContent = safeText(device.device_uid);
        elements.selectedTransport.className = `transport-badge is-${normalizedTransport(device.transport)}`;
        elements.selectedTransport.textContent = normalizedTransport(device.transport).toUpperCase();
        renderStatus(device.status);
        renderLastSeen(device.last_seen_at);
    }

    function renderLatest(latest) {
        if (latest === null) {
            setMeasurementValues(null);
            renderStatus(state.selectedDevice?.status);
            renderLastSeen(state.selectedDevice?.last_seen_at);

            return;
        }

        state.selectedDevice.status = latest.status;
        state.selectedDevice.last_seen_at = latest.last_seen_at;
        updateDeviceInList(state.selectedDevice);
        renderDeviceList();
        renderDeviceIdentity();
        setMeasurementValues(latest);
    }

    function setMeasurementValues(measurement) {
        const power = finiteNumber(measurement?.power);
        const voltage = finiteNumber(measurement?.voltage);
        const current = finiteNumber(measurement?.current);

        elements.power.textContent = formatNumber(power, 1);
        elements.voltage.textContent = formatNumber(voltage, 1);
        elements.current.textContent = formatNumber(current, 2);
        elements.infoVoltage.textContent = formatNumber(voltage, 1);
        elements.infoCurrent.textContent = formatNumber(current, 2);

        const gaugePercentage = power === null ? 0 : Math.min(Math.max(power / 1200, 0), 1) * 100;
        elements.gauge.style.strokeDashoffset = String(100 - gaugePercentage);
    }

    function renderStatus(status) {
        const normalized = normalizedStatus(status);
        const className = statusClass(normalized);

        elements.headerStatus.className = `status-badge ${className}`;
        elements.headerStatusLabel.textContent = normalized;
        elements.infoStatus.textContent = normalized;
        elements.infoStatusDetail.textContent = normalized === 'online'
            ? 'Receiving measurements'
            : normalized === 'offline' ? 'Device not reporting' : 'Waiting for device';
        elements.liveChip.classList.toggle('is-offline', normalized === 'offline');
        elements.liveChipLabel.textContent = normalized === 'offline' ? 'Offline' : 'Live';
    }

    function renderLastSeen(timestamp) {
        elements.headerLastSeen.textContent = formatDateTime(timestamp);
        elements.infoLastSeen.textContent = formatDateTime(timestamp, true);
        elements.infoLastSeenDetail.textContent = relativeTime(timestamp);
    }

    function renderHistory(measurements) {
        const recentMeasurements = measurements.slice(0, 10);

        renderChart(measurements);
        renderTable(recentMeasurements);
    }

    function renderChart(measurements) {
        const chronological = [...measurements].reverse();

        state.chart.data.labels = chronological.map((measurement) => formatTime(measurement.measured_at));
        state.chart.data.datasets[0].data = chronological.map((measurement) => finiteNumber(measurement.power) ?? 0);
        state.chart.update('none');
        elements.chartPointCount.textContent = `${measurements.length} ${measurements.length === 1 ? 'reading' : 'readings'}`;
        elements.chartEmpty.hidden = measurements.length > 0;

        if (measurements.length === 0) {
            elements.chartEmpty.textContent = 'No measurements have been recorded for this device.';
        }
    }

    function renderTable(measurements) {
        if (measurements.length === 0) {
            elements.tableBody.replaceChildren(createTableState('No measurements have been recorded for this device.'));

            return;
        }

        const fragment = document.createDocumentFragment();

        measurements.forEach((measurement) => {
            const row = document.createElement('tr');
            const timeCell = document.createElement('td');

            timeCell.textContent = formatDateTime(measurement.measured_at);
            row.append(
                timeCell,
                createMeasurementCell(measurement.voltage, 1, 'V'),
                createMeasurementCell(measurement.current, 2, 'A'),
                createMeasurementCell(measurement.power, 1, 'W', true),
            );
            fragment.append(row);
        });

        elements.tableBody.replaceChildren(fragment);
    }

    function renderEnergySummary(summary) {
        const hasData = summary?.has_data ?? {};

        renderEnergyCard(
            elements.energyToday,
            elements.energyTodayDetail,
            summary?.today_kwh,
            hasData.today === true,
        );
        renderEnergyCard(
            elements.dailyEnergyAverage,
            elements.dailyEnergyAverageDetail,
            summary?.daily_average_kwh,
            hasData.daily_average === true,
        );
        renderEnergyCard(
            elements.energyCurrentMonth,
            elements.energyCurrentMonthDetail,
            summary?.current_month_kwh,
            hasData.current_month === true,
        );
        renderEnergyCard(
            elements.energyMonthlyAverage,
            elements.energyMonthlyAverageDetail,
            summary?.monthly_average_kwh,
            hasData.monthly_average === true,
        );

        renderEnergySeries(
            state.dailyEnergyChart,
            elements.dailyEnergyEmpty,
            Array.isArray(summary?.last_30_days) ? summary.last_30_days : [],
            'date',
            formatDayLabel,
            'No usable daily energy intervals are available.',
        );
        renderEnergySeries(
            state.monthlyEnergyChart,
            elements.monthlyEnergyEmpty,
            Array.isArray(summary?.last_12_months) ? summary.last_12_months : [],
            'month',
            formatMonthLabel,
            'No usable monthly energy intervals are available.',
        );
    }

    function renderEnergyCard(valueElement, detailElement, value, hasData) {
        const energy = finiteNumber(value);

        valueElement.textContent = hasData && energy !== null ? formatEnergy(energy) : '—';
        detailElement.textContent = hasData ? 'Calculated from telemetry' : 'No usable intervals';
    }

    function renderEnergySeries(chart, emptyElement, series, key, labelFormatter, emptyMessage) {
        const hasUsableData = series.some((item) => item?.has_data === true);

        chart.data.labels = series.map((item) => labelFormatter(item?.[key]));
        chart.data.datasets[0].data = series.map((item) => item?.has_data === true
            ? finiteNumber(item.energy_kwh)
            : null);
        chart.update('none');
        emptyElement.hidden = hasUsableData;
        emptyElement.textContent = emptyMessage;
    }

    function createPowerChart() {
        const canvas = document.querySelector('#power-trend-chart');

        return new Chart(canvas, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    label: 'Power',
                    borderColor: '#21b7a8',
                    backgroundColor: 'rgba(33, 183, 168, 0.1)',
                    borderWidth: 2.25,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#159b8f',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6,
                    pointRadius: (context) => context.dataIndex === context.dataset.data.length - 1 ? 4 : 1.5,
                    tension: 0.35,
                }],
            },
            options: {
                animation: {
                    duration: 300,
                },
                maintainAspectRatio: false,
                responsive: true,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#142521',
                        displayColors: false,
                        padding: 10,
                        callbacks: {
                            label: (context) => `${formatNumber(context.parsed.y, 1)} W`,
                        },
                    },
                },
                scales: {
                    x: {
                        border: {
                            display: false,
                        },
                        grid: {
                            display: false,
                        },
                        ticks: {
                            autoSkip: true,
                            color: '#82908c',
                            font: {
                                size: 10,
                            },
                            maxRotation: 0,
                            maxTicksLimit: 7,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false,
                        },
                        grid: {
                            color: 'rgba(103, 122, 117, 0.12)',
                        },
                        ticks: {
                            color: '#82908c',
                            font: {
                                size: 10,
                            },
                            callback: (value) => `${value} W`,
                            maxTicksLimit: 5,
                        },
                    },
                },
            },
        });
    }

    function createEnergyChart(selector, label, color) {
        return new Chart(document.querySelector(selector), {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    backgroundColor: color,
                    borderColor: color,
                    borderRadius: 5,
                    borderSkipped: false,
                    data: [],
                    label,
                    maxBarThickness: 28,
                }],
            },
            options: {
                animation: {
                    duration: 300,
                },
                maintainAspectRatio: false,
                responsive: true,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#142521',
                        displayColors: false,
                        padding: 10,
                        callbacks: {
                            label: (context) => `${formatEnergy(context.parsed.y)} kWh`,
                        },
                    },
                },
                scales: {
                    x: {
                        border: {
                            display: false,
                        },
                        grid: {
                            display: false,
                        },
                        ticks: {
                            autoSkip: true,
                            color: '#82908c',
                            font: {
                                size: 10,
                            },
                            maxRotation: 0,
                            maxTicksLimit: 8,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false,
                        },
                        grid: {
                            color: 'rgba(103, 122, 117, 0.12)',
                        },
                        ticks: {
                            color: '#82908c',
                            font: {
                                size: 10,
                            },
                            callback: (value) => `${value} kWh`,
                            maxTicksLimit: 5,
                        },
                    },
                },
            },
        });
    }

    function clearMeasurementUi(message) {
        setMeasurementValues(null);
        state.chart.data.labels = [];
        state.chart.data.datasets[0].data = [];
        state.chart.update('none');
        elements.chartPointCount.textContent = '0 readings';
        elements.chartEmpty.hidden = false;
        elements.chartEmpty.textContent = message;
        elements.tableBody.replaceChildren(createTableState(message));
    }

    function clearEnergyUi(message) {
        [
            [elements.energyToday, elements.energyTodayDetail],
            [elements.dailyEnergyAverage, elements.dailyEnergyAverageDetail],
            [elements.energyCurrentMonth, elements.energyCurrentMonthDetail],
            [elements.energyMonthlyAverage, elements.energyMonthlyAverageDetail],
        ].forEach(([value, detail]) => {
            value.textContent = '—';
            detail.textContent = message;
        });

        [
            [state.dailyEnergyChart, elements.dailyEnergyEmpty],
            [state.monthlyEnergyChart, elements.monthlyEnergyEmpty],
        ].forEach(([chart, empty]) => {
            chart.data.labels = [];
            chart.data.datasets[0].data = [];
            chart.update('none');
            empty.hidden = false;
            empty.textContent = message;
        });
    }

    function showNoDevices() {
        state.abortController?.abort();
        state.selectedDevice = null;
        state.selectionVersion++;
        elements.deviceList.replaceChildren(createListState('No devices available.'));
        elements.selectedName.textContent = 'No devices found';
        elements.selectedUid.textContent = '—';
        elements.selectedTransport.className = 'transport-badge is-neutral';
        elements.selectedTransport.textContent = '—';
        renderStatus('waiting');
        renderLastSeen(null);
        clearMeasurementUi('Create a device to begin monitoring energy usage.');
        clearEnergyUi('Create a device to begin tracking energy consumption.');
        showAlert('No devices are available. Create a device and refresh the dashboard.');
    }

    function createMeasurementCell(value, precision, unit, emphasize = false) {
        const cell = document.createElement('td');
        const number = document.createElement(emphasize ? 'strong' : 'span');
        const unitLabel = document.createElement('span');

        number.textContent = formatNumber(finiteNumber(value), precision);
        unitLabel.className = 'measurement-unit';
        unitLabel.textContent = unit;
        cell.append(number, unitLabel);

        return cell;
    }

    function createTableState(message) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');

        row.className = 'table-state-row';
        cell.colSpan = 4;
        cell.textContent = message;
        row.append(cell);

        return row;
    }

    function createListState(message) {
        const element = document.createElement('div');

        element.className = 'device-list-state';
        element.textContent = message;

        return element;
    }

    function updateDeviceInList(updatedDevice) {
        state.devices = state.devices.map((device) => device.device_uid === updatedDevice.device_uid
            ? { ...device, ...updatedDevice }
            : device);
    }

    function showAlert(message, isError = false) {
        elements.alert.textContent = safeText(message);
        elements.alert.className = `dashboard-alert${isError ? ' is-error' : ''}`;
        elements.alert.hidden = false;
    }

    function hideAlert() {
        elements.alert.hidden = true;
    }

    function normalizedStatus(status) {
        return typeof status === 'string' && status.trim() !== '' ? status.toLowerCase() : 'waiting';
    }

    function statusClass(status) {
        const normalized = normalizedStatus(status);

        if (normalized === 'online') {
            return 'is-online';
        }

        if (normalized === 'offline') {
            return 'is-offline';
        }

        return 'is-neutral';
    }

    function normalizedTransport(transport) {
        return transport === 'http' || transport === 'mqtt' ? transport : 'neutral';
    }

    function finiteNumber(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number : null;
    }

    function formatNumber(value, precision) {
        return value === null ? '—' : value.toFixed(precision);
    }

    function formatEnergy(value) {
        const energy = finiteNumber(value);

        if (energy === null) {
            return '—';
        }

        const precision = energy !== 0 && Math.abs(energy) < 0.01 ? 4 : 2;

        return energy.toFixed(precision);
    }

    function formatDayLabel(value) {
        if (typeof value !== 'string' || ! /^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return '—';
        }

        const [year, month, day] = value.split('-').map(Number);

        return new Intl.DateTimeFormat(undefined, {
            day: 'numeric',
            month: 'short',
            timeZone: 'UTC',
        }).format(new Date(Date.UTC(year, month - 1, day)));
    }

    function formatMonthLabel(value) {
        if (typeof value !== 'string' || ! /^\d{4}-\d{2}$/.test(value)) {
            return '—';
        }

        const [year, month] = value.split('-').map(Number);

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            timeZone: 'UTC',
            year: '2-digit',
        }).format(new Date(Date.UTC(year, month - 1, 1)));
    }

    function formatDateTime(timestamp, compact = false) {
        const date = parseDate(timestamp);

        if (date === null) {
            return '—';
        }

        const options = compact
            ? { hour: '2-digit', minute: '2-digit' }
            : {
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                month: 'short',
                second: '2-digit',
                year: 'numeric',
            };

        return new Intl.DateTimeFormat(undefined, options).format(date);
    }

    function formatTime(timestamp) {
        const date = timestamp instanceof Date ? timestamp : parseDate(timestamp);

        if (date === null || Number.isNaN(date.getTime())) {
            return '—';
        }

        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(date);
    }

    function relativeTime(timestamp) {
        const date = parseDate(timestamp);

        if (date === null) {
            return 'No recent contact';
        }

        const elapsedSeconds = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000));

        if (elapsedSeconds < 10) {
            return 'Just now';
        }

        if (elapsedSeconds < 60) {
            return `${elapsedSeconds} seconds ago`;
        }

        const elapsedMinutes = Math.floor(elapsedSeconds / 60);

        if (elapsedMinutes < 60) {
            return `${elapsedMinutes} ${elapsedMinutes === 1 ? 'minute' : 'minutes'} ago`;
        }

        const elapsedHours = Math.floor(elapsedMinutes / 60);

        if (elapsedHours < 24) {
            return `${elapsedHours} ${elapsedHours === 1 ? 'hour' : 'hours'} ago`;
        }

        const elapsedDays = Math.floor(elapsedHours / 24);

        return `${elapsedDays} ${elapsedDays === 1 ? 'day' : 'days'} ago`;
    }

    function parseDate(timestamp) {
        if (typeof timestamp !== 'string' || timestamp === '') {
            return null;
        }

        const date = new Date(timestamp);

        return Number.isNaN(date.getTime()) ? null : date;
    }

    function safeText(value) {
        return typeof value === 'string' && value.trim() !== '' ? value : '—';
    }
}
