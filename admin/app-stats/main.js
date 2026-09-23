document.addEventListener("DOMContentLoaded", function () {
  const rawData = window.dashboardData;
  // The page omits the payload (and normally this script) when it renders its
  // empty state: no data for the selected range or tier. Nothing to draw.
  if (!rawData) {
    return;
  }
  const isGeoEnabled = rawData.geoLocationEnabled || false;

  const typeColors = {
    ExcelSheetsChart: "#3b82f6",
    GoogleSheetsChart: "#10b981",
    Backup: "#f59e0b",
    XLSX: "#ef4444",
    Receipts: "#1e40af",
  };

  const countryColors = {
    "United States": "#3b82f6",
    Canada: "#ef4444",
    "United Kingdom": "#10b981",
    Germany: "#f59e0b",
    Australia: "#1e40af",
    France: "#06b6d4",
    Netherlands: "#84cc16",
    Japan: "#f97316",
    Brazil: "#ec4899",
    India: "#60a5fa",
  };

  if (!rawData.dataPoints) {
    return;
  }

  const exportData = rawData.dataPoints.Export || [];
  const geminiData = rawData.dataPoints.Gemini || [];
  const exchangeRatesPerDateData = rawData.dataPoints.OpenExchangeRates || [];
  const exchangeRatesBatchData = rawData.dataPoints.OpenExchangeRatesBatch || [];
  // Every other chart keeps treating rate traffic as one series, which is what it was before
  // the bulk call got its own name. Only the exchange-rates chart splits them.
  const exchangeRatesData = [
    ...exchangeRatesPerDateData,
    ...exchangeRatesBatchData,
  ];
  const receiptScanningData = rawData.dataPoints.ReceiptScanning || [];
  const sessionData = rawData.dataPoints.Session || [];
  const errorData = rawData.dataPoints.Error || [];
  const featureUsageData = rawData.dataPoints.FeatureUsage || [];
  const startupData = rawData.dataPoints.Startup || [];
  const pageViewData = rawData.dataPoints.PageView || [];

  // Initialize all charts
  if (isGeoEnabled) {
    generateCountryDistributionChart(
      exportData,
      geminiData,
      exchangeRatesData,
      sessionData,
      errorData
    );
    generatePerformanceByCountryChart(
      exportData,
      geminiData,
      exchangeRatesData
    );
    generateErrorRatesByCountryChart(
      errorData,
      exportData,
      geminiData,
      exchangeRatesData
    );
    generateSessionDurationByRegionChart(sessionData);
    generateTimezoneChart(
      exportData,
      geminiData,
      exchangeRatesData,
      sessionData,
      errorData
    );
  }

  generateVersionDistributionChart(
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData,
    errorData
  );
  generateVersionTimeChart(
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData,
    errorData
  );
  generateVersionSessionChart(sessionData);
  generateVersionErrorChart(
    errorData,
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData
  );

  generateErrorCategoryChart(errorData);
  generateErrorCodeChart(errorData);
  generateErrorCategoryTimelineChart(errorData);
  generateErrorDetailsTable(errorData);

  // Feature Usage Charts
  generateFeatureUsageChart(featureUsageData);
  generateFeatureTimelineChart(featureUsageData);

  generatePageViewsTable(pageViewData);

  // Receipt Scanning Charts
  generateReceiptScanOverviewChart(receiptScanningData);
  generateReceiptScanSuccessChart(receiptScanningData);
  generateReceiptScanDurationChart(receiptScanningData);
  generateReceiptScanTrendChart(receiptScanningData);

  // AI Spreadsheet Importer Charts
  generateAIImportStats(featureUsageData);
  generateAIImportOverviewChart(featureUsageData);
  generateAIImportTrendChart(featureUsageData);
  generateAIImportTypeTimeChart(featureUsageData);
  generateAIImportDurationChart(featureUsageData);
  generateAIImportDurationByTypeChart(featureUsageData);

  generateStartupCharts(startupData);
  generateMemoryKpis(sessionData);
  generateSessionDurationChart(sessionData);
  generateExportTypesBreakdown(exportData);
  generateExportDurationByTypeChart(exportData);
  generateExportFileSizeByTypeChart(exportData);

  generateGeminiChart(geminiData);
  generateGeminiResponseTimeChart(geminiData);
  generateExchangeRatesChart(exchangeRatesPerDateData, exchangeRatesBatchData);

  generateActiveUsersTab(rawData);

  // Geographic Charts
  function generateCountryDistributionChart(
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData,
    errorData
  ) {
    const allData = [
      ...exportData,
      ...geminiData,
      ...exchangeRatesData,
      ...sessionData,
      ...errorData,
    ];
    const countryCounts = {};

    allData.forEach((item) => {
      const country = item.country || "Unknown";
      if (country !== "Unknown") {
        countryCounts[country] = (countryCounts[country] || 0) + 1;
      }
    });

    if (Object.keys(countryCounts).length === 0) {
      document.getElementById(
        "countryDistributionChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No country data available</div>';
      return;
    }

    const sortedCountries = Object.entries(countryCounts)
      .sort(([, a], [, b]) => b - a)
      .slice(0, 15);

    const labels = sortedCountries.map(([country]) => country);
    const data = sortedCountries.map(([, count]) => count);
    const colors = labels.map((country) => countryColors[country] || "#9ca3af");

    new Chart(document.getElementById("countryDistributionChart"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Operations",
            data: data,
            backgroundColor: colors,
            borderColor: colors.map((c) => c.replace("0.8", "1")),
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: "y",
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Number of Operations",
            },
          },
        }
      },
    });
  }

  function generatePerformanceByCountryChart(
    exportData,
    geminiData,
    exchangeRatesData
  ) {
    const countryPerformance = {};

    const addPerformanceData = (items, type) => {
      items.forEach((item) => {
        const country = item.country || "Unknown";
        const duration = parseFloat(item.DurationMS || 0);

        if (country !== "Unknown" && duration > 0) {
          if (!countryPerformance[country]) {
            countryPerformance[country] = [];
          }
          countryPerformance[country].push(duration);
        }
      });
    };

    addPerformanceData(exportData, "export");
    addPerformanceData(geminiData, "gemini");
    addPerformanceData(exchangeRatesData, "exchangeRates");

    if (Object.keys(countryPerformance).length === 0) {
      document.getElementById(
        "performanceByCountryChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No performance data by country available</div>';
      return;
    }

    const countryAverages = Object.entries(countryPerformance)
      .map(([country, durations]) => ({
        country,
        avgDuration:
          durations.reduce((sum, d) => sum + d, 0) / durations.length,
        count: durations.length,
      }))
      .filter((item) => item.count >= 5)
      .sort((a, b) => a.avgDuration - b.avgDuration)
      .slice(0, 10);

    new Chart(document.getElementById("performanceByCountryChart"), {
      type: "bar",
      data: {
        labels: countryAverages.map((item) => item.country),
        datasets: [
          {
            label: "Average Duration (ms)",
            data: countryAverages.map((item) => Math.round(item.avgDuration)),
            backgroundColor: "#10b981",
            borderColor: "#059669",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Average Duration (ms)",
            },
          },
        }
      },
    });
  }

  function generateErrorRatesByCountryChart(
    errorData,
    exportData,
    geminiData,
    exchangeRatesData
  ) {
    if (errorData.length === 0) {
      document.getElementById(
        "errorRatesByCountryChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No error data by country available</div>';
      return;
    }

    const countryErrors = {};
    const countryOperations = {};

    errorData.forEach((error) => {
      const country = error.country || "Unknown";
      if (country !== "Unknown") {
        countryErrors[country] = (countryErrors[country] || 0) + 1;
      }
    });

    [...exportData, ...geminiData, ...exchangeRatesData].forEach((item) => {
      const country = item.country || "Unknown";
      if (country !== "Unknown") {
        countryOperations[country] = (countryOperations[country] || 0) + 1;
      }
    });

    const countryErrorRates = Object.keys(countryErrors)
      .map((country) => ({
        country,
        errorRate:
          ((countryErrors[country] || 0) / (countryOperations[country] || 1)) *
          100,
        errors: countryErrors[country] || 0,
        operations: countryOperations[country] || 0,
      }))
      .filter((item) => item.operations >= 5)
      .sort((a, b) => b.errorRate - a.errorRate);

    new Chart(document.getElementById("errorRatesByCountryChart"), {
      type: "bar",
      data: {
        labels: countryErrorRates.map((item) => item.country),
        datasets: [
          {
            label: "Error Rate (%)",
            data: countryErrorRates.map((item) => item.errorRate.toFixed(2)),
            backgroundColor: "#ef4444",
            borderColor: "#dc2626",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const item = countryErrorRates[context.dataIndex];
                return `${item.country}: ${item.errorRate.toFixed(2)}% (${item.errors
                  }/${item.operations})`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Error Rate (%)",
            },
          },
        }
      },
    });
  }

  function generateSessionDurationByRegionChart(sessionData) {
    const sessionEndData = sessionData.filter(
      (s) => s.action === "SessionEnd" && s.duration > 0
    );

    if (sessionEndData.length === 0) {
      document.getElementById(
        "sessionDurationByRegionChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No session duration data by region available</div>';
      return;
    }

    const regionDurations = {};
    sessionEndData.forEach((session) => {
      const country = session.country || "Unknown";
      if (country !== "Unknown") {
        if (!regionDurations[country]) {
          regionDurations[country] = [];
        }
        regionDurations[country].push(parseFloat(session.duration));
      }
    });

    const regionAverages = Object.entries(regionDurations)
      .map(([country, durations]) => ({
        country,
        avgDuration:
          durations.reduce((sum, d) => sum + d, 0) / durations.length,
        count: durations.length,
      }))
      .filter((item) => item.count >= 2)
      .sort((a, b) => b.avgDuration - a.avgDuration);

    new Chart(document.getElementById("sessionDurationByRegionChart"), {
      type: "bar",
      data: {
        labels: regionAverages.map((item) => item.country),
        datasets: [
          {
            label: "Average Session Duration (seconds)",
            data: regionAverages.map((item) => Math.round(item.avgDuration)),
            backgroundColor: "#06b6d4",
            borderColor: "#0891b2",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Average Duration (seconds)",
            },
          },
        }
      },
    });
  }

  function generateTimezoneChart(
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData,
    errorData
  ) {
    const allData = [
      ...exportData,
      ...geminiData,
      ...exchangeRatesData,
      ...sessionData,
      ...errorData,
    ];
    const timezoneCounts = {};

    allData.forEach((item) => {
      const timezone = item.timezone || "Unknown";
      if (timezone !== "Unknown") {
        timezoneCounts[timezone] = (timezoneCounts[timezone] || 0) + 1;
      }
    });

    if (Object.keys(timezoneCounts).length === 0) {
      document.getElementById("timezoneChart").parentElement.innerHTML =
        '<div class="chart-no-data">No timezone data available</div>';
      return;
    }

    const sortedTimezones = Object.entries(timezoneCounts)
      .sort(([, a], [, b]) => b - a)
      .slice(0, 10);

    new Chart(document.getElementById("timezoneChart"), {
      type: "bar",
      data: {
        labels: sortedTimezones.map(([tz]) => tz.replace("/", "/\n")),
        datasets: [
          {
            label: "Operations",
            data: sortedTimezones.map(([, count]) => count),
            backgroundColor: "#3b82f6",
            borderColor: "#2563eb",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Number of Operations",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        }
      },
    });
  }

  // Version Charts
  function generateVersionDistributionChart(
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData,
    errorData
  ) {
    const allData = [
      ...exportData,
      ...geminiData,
      ...exchangeRatesData,
      ...sessionData,
      ...errorData,
    ];
    const versionCounts = {};

    allData.forEach((item) => {
      const version = item.appVersion || "Unknown";
      if (version !== "Unknown") {
        versionCounts[version] = (versionCounts[version] || 0) + 1;
      }
    });

    if (Object.keys(versionCounts).length === 0) {
      document.getElementById(
        "versionDistributionChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No version data available</div>';
      return;
    }

    const sortedVersions = Object.entries(versionCounts).sort(
      ([, a], [, b]) => b - a
    );

    const labels = sortedVersions.map(([version]) => `V.${version}`);
    const data = sortedVersions.map(([, count]) => count);
    const colors = [
      "#3b82f6",
      "#10b981",
      "#f59e0b",
      "#ef4444",
      "#1e40af",
      "#06b6d4",
      "#84cc16",
      "#f97316",
      "#ec4899",
      "#60a5fa",
    ];

    new Chart(document.getElementById("versionDistributionChart"), {
      type: "pie",
      data: {
        labels: labels,
        datasets: [
          {
            data: data,
            backgroundColor: colors.slice(0, labels.length),
            borderColor: colors
              .slice(0, labels.length)
              .map((c) => c.replace("0.8", "1")),
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((context.raw / total) * 100);
                return `${context.label}: ${context.raw} operations (${percentage}%)`;
              },
            },
          },
        }
      },
    });
  }

  function generateVersionTimeChart(
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData,
    errorData
  ) {
    const allData = [
      ...exportData,
      ...geminiData,
      ...exchangeRatesData,
      ...sessionData,
      ...errorData,
    ];

    const versionByDate = {};
    const versions = new Set();

    allData.forEach((item) => {
      const date = new Date(item.timestamp).toLocaleDateString();
      const version = item.appVersion || "Unknown";

      if (version !== "Unknown") {
        versions.add(version);
        if (!versionByDate[date]) {
          versionByDate[date] = {};
        }
        versionByDate[date][version] = (versionByDate[date][version] || 0) + 1;
      }
    });

    if (versions.size === 0) {
      document.getElementById("versionTimeChart").parentElement.innerHTML =
        '<div class="chart-no-data">No version timeline data available</div>';
      return;
    }

    const recentDates = Object.keys(versionByDate).sort();
    const topVersions = Array.from(versions).slice(0, 5);

    const datasets = topVersions.map((version, index) => ({
      label: `V.${version}`,
      data: recentDates.map((date) => versionByDate[date]?.[version] || 0),
      borderColor: ["#3b82f6", "#10b981", "#f59e0b", "#ef4444", "#1e40af"][
        index
      ],
      backgroundColor: [
        "rgba(59, 130, 246, 0.1)",
        "rgba(16, 185, 129, 0.1)",
        "rgba(245, 158, 11, 0.1)",
        "rgba(239, 68, 68, 0.1)",
        "rgba(30, 64, 175, 0.1)",
      ][index],
      fill: true,
      tension: 0.4,
    }));

    new Chart(document.getElementById("versionTimeChart"), {
      type: "line",
      data: {
        labels: recentDates,
        datasets: datasets,
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Operations Count",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        }
      },
    });
  }

  function generateVersionSessionChart(sessionData) {
    const sessionEndData = sessionData.filter(
      (s) => s.action === "SessionEnd" && s.duration > 0
    );

    if (sessionEndData.length === 0) {
      document.getElementById("versionSessionChart").parentElement.innerHTML =
        '<div class="chart-no-data">No session duration by version data available</div>';
      return;
    }

    const versionSessions = {};
    sessionEndData.forEach((session) => {
      const version = session.appVersion || "Unknown";
      if (version !== "Unknown") {
        if (!versionSessions[version]) {
          versionSessions[version] = [];
        }
        versionSessions[version].push(parseFloat(session.duration));
      }
    });

    const versionAverages = Object.entries(versionSessions)
      .map(([version, durations]) => ({
        version,
        avgDuration:
          durations.reduce((sum, d) => sum + d, 0) / durations.length,
        count: durations.length,
      }))
      .filter((item) => item.count >= 3)
      .sort((a, b) => b.avgDuration - a.avgDuration);

    const labels = versionAverages.map((item) => `V.${item.version}`);
    const averages = versionAverages.map((item) =>
      Math.round(item.avgDuration)
    );

    new Chart(document.getElementById("versionSessionChart"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Avg Session Duration (seconds)",
            data: averages,
            backgroundColor: "#06b6d4",
            borderColor: "#0891b2",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const versionData = versionAverages[context.dataIndex];
                return `${context.label}: ${context.raw}s avg (${versionData.count} sessions)`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Average Duration (seconds)",
            },
          },
        }
      },
    });
  }

  function generateVersionErrorChart(
    errorData,
    exportData,
    geminiData,
    exchangeRatesData,
    sessionData
  ) {
    if (errorData.length === 0) {
      document.getElementById("versionErrorChart").parentElement.innerHTML =
        '<div class="chart-no-data">No error data by version available</div>';
      return;
    }

    const versionErrors = {};
    const versionOperations = {};

    errorData.forEach((error) => {
      const version = error.appVersion || "Unknown";
      if (version !== "Unknown") {
        versionErrors[version] = (versionErrors[version] || 0) + 1;
      }
    });

    [
      ...exportData,
      ...geminiData,
      ...exchangeRatesData,
      ...sessionData,
    ].forEach((item) => {
      const version = item.appVersion || "Unknown";
      if (version !== "Unknown") {
        versionOperations[version] = (versionOperations[version] || 0) + 1;
      }
    });

    const versionErrorRates = Object.keys(versionErrors)
      .map((version) => ({
        version,
        errorRate:
          ((versionErrors[version] || 0) / (versionOperations[version] || 1)) *
          100,
        errors: versionErrors[version] || 0,
        operations: versionOperations[version] || 0,
      }))
      .filter((item) => item.operations >= 10)
      .sort((a, b) => b.errorRate - a.errorRate);

    const labels = versionErrorRates.map((item) => `V.${item.version}`);
    const errorRates = versionErrorRates.map((item) =>
      item.errorRate.toFixed(2)
    );

    new Chart(document.getElementById("versionErrorChart"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Error Rate (%)",
            data: errorRates,
            backgroundColor: "#ef4444",
            borderColor: "#dc2626",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const item = versionErrorRates[context.dataIndex];
                return `V.${item.version}: ${item.errorRate.toFixed(2)}% (${item.errors
                  }/${item.operations})`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Error Rate (%)",
            },
          },
        }
      },
    });
  }

  // Error Charts
  function generateErrorCategoryChart(errorData) {
    if (errorData.length === 0) {
      document.getElementById("errorCategoryChart").parentElement.innerHTML =
        '<div class="chart-no-data">No error data available</div>';
      return;
    }

    const categoryCounts = {};
    errorData.forEach((error) => {
      const category = error.ErrorCategory || "Unknown";
      categoryCounts[category] = (categoryCounts[category] || 0) + 1;
    });

    const sortedCategories = Object.entries(categoryCounts).sort(
      ([, a], [, b]) => b - a
    );
    const labels = sortedCategories.map(([category]) => category);
    const data = sortedCategories.map(([, count]) => count);
    const colors = [
      "#ef4444",
      "#f97316",
      "#eab308",
      "#84cc16",
      "#22c55e",
      "#06b6d4",
      "#3b82f6",
      "#1e40af",
    ];

    new Chart(document.getElementById("errorCategoryChart"), {
      type: "pie",
      data: {
        labels: labels,
        datasets: [
          {
            data: data,
            backgroundColor: colors.slice(0, labels.length),
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((context.raw / total) * 100);
                return `${context.label}: ${context.raw} errors (${percentage}%)`;
              },
            },
          },
        }
      },
    });
  }


  function generateErrorCodeChart(errorData) {
    if (errorData.length === 0) {
      document.getElementById("errorCodeChart").parentElement.innerHTML =
        '<div class="chart-no-data">No error data available</div>';
      return;
    }

    const codeCounts = {};
    errorData.forEach((error) => {
      const code = error.ErrorCode || "Unknown";
      codeCounts[code] = (codeCounts[code] || 0) + 1;
    });

    const sortedCodes = Object.entries(codeCounts).sort(
      ([, a], [, b]) => b - a
    );
    const labels = sortedCodes.map(([code]) => code);
    const data = sortedCodes.map(([, count]) => count);
    const colors = [
      "#ef4444",
      "#f97316",
      "#eab308",
      "#84cc16",
      "#22c55e",
      "#06b6d4",
      "#3b82f6",
      "#1e40af",
      "#ec4899",
      "#14b8a6",
    ];

    new Chart(document.getElementById("errorCodeChart"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Occurrences",
            data: data,
            backgroundColor: colors.slice(0, labels.length).concat(
              Array(Math.max(0, labels.length - colors.length)).fill("#94a3b8")
            ),
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: labels.length > 8 ? "y" : "x",
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((context.raw / total) * 100);
                return `${context.raw} errors (${percentage}%)`;
              },
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
          },
          y: {
            beginAtZero: true,
          },
        },
      },
    });
  }

  function generateErrorDetailsTable(errorData) {
    const wrapper = document.getElementById("errorDetailsTableWrapper");
    if (errorData.length === 0) {
      wrapper.innerHTML =
        '<p style="text-align: center; color: #9ca3af;">No error data available</p>';
      return;
    }

    const sorted = [...errorData].sort(
      (a, b) => new Date(b.timestamp) - new Date(a.timestamp)
    );

    let html =
      '<table class="error-details-table"><thead><tr>' +
      "<th>Time</th>" +
      "<th>Category</th>" +
      "<th>Error Code</th>" +
      "<th>Message</th>" +
      "<th>Source</th>" +
      "<th>Version</th>" +
      "</tr></thead><tbody>";

    sorted.forEach((error) => {
      const time = new Date(error.timestamp).toLocaleString();
      const category = error.ErrorCategory || "Unknown";
      const code = error.ErrorCode || "—";
      // Errors upload no message by design; the call site's description stands in.
      const message = error.Message || error.Context || "—";
      let source = "—";
      if (error.SourceFile) {
        source = error.SourceFile;
        if (error.LineNumber) source += ":" + error.LineNumber;
        if (error.MethodName) source += " (" + error.MethodName + ")";
      } else if (error.MethodName) {
        source = error.MethodName;
        if (error.LineNumber) source += ":" + error.LineNumber;
      }
      const version = error.appVersion || "—";

      html +=
        "<tr>" +
        `<td>${escapeHtml(time)}</td>` +
        `<td>${escapeHtml(category)}</td>` +
        `<td class="error-code">${escapeHtml(code)}</td>` +
        `<td>${escapeHtml(message)}</td>` +
        `<td class="error-source">${escapeHtml(source)}</td>` +
        `<td>${escapeHtml(version)}</td>` +
        "</tr>";
    });

    html += "</tbody></table>";
    wrapper.innerHTML = html;
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // Usage Charts
  /**
   * Startup timings. ToReadyMs contains ToFirstPaintMs rather than continuing
   * from it, so the two are nested and must never be summed.
   *
   * Cold and warm are reported separately on purpose. A warm launch reads its
   * files from the OS cache, so blending them pulls the average down exactly
   * when a slow first launch is what we are trying to see.
   */
  /**
   * Peak memory per session. Sessions predating the fields, and ends reconstructed
   * after a force-quit, carry no measurement and are left out rather than counted
   * as zero.
   */
  function generateMemoryKpis(sessionData) {
    const setText = (id, text) => {
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    };

    const nums = (key) =>
      sessionData
        .map((r) => r[key])
        .filter((v) => typeof v === "number" && isFinite(v) && v > 0)
        .sort((a, b) => a - b);

    const percentile = (sorted, p) => {
      if (sorted.length === 0) return null;
      const rank = Math.ceil((p / 100) * sorted.length);
      return sorted[Math.min(sorted.length - 1, Math.max(0, rank - 1))];
    };

    const fmtMb = (mb) =>
      mb === null ? "—" : mb < 1024 ? mb + " MB" : (mb / 1024).toFixed(1) + " GB";

    const working = nums("PeakWorkingSetMb");
    const heap = nums("PeakManagedMemoryMb");

    setText("kpiMemoryP50", fmtMb(percentile(working, 50)));
    setText("kpiMemoryP90", fmtMb(percentile(working, 90)));
    setText("kpiMemoryMax", fmtMb(working.length ? working[working.length - 1] : null));
    setText("kpiHeapP50", fmtMb(percentile(heap, 50)));
    setText(
      "kpiMemorySample",
      working.length === 0
        ? "No sessions have reported memory yet"
        : "From " + working.length + " session" + (working.length === 1 ? "" : "s")
    );
  }

  function generateStartupCharts(startupData) {
    const setText = (id, text) => {
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    };

    // Percentile by nearest-rank on a sorted copy. Small samples are the norm
    // here, so an interpolating percentile would invent precision we lack.
    const percentile = (sorted, p) => {
      if (sorted.length === 0) return null;
      const rank = Math.ceil((p / 100) * sorted.length);
      return sorted[Math.min(sorted.length - 1, Math.max(0, rank - 1))];
    };

    const fmtMs = (ms) =>
      ms === null ? "—" : ms < 1000 ? ms + " ms" : (ms / 1000).toFixed(1) + " s";

    const nums = (rows, key) =>
      rows
        .map((r) => r[key])
        .filter((v) => typeof v === "number" && isFinite(v) && v >= 0)
        .sort((a, b) => a - b);

    const cold = startupData.filter((r) => r.ColdStart);
    const warm = startupData.filter((r) => !r.ColdStart);

    const coldPaint = nums(cold, "ToFirstPaintMs");
    const coldReady = nums(cold, "ToReadyMs");

    setText("kpiFirstPaintP50", fmtMs(percentile(coldPaint, 50)));
    setText("kpiFirstPaintP90", fmtMs(percentile(coldPaint, 90)));
    setText("kpiReadyP50", fmtMs(percentile(coldReady, 50)));
    setText("kpiReadyP90", fmtMs(percentile(coldReady, 90)));
    setText(
      "kpiStartupSample",
      coldReady.length === 0
        ? "No cold launches recorded"
        : "From " + coldReady.length + " cold launch" + (coldReady.length === 1 ? "" : "es")
    );

    if (startupData.length === 0) {
      const dist = document.getElementById("startupDistributionChart");
      const cw = document.getElementById("startupColdWarmChart");
      if (dist)
        dist.parentElement.innerHTML =
          '<div class="chart-no-data">No startup data available</div>';
      if (cw)
        cw.parentElement.innerHTML =
          '<div class="chart-no-data">No startup data available</div>';
      return;
    }

    // Distribution, not a time series: the question this answers is "is nine
    // seconds normal or an outlier", which an average over time cannot show.
    const buckets = [
      { label: "< 2s", min: 0, max: 2000 },
      { label: "2-4s", min: 2000, max: 4000 },
      { label: "4-6s", min: 4000, max: 6000 },
      { label: "6-8s", min: 6000, max: 8000 },
      { label: "8-10s", min: 8000, max: 10000 },
      { label: "10-15s", min: 10000, max: 15000 },
      { label: "15s+", min: 15000, max: Infinity },
    ];
    const bucketCounts = buckets.map(
      (b) => coldReady.filter((v) => v >= b.min && v < b.max).length
    );

    const distCanvas = document.getElementById("startupDistributionChart");
    if (distCanvas) {
      if (coldReady.length === 0) {
        distCanvas.parentElement.innerHTML =
          '<div class="chart-no-data">No cold launches recorded</div>';
      } else {
        new Chart(distCanvas, {
          type: "bar",
          data: {
            labels: buckets.map((b) => b.label),
            datasets: [
              {
                label: "Cold launches",
                data: bucketCounts,
                backgroundColor: "#8b5cf6",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (ctx) =>
                    ctx.parsed.y +
                    " of " +
                    coldReady.length +
                    " launches (" +
                    Math.round((ctx.parsed.y / coldReady.length) * 100) +
                    "%)",
                },
              },
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: { stepSize: 1 },
                title: { display: true, text: "Launches" },
              },
              x: { title: { display: true, text: "Time to ready" } },
            },
          },
        });
      }
    }

    const cwCanvas = document.getElementById("startupColdWarmChart");
    if (cwCanvas) {
      const toSec = (ms) => (ms === null ? 0 : Math.round((ms / 1000) * 10) / 10);
      new Chart(cwCanvas, {
        type: "bar",
        data: {
          labels: ["To loading screen", "To ready"],
          datasets: [
            {
              label: "Cold (" + cold.length + ")",
              data: [
                toSec(percentile(coldPaint, 50)),
                toSec(percentile(coldReady, 50)),
              ],
              backgroundColor: "#6366f1",
            },
            {
              label: "Warm (" + warm.length + ")",
              data: [
                toSec(percentile(nums(warm, "ToFirstPaintMs"), 50)),
                toSec(percentile(nums(warm, "ToReadyMs"), 50)),
              ],
              backgroundColor: "#a5b4fc",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ": " + ctx.parsed.y + " s" } },
          },
          scales: {
            y: {
              beginAtZero: true,
              title: { display: true, text: "Median seconds" },
            },
          },
        },
      });
    }
  }

  function generateSessionDurationChart(sessionData) {
    // Active time, not launch-to-quit: the wall clock counts a window left open
    // overnight as use. Only clean ends from recent builds carry it; older builds
    // and ends rebuilt after a force-quit send null, which means "not measured",
    // so those are left out rather than averaged in as zero.
    const sessionEndData = sessionData.filter(
      (s) =>
        s.action === "SessionEnd" &&
        s.activeSeconds !== null &&
        s.activeSeconds !== undefined
    );

    if (sessionEndData.length === 0) {
      document.getElementById("sessionDurationChart").parentElement.innerHTML =
        '<div class="chart-no-data">No active time data yet. Only recent app versions record it.</div>';
      return;
    }

    const dailyStats = {};
    sessionEndData.forEach((session) => {
      const date = session.timestamp.slice(0, 10);
      if (!dailyStats[date]) {
        dailyStats[date] = {
          totalDuration: 0,
          count: 0,
        };
      }
      dailyStats[date].totalDuration += parseFloat(session.activeSeconds) || 0;
      dailyStats[date].count++;
    });

    const dates = Object.keys(dailyStats).sort();
    // Convert average seconds to minutes (one decimal) for a more readable scale.
    const avgDurations = dates.map(
      (date) =>
        Math.round(
          dailyStats[date].totalDuration / dailyStats[date].count / 6
        ) / 10
    );

    new Chart(document.getElementById("sessionDurationChart"), {
      type: "bar",
      data: {
        labels: dates,
        datasets: [
          {
            label: "Average Active Time (minutes)",
            data: avgDurations,
            backgroundColor: "#3b82f6",
            borderColor: "#2563eb",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: (ctx) =>
                ctx.parsed.y + " min active (" + dailyStats[dates[ctx.dataIndex]].count + " sessions)",
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Active minutes",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        }
      },
    });
  }

  function generateExportTypesBreakdown(exportData) {
    if (exportData.length === 0) {
      document.getElementById("exportTypesGrid").parentElement.innerHTML =
        '<div class="chart-no-data">No export data available</div>';
      return;
    }

    const typeCounts = {};
    exportData.forEach((item) => {
      const type = item.ExportType || "Unknown";
      typeCounts[type] = (typeCounts[type] || 0) + 1;
    });

    const sortedTypes = Object.entries(typeCounts).sort(
      ([, a], [, b]) => b - a
    );
    const labels = sortedTypes.map(([type]) => type);
    const data = sortedTypes.map(([, count]) => count);
    const colors = labels.map((type) => typeColors[type] || "#9ca3af");

    new Chart(document.getElementById("exportTypesGrid"), {
      type: "pie",
      data: {
        labels: labels,
        datasets: [
          {
            data: data,
            backgroundColor: colors,
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((context.raw / total) * 100);
                return `${context.label}: ${context.raw} exports (${percentage}%)`;
              },
            },
          },
        }
      },
    });
  }

  function generateExportDurationByTypeChart(exportData) {
    if (exportData.length === 0) {
      document.getElementById(
        "exportDurationByTypeChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No export data available</div>';
      return;
    }

    const typeAverages = {};
    const typeCounts = {};

    exportData.forEach((item) => {
      const type = item.ExportType || "Unknown";
      const duration = item.DurationMS;
      const durationValue =
        typeof duration === "string"
          ? parseFloat(duration.replace(/[^\d.]/g, ""))
          : parseInt(duration) || 0;

      if (!typeAverages[type]) {
        typeAverages[type] = 0;
        typeCounts[type] = 0;
      }
      typeAverages[type] += durationValue;
      typeCounts[type]++;
    });

    const labels = [];
    const averages = [];
    const colors = [];

    for (const type in typeAverages) {
      if (typeCounts[type] > 0) {
        labels.push(type);
        averages.push(Math.round(typeAverages[type] / typeCounts[type]));
        colors.push(typeColors[type] || "#9ca3af");
      }
    }

    new Chart(document.getElementById("exportDurationByTypeChart"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Average Duration (ms)",
            data: averages,
            backgroundColor: colors,
            borderColor: colors.map((c) => c.replace("0.6", "1")),
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Duration (ms)",
            },
          },
        }
      },
    });
  }

  function generateExportFileSizeByTypeChart(exportData) {
    const filteredData = exportData
      .filter((d) => d.FileSize && d.FileSize !== "null" && d.FileSize !== null)
      .map((d) => ({
        type: d.ExportType || "Unknown",
        size: parseInt(d.FileSize) || 0,
      }))
      .filter((item) => item.size > 0);

    if (filteredData.length === 0) {
      document.getElementById(
        "exportFileSizeByTypeChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No file size data available</div>';
      return;
    }

    const typeAverages = {};
    const typeCounts = {};

    filteredData.forEach((item) => {
      if (!typeAverages[item.type]) {
        typeAverages[item.type] = 0;
        typeCounts[item.type] = 0;
      }
      typeAverages[item.type] += item.size;
      typeCounts[item.type]++;
    });

    const labels = [];
    const averages = [];
    const colors = [];

    for (const type in typeAverages) {
      if (typeCounts[type] > 0) {
        labels.push(type);
        averages.push(Math.round(typeAverages[type] / typeCounts[type]));
        colors.push(typeColors[type] || "#9ca3af");
      }
    }

    new Chart(document.getElementById("exportFileSizeByTypeChart"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Average File Size (bytes)",
            data: averages,
            backgroundColor: colors,
            borderColor: colors.map((c) => c.replace("0.6", "1")),
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "File Size (bytes)",
            },
            ticks: {
              callback: function (value) {
                if (value >= 1048576)
                  return (value / 1048576).toFixed(1) + "MB";
                if (value >= 1024) return (value / 1024).toFixed(1) + "KB";
                return value + "B";
              },
            },
          },
        }
      },
    });
  }

  // API Usage Charts
  function generateGeminiChart(geminiData) {
    if (geminiData.length === 0) {
      document.getElementById("geminiChart").parentElement.innerHTML =
        '<div class="chart-no-data">No Gemini data available</div>';
      return;
    }

    const successful = geminiData.filter((d) => d.Success === true).length;
    const failed = geminiData.filter((d) => d.Success === false).length;

    new Chart(document.getElementById("geminiChart"), {
      type: "doughnut",
      data: {
        labels: ["Successful", "Failed"],
        datasets: [
          {
            data: [successful, failed],
            backgroundColor: ["#10b981", "#ef4444"],
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((context.raw / total) * 100);
                return `${context.label}: ${context.raw} calls (${percentage}%)`;
              },
            },
          },
        },
      },
    });
  }

  function generateGeminiResponseTimeChart(geminiData) {
    const dataWithDuration = geminiData.filter(
      (d) => d.DurationMS && d.DurationMS > 0
    );

    if (dataWithDuration.length === 0) {
      document.getElementById("geminiResponseTimeChart").parentElement.innerHTML =
        '<div class="chart-no-data">No Gemini response time data available</div>';
      return;
    }

    const recentData = dataWithDuration.slice(-50);
    const labels = recentData.map((d) =>
      new Date(d.timestamp).toLocaleDateString()
    );
    const durations = recentData.map((d) => parseInt(d.DurationMS) || 0);

    new Chart(document.getElementById("geminiResponseTimeChart"), {
      type: "line",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Response Time (ms)",
            data: durations,
            borderColor: "#0ea5e9",
            backgroundColor: "rgba(14, 165, 233, 0.1)",
            fill: true,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Duration (ms)",
            },
          },
        },
      },
    });
  }

  // Two series, deliberately. One bulk request should price every date a currency change needs,
  // and the per-date line should stay near zero. Per-date climbing alongside bulk means the bulk
  // response is arriving and not being used, which costs a distant user a round trip per date.
  function generateExchangeRatesChart(perDateData, batchData) {
    const combined = [
      ...perDateData.map((d) => ({ point: d, series: "perDate" })),
      ...batchData.map((d) => ({ point: d, series: "batch" })),
    ].sort(
      (a, b) => new Date(a.point.timestamp) - new Date(b.point.timestamp)
    );

    if (combined.length === 0) {
      document.getElementById("exchangeRatesChart").parentElement.innerHTML =
        '<div class="chart-no-data">No exchange rates data available</div>';
      return;
    }

    const recentData = combined.slice(-40);
    const labels = recentData.map((d) =>
      new Date(d.point.timestamp).toLocaleDateString()
    );
    const seriesFor = (name) =>
      recentData.map((d) =>
        d.series === name ? parseInt(d.point.DurationMS) || 0 : null
      );

    new Chart(document.getElementById("exchangeRatesChart"), {
      type: "line",
      data: {
        labels: labels,
        datasets: [
          {
            label: `Bulk request (${batchData.length})`,
            data: seriesFor("batch"),
            borderColor: "#3b82f6",
            backgroundColor: "rgba(59, 130, 246, 0.1)",
            fill: false,
            tension: 0.4,
            spanGaps: true,
          },
          {
            label: `Per-date repair (${perDateData.length})`,
            data: seriesFor("perDate"),
            borderColor: "#f59e0b",
            backgroundColor: "rgba(245, 158, 11, 0.1)",
            fill: false,
            tension: 0.4,
            spanGaps: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Duration (ms)",
            },
          },
        }
      },
    });
  }

  // =====================
  // Active Users Tab
  // =====================

  // Total / DAU / WAU / MAU are counted server-side across every event, not just
  // the ones inside the selected date range: "active today" has to mean today
  // whatever range is being charted. See the fixedKpis note in index.php.
  function setFixedKpis(rawData) {
    const kpis = rawData.fixedKpis;
    if (!kpis) return;
    const cards = {
      kpiTotalUsers: kpis.totalUsers,
      kpiDAU: kpis.dau,
      kpiMAU: kpis.mau,
      kpiNewUsers: kpis.newUsers,
    };
    for (const id of Object.keys(cards)) {
      const el = document.getElementById(id);
      if (el) el.textContent = cards[id];
    }
  }

  // Every day of the selected range as local YYYY-MM-DD strings, so a day with no
  // activity still gets its zero. The server reports "All Time" as the day of the
  // oldest event. The end is capped at today so a range ending later has no empty tail.
  function rangeDays(rawData, toLocalDateStr) {
    const parse = (s) => {
      const d = s ? new Date(s + "T00:00:00") : null;
      return d && !isNaN(d.getTime()) ? d : null;
    };
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let end = parse(rawData.range && rawData.range.end) || today;
    if (end > today) end = today;
    let start = parse(rawData.range && rawData.range.start);
    if (!start || start > end) {
      start = new Date(end);
      start.setDate(start.getDate() - 29);
    }
    const days = [];
    // setDate rather than adding 24h, which skips or repeats a day across DST.
    for (const d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      days.push(toLocalDateStr(d));
    }
    return days;
  }

  function generateActiveUsersTab(rawData) {
    // Collect all events from all categories
    const allEvents = [];
    for (const category of Object.keys(rawData.dataPoints)) {
      const events = rawData.dataPoints[category] || [];
      events.forEach((e) => allEvents.push({ ...e, _category: category }));
    }

    const eventsWithUser = allEvents.filter((e) => e.hashedIP);

    // The KPI cards are fixed-window and range-independent, so they're filled from
    // the server's own count before any early return below: an empty date range
    // must not blank out numbers that don't depend on the range.
    setFixedKpis(rawData);

    if (eventsWithUser.length === 0) {
      document.getElementById("dauChart").parentElement.innerHTML =
        '<div class="chart-no-data">No active user data available</div>';
      document.getElementById("platformBreakdownChart").parentElement.innerHTML =
        '<div class="chart-no-data">No data</div>';
      document.getElementById("peakHoursChart").parentElement.innerHTML =
        '<div class="chart-no-data">No data</div>';
      return;
    }

    // Local date string YYYY-MM-DD (not UTC). This dashboard is read in the
    // admin's own timezone, so "today" and the day buckets must be local.
    // toISOString() bucketed by UTC and shifted every axis label back a day for
    // timezones west of UTC (e.g. today, Jun 29, rendered as Jun 28).
    const toLocalDateStr = (ts) => {
      const d = new Date(ts);
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    };
    // Format a YYYY-MM-DD bucket as an axis label, parsing it as a local date
    // (T00:00:00) so it isn't shifted back a day.
    const fmtDayLabel = (d) =>
      new Date(d + "T00:00:00").toLocaleDateString(undefined, { month: "short", day: "numeric" });

    // Helper: date string YYYY-MM-DD from timestamp (local)
    function toDateStr(ts) {
      return toLocalDateStr(ts);
    }

    // Build user map: hashedIP -> { firstSeen, lastSeen, platform, country, appVersion, sessionCount, days }
    const userMap = {};
    eventsWithUser.forEach((e) => {
      const ip = e.hashedIP;
      const ts = new Date(e.timestamp).getTime();
      const dateStr = toDateStr(e.timestamp);
      if (!userMap[ip]) {
        userMap[ip] = {
          firstSeen: ts,
          lastSeen: ts,
          platform: e.platform || "Unknown",
          country: e.country || "Unknown",
          appVersion: e.appVersion || "Unknown",
          sessionCount: 0,
          days: new Set(),
        };
      }
      const u = userMap[ip];
      if (ts < u.firstSeen) u.firstSeen = ts;
      if (ts > u.lastSeen) {
        u.lastSeen = ts;
        u.platform = e.platform || u.platform;
        u.country = e.country || u.country;
        u.appVersion = e.appVersion || u.appVersion;
      }
      u.days.add(dateStr);
      if (e._category === "Session" && e.action === "SessionStart") {
        u.sessionCount++;
      }
    });

    const allUsers = Object.keys(userMap);

    // Users active per day, drawn below as new and returning stacked, so each bar's
    // height is that day's active users.
    const dailyUsers = {};
    eventsWithUser.forEach((e) => {
      const dateStr = toDateStr(e.timestamp);
      if (!dailyUsers[dateStr]) dailyUsers[dateStr] = new Set();
      dailyUsers[dateStr].add(e.hashedIP);
    });

    // The server already drops events outside the selected range, so these charts
    // cover exactly that range with no trailing-days cutoff of their own.
    const rangeDates = rangeDays(rawData, toLocalDateStr);
    // --- Platform Breakdown doughnut (selected range) ---
    const platformCounts = {};
    allUsers.forEach((ip) => {
      const p = userMap[ip].platform;
      platformCounts[p] = (platformCounts[p] || 0) + 1;
    });

    const platformLabels = Object.keys(platformCounts);
    const platformData = platformLabels.map((l) => platformCounts[l]);
    const platformColors = ["#3b82f6", "#10b981", "#f59e0b", "#ef4444", "#1e40af", "#ec4899"];

    new Chart(document.getElementById("platformBreakdownChart"), {
      type: "doughnut",
      data: {
        labels: platformLabels,
        datasets: [
          {
            data: platformData,
            backgroundColor: platformColors.slice(0, platformLabels.length),
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: "bottom" } },
      },
    });

    // --- Daily Active Users, split into new and returning (selected range) ---
    // A user is "new" on the day of their first event ever, "returning" on any later
    // day. The server's all-time first sighting is used where it has one: the events
    // here start at the range, so the earliest of them would make everyone active on
    // its first day look new.
    const serverFirstSeen = rawData.userFirstSeen || {};
    const globalFirstSeen = {};
    allUsers.forEach((ip) => {
      const first = serverFirstSeen[ip] ? serverFirstSeen[ip] * 1000 : userMap[ip].firstSeen;
      globalFirstSeen[ip] = toDateStr(new Date(first));
    });

    const newPerDay = {};
    const returningPerDay = {};
    rangeDates.forEach((d) => {
      newPerDay[d] = 0;
      returningPerDay[d] = 0;
    });

    rangeDates.forEach((d) => {
      if (!dailyUsers[d]) return;
      dailyUsers[d].forEach((ip) => {
        if (globalFirstSeen[ip] === d) {
          newPerDay[d]++;
        } else {
          returningPerDay[d]++;
        }
      });
    });

    new Chart(document.getElementById("dauChart"), {
      type: "bar",
      data: {
        labels: rangeDates.map((d) => fmtDayLabel(d)),
        datasets: [
          {
            label: "New",
            data: rangeDates.map((d) => newPerDay[d]),
            backgroundColor: "#10b981",
          },
          {
            label: "Returning",
            data: rangeDates.map((d) => returningPerDay[d]),
            backgroundColor: "#3b82f6",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { stacked: true },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: { stepSize: 1 },
            title: { display: true, text: "Active Users" },
          },
        },
        plugins: {
          legend: { position: "bottom" },
          tooltip: {
            mode: "index",
            callbacks: {
              footer: (items) => "Total: " + items.reduce((sum, i) => sum + i.raw, 0),
            },
          },
        },
      },
    });

    // --- Peak Usage Hours bar chart (selected range) ---
    const hourlyUsers = {};
    for (let h = 0; h < 24; h++) hourlyUsers[h] = new Set();
    eventsWithUser.forEach((e) => {
      hourlyUsers[new Date(e.timestamp).getHours()].add(e.hashedIP);
    });

    const hourLabels = Array.from({ length: 24 }, (_, i) =>
      i.toString().padStart(2, "0") + ":00"
    );
    const hourData = Array.from({ length: 24 }, (_, i) => hourlyUsers[i].size);

    new Chart(document.getElementById("peakHoursChart"), {
      type: "bar",
      data: {
        labels: hourLabels,
        datasets: [
          {
            label: "Unique Users",
            data: hourData,
            backgroundColor: "#0ea5e9",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            title: { display: true, text: "Unique Users" },
          },
        },
        plugins: { legend: { display: false } },
      },
    });

    // --- User Details Table ---
    const tbody = document.querySelector("#activeUsersTable tbody");
    const sortedUsers = allUsers
      .map((ip) => ({ ip, ...userMap[ip] }))
      .sort((a, b) => b.lastSeen - a.lastSeen);

    sortedUsers.forEach((u) => {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td style=\"font-family: monospace; font-weight: 600;\">" + u.ip.substring(0, 8) + "</td>" +
        "<td>" + new Date(u.firstSeen).toLocaleDateString() + "</td>" +
        "<td>" + new Date(u.lastSeen).toLocaleDateString() + "</td>" +
        "<td>" + u.sessionCount + "</td>" +
        "<td>" + u.platform + "</td>" +
        "<td>" + u.country + "</td>" +
        "<td>" + u.appVersion + "</td>";
      tbody.appendChild(tr);
    });
  }

  // =====================
  // Pages
  // =====================
  function generatePageViewsTable(pageViewData) {
    const tbody = document.querySelector("#pageViewsTable tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    if (pageViewData.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--admin-text);">' +
        "No page data yet. Page timing ships in 2.0.14." +
        "</td></tr>";
      return;
    }

    // Median rather than mean: one person who left a screen open for an hour would otherwise
    // decide the number for that page.
    const median = (nums) => {
      if (nums.length === 0) return 0;
      const sorted = nums.slice().sort((a, b) => a - b);
      const mid = Math.floor(sorted.length / 2);
      return sorted.length % 2 ? sorted[mid] : Math.round((sorted[mid - 1] + sorted[mid]) / 2);
    };

    const fmt = (secs) => {
      if (secs < 60) return secs + "s";
      const m = Math.floor(secs / 60);
      return m < 60 ? m + "m " + (secs % 60) + "s" : Math.floor(m / 60) + "h " + (m % 60) + "m";
    };

    const byPage = {};
    pageViewData.forEach((v) => {
      const name = v.PageName || "Unknown";
      if (!byPage[name]) byPage[name] = { active: [], open: [] };
      byPage[name].active.push(Number(v.ActiveSeconds) || 0);
      byPage[name].open.push(Number(v.DurationSeconds) || 0);
    });

    const rows = Object.entries(byPage)
      .map(([name, d]) => ({
        name,
        visits: d.active.length,
        medianActive: median(d.active),
        medianOpen: median(d.open),
        totalActive: d.active.reduce((a, b) => a + b, 0),
      }))
      // Total active time first: that is where the app is actually being used, which is a
      // different question from which page gets opened most.
      .sort((a, b) => b.totalActive - a.totalActive);

    rows.forEach((r) => {
      const tr = document.createElement("tr");
      // Flag the walked-away pattern rather than making every reader do the subtraction.
      const abandoned = r.medianOpen > r.medianActive * 3 && r.medianOpen - r.medianActive > 30;
      tr.innerHTML =
        '<td style="font-weight:600;">' + escapeHtml(r.name) + "</td>" +
        "<td>" + r.visits.toLocaleString() + "</td>" +
        "<td>" + fmt(r.medianActive) + "</td>" +
        "<td" + (abandoned ? ' style="color:var(--admin-warning, #f59e0b); font-weight:600;"' : "") +
          ">" + fmt(r.medianOpen) + "</td>" +
        "<td>" + fmt(r.totalActive) + "</td>";
      tbody.appendChild(tr);
    });
  }

  // =====================
  // Error Category Timeline Chart
  // =====================
  function generateErrorCategoryTimelineChart(errorData) {
    if (errorData.length === 0) {
      document.getElementById(
        "errorCategoryTimelineChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No error timeline data available</div>';
      return;
    }

    const categoryByDate = {};
    const categories = new Set();

    errorData.forEach((error) => {
      const date = new Date(error.timestamp).toLocaleDateString();
      const category = error.ErrorCategory || "Unknown";
      categories.add(category);

      if (!categoryByDate[date]) {
        categoryByDate[date] = {};
      }
      categoryByDate[date][category] =
        (categoryByDate[date][category] || 0) + 1;
    });

    const dates = Object.keys(categoryByDate).sort();

    const categoryColors = [
      "#ef4444",
      "#f59e0b",
      "#3b82f6",
      "#10b981",
      "#1e40af",
      "#ec4899",
      "#14b8a6",
      "#f97316",
      "#60a5fa",
      "#06b6d4",
      "#84cc16",
      "#93c5fd",
    ];

    // Rank categories by their total count over the shown window, so the most
    // active categories are plotted, including ones that only appear recently
    // (e.g. Network). Previously this kept the first six categories the data
    // happened to mention, which silently dropped any later ones.
    const categoryTotals = {};
    Array.from(categories).forEach((cat) => {
      categoryTotals[cat] = dates.reduce(
        (sum, date) => sum + (categoryByDate[date]?.[cat] || 0),
        0
      );
    });
    const categoryArray = Array.from(categories)
      .sort((a, b) => categoryTotals[b] - categoryTotals[a])
      .slice(0, categoryColors.length);

    const datasets = categoryArray.map((category, index) => ({
      label: category,
      data: dates.map((date) => categoryByDate[date]?.[category] || 0),
      borderColor: categoryColors[index],
      backgroundColor: categoryColors[index] + "20",
      fill: true,
      tension: 0.4,
    }));

    new Chart(document.getElementById("errorCategoryTimelineChart"), {
      type: "line",
      data: {
        labels: dates,
        datasets: datasets,
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            stacked: true,
            title: {
              display: true,
              text: "Error Count",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        },
      },
    });
  }

  // =====================
  // Feature Usage Charts
  // =====================
  // Convert camelCase/PascalCase feature names to readable labels
  function formatFeatureLabel(name) {
    const labelMap = {
      DataImported: "AI Spreadsheet Import",
      ReceiptScanned: "Receipt Scanned",
      GoogleSheetsExport: "Google Sheets Export",
      BackupCreated: "Backup Created",
      BackupRestored: "Backup Restored",
      CurrencyConverted: "Currency Converted",
      SupplierMatched: "Supplier Matched",
    };
    if (labelMap[name]) return labelMap[name];
    // Fallback: insert spaces before uppercase letters
    return name.replace(/([a-z])([A-Z])/g, "$1 $2").replace(/([A-Z]+)([A-Z][a-z])/g, "$1 $2");
  }

  function generateFeatureUsageChart(featureUsageData) {
    if (featureUsageData.length === 0) {
      document.getElementById("featureUsageChart").parentElement.innerHTML =
        '<div class="chart-no-data">No feature usage data available</div>';
      return;
    }

    const featureCounts = {};
    featureUsageData.forEach((item) => {
      const feature = item.FeatureName || "Unknown";
      featureCounts[feature] = (featureCounts[feature] || 0) + 1;
    });

    const sortedFeatures = Object.entries(featureCounts)
      .sort(([, a], [, b]) => b - a)
      .slice(0, 10);

    const colors = [
      "#3b82f6",
      "#10b981",
      "#f59e0b",
      "#ef4444",
      "#1e40af",
      "#06b6d4",
      "#84cc16",
      "#f97316",
      "#ec4899",
      "#60a5fa",
    ];

    new Chart(document.getElementById("featureUsageChart"), {
      type: "bar",
      data: {
        labels: sortedFeatures.map(([feature]) => formatFeatureLabel(feature)),
        datasets: [
          {
            label: "Usage Count",
            data: sortedFeatures.map(([, count]) => count),
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: "y",
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Usage Count",
            },
          },
        },
      },
    });
  }

  function generateFeatureTimelineChart(featureUsageData) {
    if (featureUsageData.length === 0) {
      document.getElementById("featureTimelineChart").parentElement.innerHTML =
        '<div class="chart-no-data">No feature timeline data available</div>';
      return;
    }

    const dailyCounts = {};
    featureUsageData.forEach((item) => {
      const date = new Date(item.timestamp).toLocaleDateString();
      dailyCounts[date] = (dailyCounts[date] || 0) + 1;
    });

    const dates = Object.keys(dailyCounts).sort();
    const counts = dates.map((date) => dailyCounts[date]);

    new Chart(document.getElementById("featureTimelineChart"), {
      type: "line",
      data: {
        labels: dates,
        datasets: [
          {
            label: "Feature Usage",
            data: counts,
            borderColor: "#3b82f6",
            backgroundColor: "rgba(59, 130, 246, 0.1)",
            fill: true,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Usage Count",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        },
      },
    });
  }


  // =====================
  // Receipt Scanning Charts
  // =====================
  function generateReceiptScanOverviewChart(receiptScanningData) {
    if (receiptScanningData.length === 0) {
      document.getElementById(
        "receiptScanOverviewChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No receipt scanning data available</div>';
      return;
    }

    const successful = receiptScanningData.filter(
      (r) => r.Success === true
    ).length;
    const failed = receiptScanningData.filter(
      (r) => r.Success === false
    ).length;

    new Chart(document.getElementById("receiptScanOverviewChart"), {
      type: "doughnut",
      data: {
        labels: ["Successful", "Failed"],
        datasets: [
          {
            data: [successful, failed],
            backgroundColor: ["#10b981", "#ef4444"],
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((context.raw / total) * 100);
                return `${context.label}: ${context.raw} scans (${percentage}%)`;
              },
            },
          },
        },
      },
    });
  }

  function generateReceiptScanSuccessChart(receiptScanningData) {
    if (receiptScanningData.length === 0) {
      document.getElementById(
        "receiptScanSuccessChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No receipt scanning data available</div>';
      return;
    }

    const dailyStats = {};
    receiptScanningData.forEach((item) => {
      const date = new Date(item.timestamp).toLocaleDateString();
      if (!dailyStats[date]) {
        dailyStats[date] = { success: 0, total: 0 };
      }
      dailyStats[date].total++;
      if (item.Success === true) {
        dailyStats[date].success++;
      }
    });

    const dates = Object.keys(dailyStats).sort();
    const successRates = dates.map((date) =>
      dailyStats[date].total > 0
        ? Math.round((dailyStats[date].success / dailyStats[date].total) * 100)
        : 0
    );

    new Chart(document.getElementById("receiptScanSuccessChart"), {
      type: "line",
      data: {
        labels: dates,
        datasets: [
          {
            label: "Success Rate (%)",
            data: successRates,
            borderColor: "#10b981",
            backgroundColor: "rgba(16, 185, 129, 0.1)",
            fill: true,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            title: {
              display: true,
              text: "Success Rate (%)",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        },
      },
    });
  }

  function generateReceiptScanDurationChart(receiptScanningData) {
    const dataWithDuration = receiptScanningData.filter(
      (r) => r.DurationMS && r.DurationMS > 0
    );

    if (dataWithDuration.length === 0) {
      document.getElementById(
        "receiptScanDurationChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No duration data available</div>';
      return;
    }

    const recentData = dataWithDuration.slice(-50);
    const durations = recentData.map((r) => parseInt(r.DurationMS) || 0);
    const avgDuration = Math.round(
      durations.reduce((a, b) => a + b, 0) / durations.length
    );

    // Create histogram buckets
    const buckets = {
      "0-1s": 0,
      "1-2s": 0,
      "2-3s": 0,
      "3-5s": 0,
      "5-10s": 0,
      "10s+": 0,
    };

    dataWithDuration.forEach((item) => {
      const seconds = (item.DurationMS || 0) / 1000;
      if (seconds < 1) buckets["0-1s"]++;
      else if (seconds < 2) buckets["1-2s"]++;
      else if (seconds < 3) buckets["2-3s"]++;
      else if (seconds < 5) buckets["3-5s"]++;
      else if (seconds < 10) buckets["5-10s"]++;
      else buckets["10s+"]++;
    });

    new Chart(document.getElementById("receiptScanDurationChart"), {
      type: "bar",
      data: {
        labels: Object.keys(buckets),
        datasets: [
          {
            label: "Scans",
            data: Object.values(buckets),
            backgroundColor: "#06b6d4",
            borderColor: "#0891b2",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          title: {
            display: true,
            text: `Average: ${avgDuration}ms`,
            position: "bottom",
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Number of Scans",
            },
          },
        },
      },
    });
  }

  function generateReceiptScanTrendChart(receiptScanningData) {
    if (receiptScanningData.length === 0) {
      document.getElementById("receiptScanTrendChart").parentElement.innerHTML =
        '<div class="chart-no-data">No trend data available</div>';
      return;
    }

    const dailyCounts = {};
    receiptScanningData.forEach((item) => {
      const date = new Date(item.timestamp).toLocaleDateString();
      dailyCounts[date] = (dailyCounts[date] || 0) + 1;
    });

    const dates = Object.keys(dailyCounts).sort();
    const counts = dates.map((date) => dailyCounts[date]);

    new Chart(document.getElementById("receiptScanTrendChart"), {
      type: "bar",
      data: {
        labels: dates,
        datasets: [
          {
            label: "Scans",
            data: counts,
            backgroundColor: "#f59e0b",
            borderColor: "#d97706",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Scans per Day",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        },
      },
    });
  }

  // =====================
  // AI Spreadsheet Importer Charts
  // =====================

  // Helper to extract DataImported events from featureUsageData
  function getDataImportedEvents(featureUsageData) {
    return featureUsageData.filter((f) => f.FeatureName === "DataImported");
  }

  // The app tags AI imports "ai-xlsx" / "ai-csv", plus "-tier2" / "-mixed"
  // variants for multi-tier imports (App.axaml.cs). Bucket every variant by its
  // base file type so the charts count all of them, not just plain tier-1 imports.
  function aiImportType(context) {
    if (typeof context !== "string") return null;
    if (context.startsWith("ai-xlsx")) return "ai-xlsx";
    if (context.startsWith("ai-csv")) return "ai-csv";
    return null;
  }

  function generateAIImportStats(featureUsageData) {
    const statsGrid = document.getElementById("aiImportStatsGrid");
    if (!statsGrid) return;

    const importEvents = getDataImportedEvents(featureUsageData);
    const aiXlsx = importEvents.filter((e) => aiImportType(e.Context) === "ai-xlsx");
    const aiCsv = importEvents.filter((e) => aiImportType(e.Context) === "ai-csv");
    const totalAI = aiXlsx.length + aiCsv.length;

    const stats = [
      {
        title: "Total AI Imports",
        value: totalAI.toLocaleString(),
        subtext: `${aiXlsx.length} Excel, ${aiCsv.length} CSV`,
      },
      {
        title: "AI Excel Imports",
        value: aiXlsx.length.toLocaleString(),
        subtext: "ai-xlsx mapped imports",
      },
      {
        title: "AI CSV Imports",
        value: aiCsv.length.toLocaleString(),
        subtext: "ai-csv mapped imports",
      },
    ];

    // Add avg duration stat if any AI imports have duration data
    const aiWithDuration = [...aiXlsx, ...aiCsv].filter(
      (e) => e.DurationMs != null && e.DurationMs > 0
    );
    if (aiWithDuration.length > 0) {
      const avgMs =
        aiWithDuration.reduce((sum, e) => sum + e.DurationMs, 0) /
        aiWithDuration.length;
      stats.push({
        title: "Avg Import Duration",
        value: avgMs >= 1000 ? (avgMs / 1000).toFixed(1) + "s" : Math.round(avgMs) + "ms",
        subtext: `Based on ${aiWithDuration.length} imports with timing data`,
      });
    }

    statsGrid.innerHTML = stats
      .map(
        (stat) => `
      <div class="stat-card">
        <h3>${stat.title}</h3>
        <div class="value">${stat.value}</div>
        <p class="subtext">${stat.subtext}</p>
      </div>
    `
      )
      .join("");
  }

  function generateAIImportOverviewChart(featureUsageData) {
    const importEvents = getDataImportedEvents(featureUsageData);

    if (importEvents.length === 0) {
      document.getElementById("aiImportOverviewChart").parentElement.innerHTML =
        '<div class="chart-no-data">No AI import data available</div>';
      return;
    }

    const aiXlsx = importEvents.filter((e) => aiImportType(e.Context) === "ai-xlsx").length;
    const aiCsv = importEvents.filter((e) => aiImportType(e.Context) === "ai-csv").length;

    if (aiXlsx === 0 && aiCsv === 0) {
      document.getElementById("aiImportOverviewChart").parentElement.innerHTML =
        '<div class="chart-no-data">No AI import data available</div>';
      return;
    }

    new Chart(document.getElementById("aiImportOverviewChart"), {
      type: "doughnut",
      data: {
        labels: ["AI Excel (.xlsx)", "AI CSV (.csv)"],
        datasets: [
          {
            data: [aiXlsx, aiCsv],
            backgroundColor: ["#3b82f6", "#0ea5e9"],
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((context.raw / total) * 100);
                return `${context.label}: ${context.raw} imports (${percentage}%)`;
              },
            },
          },
        },
      },
    });
  }

  function generateAIImportTrendChart(featureUsageData) {
    const importEvents = getDataImportedEvents(featureUsageData);
    const aiEvents = importEvents.filter((e) => aiImportType(e.Context) !== null);

    if (aiEvents.length === 0) {
      document.getElementById("aiImportTrendChart").parentElement.innerHTML =
        '<div class="chart-no-data">No AI import trend data available</div>';
      return;
    }

    const dailyCounts = {};
    aiEvents.forEach((item) => {
      const date = new Date(item.timestamp).toLocaleDateString();
      dailyCounts[date] = (dailyCounts[date] || 0) + 1;
    });

    const dates = Object.keys(dailyCounts).sort();
    const counts = dates.map((date) => dailyCounts[date]);

    new Chart(document.getElementById("aiImportTrendChart"), {
      type: "bar",
      data: {
        labels: dates,
        datasets: [
          {
            label: "AI Imports",
            data: counts,
            backgroundColor: "#10b981",
            borderColor: "#059669",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Imports per Day",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        },
      },
    });
  }

  function generateAIImportDurationChart(featureUsageData) {
    const importEvents = getDataImportedEvents(featureUsageData).filter(
      (e) =>
        aiImportType(e.Context) !== null &&
        e.DurationMs != null &&
        e.DurationMs > 0
    );

    if (importEvents.length === 0) {
      document.getElementById("aiImportDurationChart").parentElement.innerHTML =
        '<div class="chart-no-data">No AI import duration data available</div>';
      return;
    }

    // Group by day and compute average duration
    const dailyDurations = {};
    importEvents.forEach((item) => {
      const date = new Date(item.timestamp).toLocaleDateString();
      if (!dailyDurations[date]) {
        dailyDurations[date] = { total: 0, count: 0 };
      }
      dailyDurations[date].total += item.DurationMs;
      dailyDurations[date].count++;
    });

    const dates = Object.keys(dailyDurations).sort();
    const avgDurations = dates.map((d) =>
      Math.round(dailyDurations[d].total / dailyDurations[d].count)
    );

    new Chart(document.getElementById("aiImportDurationChart"), {
      type: "line",
      data: {
        labels: dates,
        datasets: [
          {
            label: "Avg Duration (ms)",
            data: avgDurations,
            borderColor: "#0ea5e9",
            backgroundColor: "rgba(14, 165, 233, 0.1)",
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#0ea5e9",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const ms = context.raw;
                const label =
                  ms >= 1000
                    ? (ms / 1000).toFixed(1) + "s"
                    : ms + "ms";
                const count =
                  dailyDurations[dates[context.dataIndex]].count;
                return `Avg: ${label} (${count} imports)`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Duration (ms)",
            },
          },
          x: {
            ticks: {
              maxRotation: 45,
            },
          },
        },
      },
    });
  }

  function generateAIImportDurationByTypeChart(featureUsageData) {
    const importEvents = getDataImportedEvents(featureUsageData).filter(
      (e) =>
        aiImportType(e.Context) !== null &&
        e.DurationMs != null &&
        e.DurationMs > 0
    );

    if (importEvents.length === 0) {
      document.getElementById(
        "aiImportDurationByTypeChart"
      ).parentElement.innerHTML =
        '<div class="chart-no-data">No AI import duration data available</div>';
      return;
    }

    const byType = { "ai-xlsx": [], "ai-csv": [] };
    importEvents.forEach((e) => {
      const t = aiImportType(e.Context);
      if (t && byType[t]) {
        byType[t].push(e.DurationMs);
      }
    });

    const labels = [];
    const avgData = [];
    const minData = [];
    const maxData = [];
    const colors = { "ai-xlsx": "#3b82f6", "ai-csv": "#0ea5e9" };
    const bgColors = [];

    for (const [type, durations] of Object.entries(byType)) {
      if (durations.length === 0) continue;
      labels.push(type === "ai-xlsx" ? "AI Excel (.xlsx)" : "AI CSV (.csv)");
      const avg = Math.round(
        durations.reduce((a, b) => a + b, 0) / durations.length
      );
      avgData.push(avg);
      minData.push(Math.min(...durations));
      maxData.push(Math.max(...durations));
      bgColors.push(colors[type]);
    }

    new Chart(document.getElementById("aiImportDurationByTypeChart"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Average",
            data: avgData,
            backgroundColor: bgColors,
            borderColor: bgColors,
            borderWidth: 1,
          },
          {
            label: "Min",
            data: minData,
            backgroundColor: bgColors.map((c) => c + "66"),
            borderColor: bgColors,
            borderWidth: 1,
          },
          {
            label: "Max",
            data: maxData,
            backgroundColor: bgColors.map((c) => c + "33"),
            borderColor: bgColors,
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const ms = context.raw;
                const label =
                  ms >= 1000
                    ? (ms / 1000).toFixed(1) + "s"
                    : ms + "ms";
                return `${context.dataset.label}: ${label}`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Duration (ms)",
            },
          },
        },
      },
    });
  }

  function generateAIImportTypeTimeChart(featureUsageData) {
    const importEvents = getDataImportedEvents(featureUsageData);

    if (importEvents.length === 0) {
      document.getElementById("aiImportTypeTimeChart").parentElement.innerHTML =
        '<div class="chart-no-data">No import data available</div>';
      return;
    }

    const dailyByType = {};
    importEvents.forEach((item) => {
      const date = new Date(item.timestamp).toLocaleDateString();
      if (!dailyByType[date]) {
        dailyByType[date] = { "ai-xlsx": 0, "ai-csv": 0 };
      }
      const detail = aiImportType(item.Context);
      if (detail && dailyByType[date][detail] !== undefined) {
        dailyByType[date][detail]++;
      }
    });

    const dates = Object.keys(dailyByType).sort();

    new Chart(document.getElementById("aiImportTypeTimeChart"), {
      type: "bar",
      data: {
        labels: dates,
        datasets: [
          {
            label: "AI Excel",
            data: dates.map((d) => dailyByType[d]["ai-xlsx"]),
            backgroundColor: "#3b82f6",
            borderColor: "#2563eb",
            borderWidth: 1,
          },
          {
            label: "AI CSV",
            data: dates.map((d) => dailyByType[d]["ai-csv"]),
            backgroundColor: "#0ea5e9",
            borderColor: "#0284c7",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            stacked: true,
            title: {
              display: true,
              text: "Imports per Day",
            },
          },
          x: {
            stacked: true,
            ticks: {
              maxRotation: 45,
            },
          },
        },
      },
    });
  }
});