document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const logForm = document.getElementById('logForm');
    const profileForm = document.getElementById('profileForm');
    const passwordForm = document.getElementById('passwordForm');
    const logTableBody = document.querySelector('tbody');
    const statusMessage = document.getElementById('status-message');

    // Custom Modal Elements
    const modal = document.getElementById('customModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalCancel = document.getElementById('modalCancel');
    const modalConfirm = document.getElementById('modalConfirm');
    let modalResolver = null;

    function showModal(title, msg, confirmText = "Confirm") {
        modalTitle.textContent = title;
        modalMessage.textContent = msg;
        modalConfirm.textContent = confirmText;
        modal.classList.remove('hidden');
        return new Promise((resolve) => {
            modalResolver = resolve;
        });
    }

    function hideModal(value) {
        modal.classList.add('hidden');
        if (modalResolver) modalResolver(value);
        modalResolver = null;
    }

    if (modalCancel) modalCancel.onclick = () => hideModal(false);
    if (modalConfirm) modalConfirm.onclick = () => hideModal(true);

    const mAbsentBtn = document.getElementById('markAbsentBtn');
    const mPresentBtn = document.getElementById('markPresentBtn');
    const tInputs = document.getElementById('timeInputs');
    const tArea = document.getElementById('tasksInput');
    const rArea = document.getElementById('remarksInput');
    const sInput = document.getElementById('statusInput');

    if (mAbsentBtn) {
        mAbsentBtn.onclick = () => {
            sInput.value = 'Absent';
            tInputs.classList.add('hidden');
            tArea.classList.add('hidden');
            rArea.classList.remove('hidden');
            mAbsentBtn.classList.add('hidden');
            mPresentBtn.classList.remove('hidden');
            const tasks = tArea.querySelector('textarea');
            if (tasks) tasks.required = false;
        };
    }

    if (mPresentBtn) {
        mPresentBtn.onclick = () => {
            sInput.value = 'Present';
            tInputs.classList.remove('hidden');
            tArea.classList.remove('hidden');
            rArea.classList.add('hidden');
            mPresentBtn.classList.add('hidden');
            mAbsentBtn.classList.remove('hidden');
            const tasks = tArea.querySelector('textarea');
            if (tasks) tasks.required = true;
        };
    }

    const handleResponse = (res) => {
        if (statusMessage) {
            statusMessage.textContent = res.message.toUpperCase();
            statusMessage.className = `mb-6 p-4 rounded-lg font-bold text-xs uppercase tracking-widest ${res.success ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`;
            statusMessage.classList.remove('hidden');
            setTimeout(() => statusMessage.classList.add('hidden'), 5000);
        }
        
        if (res.success && res.logs) {
            // Update table and metrics using server-calculated values
            updateTableUI(res.logs, res.grand_total, res.estimated_date, res.remaining_days);
        }
    };

    function updateTableUI(logs, total, estDate, remDays) {
        // UI Target Elements
        const hourDisplays = document.querySelectorAll('.total-hours-header');
        const countDisplays = document.querySelectorAll('.total-logs-count');
        const daysLeftDisplays = document.querySelectorAll('.total-days-left, #remaining-days-stat');
        const estDateDisplays = document.querySelectorAll('#est-date-header, #est-date-stat');
        const progressBar = document.getElementById('progress-bar');
        
        const goal = 486;
        const progressPercent = Math.min(100, (total / goal) * 100);

        // Update Total Hours
        hourDisplays.forEach(el => el.textContent = `${parseFloat(total).toFixed(2)}h`);
        
        // Update Total Logs count
        countDisplays.forEach(el => el.textContent = logs.length);
        
        // Update Remaining Days (Using server calculated value)
        daysLeftDisplays.forEach(el => {
            const isStat = el.id === 'remaining-days-stat';
            el.textContent = isStat ? remDays : `${remDays}d`;
        });

        // Update Estimated Completion Date (Using server calculated value)
        if (estDate) {
            estDateDisplays.forEach(el => el.textContent = estDate);
        }

        // Update Progress Bar if visible
        if (progressBar) {
            progressBar.style.width = `${progressPercent}%`;
            const progressLabel = progressBar.closest('.glass-card')?.querySelector('span.text-xs.font-mono');
            if (progressLabel) progressLabel.textContent = `${parseFloat(total).toFixed(1)} / ${goal} hours`;
        }

        if (!logTableBody) return;
        
        // Refresh Table Content
        logTableBody.innerHTML = logs.length ? logs.map(log => `
            <tr class="table-row">
                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">
                    ${log.formatted_date}
                    ${log.status === 'Absent' ? '<span class="ml-2 text-[8px] bg-red-500/20 text-red-400 px-1 rounded uppercase">Absent</span>' : ''}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                    ${log.status === 'Absent' ? '-- : --' : log.formatted_start + ' - ' + log.formatted_end}
                </td>
                <td class="px-6 py-4 text-sm max-w-xs truncate text-gray-400">
                    ${log.status === 'Absent' ? '<em>Reason: ' + (log.remarks || '') + '</em>' : log.tasks}
                </td>
                <td class="px-6 py-4 text-right font-mono font-bold text-blue-400">
                    ${parseFloat(log.total_hours).toFixed(2)}
                </td>
                <td class="px-6 py-4 text-right space-x-2">
                    <button data-id="${log.id}" data-log='${JSON.stringify(log)}' class="edit-btn text-blue-400 hover:text-blue-300 text-[10px] font-black uppercase tracking-widest">[Edit]</button>
                    <button data-id="${log.id}" class="delete-btn text-red-500/60 hover:text-red-500 text-[10px] font-black uppercase tracking-widest">[Remove]</button>
                </td>
            </tr>
        `).join('') : '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 font-mono text-xs uppercase">No logs detected.</td></tr>';
    }

    if (loginForm) {
        loginForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await fetch('actions/login.php', { method: 'POST', body: new FormData(loginForm) });
            const data = await res.json();
            if (data.success) location.href = '?page=dashboard';
            else handleResponse(data);
        };
    }

    if (signupForm) {
        signupForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await fetch('actions/register.php', { method: 'POST', body: new FormData(signupForm) });
            const data = await res.json();
            if (data.success) location.href = '?page=login';
            else handleResponse(data);
        };
    }

    if (logForm) {
        logForm.onsubmit = async (e) => {
            e.preventDefault();
            const idInput = document.getElementById('entryIdInput');
            const isEdit = idInput && idInput.value !== "";
            const confirmed = await showModal(
                isEdit ? "Update Session" : "Deploy Session", 
                isEdit ? "Commit changes to this sequence?" : "Deploy session to mainframe?",
                isEdit ? "Update" : "Deploy"
            );
            if (!confirmed) return;

            const res = await fetch(isEdit ? 'actions/update_log.php' : 'actions/insert_log.php', {
                method: 'POST',
                body: new FormData(logForm)
            });
            const data = await res.json();
            handleResponse(data);
            if (data.success) {
                logForm.reset();
                if (idInput) idInput.value = "";
                const title = document.getElementById('formTitle');
                const btn = document.getElementById('submitBtn');
                if (title) title.textContent = "New Entry";
                if (btn) btn.textContent = "Deploy Entry";
                if (mPresentBtn) mPresentBtn.click();
            }
        };
    }

    if (profileForm) {
        profileForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await fetch('actions/update_profile.php', { method: 'POST', body: new FormData(profileForm) });
            handleResponse(await res.json());
        };
    }

    if (passwordForm) {
        passwordForm.onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(passwordForm);
            if (fd.get('new_password') !== fd.get('confirm_password')) {
                handleResponse({ success: false, message: 'Passwords mismatch.' });
                return;
            }
            const res = await fetch('actions/update_password.php', { method: 'POST', body: fd });
            const data = await res.json();
            handleResponse(data);
            if (data.success) passwordForm.reset();
        };
    }

    if (logTableBody) {
        logTableBody.onclick = async (e) => {
            const id = e.target.dataset.id;
            if (e.target.classList.contains('delete-btn')) {
                const confirmed = await showModal("Destroy Record", "Purge this session from console?", "Destroy");
                if (!confirmed) return;
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('actions/delete_log.php', { method: 'POST', body: fd });
                handleResponse(await res.json());
            } else if (e.target.classList.contains('edit-btn')) {
                const log = JSON.parse(e.target.dataset.log);
                const title = document.getElementById('formTitle');
                const btn = document.getElementById('submitBtn');
                const idInput = document.getElementById('entryIdInput');
                if (title) title.textContent = "Edit Entry";
                if (btn) btn.textContent = "Update Entry";
                if (idInput) idInput.value = log.id;
                logForm.querySelector('[name="log_date"]').value = log.log_date;
                if (log.status === 'Absent') {
                    if (mAbsentBtn) mAbsentBtn.click();
                    logForm.querySelector('[name="remarks"]').value = log.remarks;
                } else {
                    if (mPresentBtn) mPresentBtn.click();
                    logForm.querySelector('[name="start_time"]').value = log.start_time;
                    logForm.querySelector('[name="end_time"]').value = log.end_time;
                    logForm.querySelector('[name="tasks"]').value = log.tasks;
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
    }
});