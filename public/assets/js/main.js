/**
 * Kabarak University - Student Consultation Booking System
 * Interactive Client-Side JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // 1. Mobile Sidebar Toggle & Overlay
    // -------------------------------------------------------------
    const sidebar = document.querySelector('.app-sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // -------------------------------------------------------------
    // 2. Auto-set Minimum Date to Today for Date Inputs
    // -------------------------------------------------------------
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"].min-today');
    dateInputs.forEach(input => {
        if (!input.getAttribute('min')) {
            input.setAttribute('min', today);
        }
    });

    // -------------------------------------------------------------
    // 3. Auto Calculate Slot End Time (+30 or +45 mins default)
    // -------------------------------------------------------------
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const durationSelect = document.getElementById('slot_duration');

    if (startTimeInput && endTimeInput) {
        function updateEndTime() {
            if (!startTimeInput.value) return;
            const duration = durationSelect ? parseInt(durationSelect.value, 10) : 45;
            const [hours, minutes] = startTimeInput.value.split(':').map(Number);
            const date = new Date();
            date.setHours(hours, minutes + duration, 0, 0);

            const hh = String(date.getHours()).padStart(2, '0');
            const mm = String(date.getMinutes()).padStart(2, '0');
            endTimeInput.value = `${hh}:${mm}`;
        }

        startTimeInput.addEventListener('change', updateEndTime);
        if (durationSelect) {
            durationSelect.addEventListener('change', updateEndTime);
        }
    }

    // -------------------------------------------------------------
    // 4. Client-side Live Directory Filtering (Instant Search)
    // -------------------------------------------------------------
    const searchInput = document.getElementById('directorySearch');
    const roleFilter = document.getElementById('roleFilter');
    const deptFilter = document.getElementById('deptFilter');
    const staffGrid = document.getElementById('staffCardsContainer');
    const noResultsMsg = document.getElementById('noSearchResults');

    function filterDirectory() {
        if (!staffGrid) return;

        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const role = (roleFilter ? roleFilter.value : '').toLowerCase().trim();
        const dept = (deptFilter ? deptFilter.value : '').toLowerCase().trim();

        const cards = staffGrid.querySelectorAll('.staff-directory-item');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const cardRole = (card.getAttribute('data-role') || '').toLowerCase();
            const cardDept = (card.getAttribute('data-department') || '').toLowerCase();
            const service = (card.getAttribute('data-service') || '').toLowerCase();

            const matchesQuery = !query || name.includes(query) || service.includes(query) || cardDept.includes(query);
            const matchesRole = !role || cardRole === role;
            const matchesDept = !dept || cardDept === dept;

            if (matchesQuery && matchesRole && matchesDept) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noResultsMsg) {
            noResultsMsg.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterDirectory);
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', filterDirectory);
    }
    if (deptFilter) {
        deptFilter.addEventListener('change', filterDirectory);
    }

    // -------------------------------------------------------------
    // 5. Character Count Tracker for Textareas
    // -------------------------------------------------------------
    const trackedTextareas = document.querySelectorAll('[data-char-count]');
    trackedTextareas.forEach(textarea => {
        const targetId = textarea.getAttribute('data-char-count');
        const counter = document.getElementById(targetId);
        const maxLen = textarea.getAttribute('maxlength') || 1000;

        function updateCount() {
            if (counter) {
                const len = textarea.value.length;
                counter.textContent = `${len} / ${maxLen} characters`;
                if (len >= maxLen) {
                    counter.classList.add('text-danger');
                } else {
                    counter.classList.remove('text-danger');
                }
            }
        }

        textarea.addEventListener('input', updateCount);
        updateCount();
    });

    // -------------------------------------------------------------
    // 6. Generic Action Confirmations
    // -------------------------------------------------------------
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            const msg = btn.getAttribute('data-confirm') || 'Are you sure you want to proceed with this action?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });
});
