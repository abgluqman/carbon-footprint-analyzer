// ============================================================
// carbon-footprint-analyzer/assets/js/dashboard.js
// Chart data is injected by dashboard.php via window.dashboardData
// ============================================================

// --- Emissions Trend Chart ---
const trendCtx = document.getElementById('emissionsTrendChart').getContext('2d');
const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: window.dashboardData.trendLabels,
        datasets: [{
            label: 'Total Emissions (kg CO₂)',
            data: window.dashboardData.trendData,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' kg CO₂';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value + ' kg';
                    }
                }
            }
        }
    }
});

// --- Category Breakdown Chart ---
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryChart = new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: window.dashboardData.categoryLabels,
        datasets: [{
            data: window.dashboardData.categoryData,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value.toFixed(2) + ' kg CO₂ (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// --- Sidebar Toggle ---
const sidebarToggle = document.getElementById('sidebarToggleBtn');
const sidebar = document.getElementById('sidebar');
let isModalOpen = false;
let modalOpeningInProgress = false;

function initSidebar() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && sidebar) {
        sidebar.classList.add('collapsed');
    }
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        if (!isModalOpen && !modalOpeningInProgress) {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
    });
}

document.addEventListener('click', function(e) {
    const modalTrigger = e.target.closest('[data-bs-toggle="modal"]');
    if (modalTrigger) {
        modalOpeningInProgress = true;
        setTimeout(() => { modalOpeningInProgress = false; }, 500);
    }
}, true);

function handleOutsideClick(e) {
    if (isModalOpen || modalOpeningInProgress) return;
    const isMainContent = e.target.closest('main');
    const isClickableElement = e.target.closest('button, a, input, select, textarea, .table, .card, .btn, .form-control');
    if (isMainContent && !isClickableElement) {
        if (sidebar && !sidebar.classList.contains('collapsed')) {
            if (!sidebar.contains(e.target) && !sidebarToggle?.contains(e.target)) {
                sidebar.classList.add('collapsed');
                localStorage.setItem('sidebarCollapsed', 'true');
            }
        }
    }
}

setTimeout(() => {
    document.addEventListener('click', handleOutsideClick);
    document.addEventListener('touchstart', handleOutsideClick);
}, 100);

const modals = document.querySelectorAll('.modal');
modals.forEach(modal => {
    modal.addEventListener('show.bs.modal', function() {
        isModalOpen = true;
        modalOpeningInProgress = true;
    });
    modal.addEventListener('shown.bs.modal', function() {
        isModalOpen = true;
        modalOpeningInProgress = false;
    });
    modal.addEventListener('hidden.bs.modal', function() {
        isModalOpen = false;
        modalOpeningInProgress = false;
    });
});

initSidebar();

// --- Modal Management ---
document.addEventListener('DOMContentLoaded', function() {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    if (backdrops.length > 0 && !document.querySelector('.modal.show')) {
        document.body.classList.remove('modal-open');
        backdrops.forEach(el => el.remove());
    }

    let isModalOpening = false;
    let currentOpenModal = null;

    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');

    modalTriggers.forEach(function(trigger) {
        trigger.removeAttribute('data-bs-toggle');

        const targetId = trigger.getAttribute('data-bs-target');
        const targetModal = document.querySelector(targetId);

        if (targetModal) {
            targetModal.setAttribute('aria-hidden', 'true');

            const modalInstance = new bootstrap.Modal(targetModal, {
                backdrop: true,
                keyboard: true,
                focus: true
            });

            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                if (isModalOpening) return;

                if (currentOpenModal && currentOpenModal !== modalInstance) {
                    currentOpenModal.hide();
                }

                isModalOpening = true;
                modalInstance.show();
                currentOpenModal = modalInstance;

                setTimeout(function() { isModalOpening = false; }, 500);
            }, { capture: true });

            targetModal.addEventListener('shown.bs.modal', function() {
                isModalOpening = false;
                targetModal.setAttribute('aria-hidden', 'false');
            });

            targetModal.addEventListener('hidden.bs.modal', function() {
                targetModal.setAttribute('aria-hidden', 'true');
                currentOpenModal = null;
            });
        }
    });
});