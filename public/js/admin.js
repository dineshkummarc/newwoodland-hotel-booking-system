document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* =========================================================
       DELETE CONFIRMATION
    ========================================================= */
    document.querySelectorAll('[data-confirm-delete]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var message = form.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    });

    /* =========================================================
       IMAGE PREVIEW (upload-box)
    ========================================================= */
    document.querySelectorAll('[data-image-input]').forEach(function (input) {
        var previewId = input.getAttribute('data-image-input');
        var preview = document.getElementById(previewId);

        input.addEventListener('change', function () {
            if (!input.files || !input.files[0] || !preview) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        });
    });

    document.querySelectorAll('.upload-box[data-dropzone]').forEach(function (box) {
        var input = box.querySelector('input[type="file"]');
        if (!input) return;

        box.addEventListener('click', function () { input.click(); });

        ['dragover', 'dragenter'].forEach(function (evt) {
            box.addEventListener(evt, function (e) {
                e.preventDefault();
                box.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            box.addEventListener(evt, function (e) {
                e.preventDefault();
                box.classList.remove('dragover');
            });
        });

        box.addEventListener('drop', function (e) {
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    /* =========================================================
       DASHBOARD CHART (Chart.js — vendored locally, no CDN)
    ========================================================= */
    var chartCanvas = document.getElementById('bookingsChart');
    if (chartCanvas && window.Chart) {
        var labels = JSON.parse(chartCanvas.getAttribute('data-labels') || '[]');
        var data = JSON.parse(chartCanvas.getAttribute('data-values') || '[]');

        new window.Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Bookings',
                    data: data,
                    borderColor: '#0f6b45',
                    backgroundColor: 'rgba(15, 107, 69, 0.1)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 2,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    }

    /* =========================================================
       SIDEBAR ACTIVE LINK
    ========================================================= */
    var path = window.location.pathname;
    document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        if (link.getAttribute('href') && path.startsWith(link.getAttribute('href')) && link.getAttribute('href') !== '/admin') {
            link.classList.add('active');
        }
    });
});
