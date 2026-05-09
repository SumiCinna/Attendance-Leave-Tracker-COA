// js/admin.js
const getEl = (id) => document.getElementById(id);

let currentMode = "select";

function setMode(mode) {
    currentMode = mode;

    const selectMode        = getEl("selectMode");
    const manualMode        = getEl("manualMode");
    const modeSelectBtn     = getEl("modeSelectBtn");
    const modeManualBtn     = getEl("modeManualBtn");
    const manualUserId      = getEl("manual_user_id");
    const userSelect        = getEl("user_select");
    const selectEmailDisplay = getEl("select_email_display");
    const manualFirstName   = getEl("manual_first_name");
    const manualMiddleName  = getEl("manual_middle_name");
    const manualLastName    = getEl("manual_last_name");
    const manualEmailInput  = getEl("manual_email_input");
    const addLeaveError     = getEl("addLeaveError");

    if (selectMode)        selectMode.style.display    = mode === "select" ? "block" : "none";
    if (manualMode)        manualMode.style.display     = mode === "manual" ? "block" : "none";
    if (modeSelectBtn)     modeSelectBtn.classList.toggle("active", mode === "select");
    if (modeManualBtn)     modeManualBtn.classList.toggle("active", mode === "manual");

    if (manualUserId)      manualUserId.value       = "";
    if (userSelect)        userSelect.value          = "";
    if (selectEmailDisplay) selectEmailDisplay.value = "";
    if (manualFirstName)   manualFirstName.value     = "";
    if (manualMiddleName)  manualMiddleName.value    = "";
    if (manualLastName)    manualLastName.value       = "";
    if (manualEmailInput)  manualEmailInput.value    = "";
    if (addLeaveError)     addLeaveError.style.display = "none";
}

function showError(msg) {
    const el = getEl("addLeaveError");
    if (!el) return;
    el.textContent    = msg;
    el.style.display  = "block";
}

function submitAddLeave() {
    const addLeaveError = getEl("addLeaveError");
    if (addLeaveError) addLeaveError.style.display = "none";

    const userSelect   = getEl("user_select");
    const manualUserId = getEl("manual_user_id");
    if (userSelect && manualUserId) {
        manualUserId.value = userSelect.value || "";
    }

    const leaveDate = getEl("manual_leave_date")?.value.trim() || "";
    const leaveType = getEl("manual_leave_type")?.value.trim() || "";
    const reason    = getEl("manual_reason")?.value.trim() || "";

    if (currentMode === "select") {
        const uid = manualUserId?.value || "";
        if (!uid) {
            showError("Please select an employee.");
            return;
        }
    } else {
        const first = getEl("manual_first_name")?.value.trim() || "";
        const last  = getEl("manual_last_name")?.value.trim()  || "";
        const email = getEl("manual_email_input")?.value.trim() || "";
        if (!first || !last) {
            showError("First and last name are required.");
            return;
        }
        if (!email) {
            showError("Gmail is required.");
            return;
        }
        if (!/^[^\s@]+@gmail\.com$/i.test(email)) {
            showError("Enter a valid @gmail.com address.");
            return;
        }
    }

    if (!leaveDate) {
        showError("Leave date is required.");
        return;
    }
    if (!leaveType) {
        showError("Please select a leave type.");
        return;
    }
    if (!reason) {
        showError("Reason is required.");
        return;
    }
    if (reason.length > 100) {
        showError("Reason must be 100 characters or less.");
        return;
    }

    const form = getEl("addLeaveForm");
    if (form) form.submit();
}

function openAddLeaveModal() {
    setMode("select");
    const leaveDate      = getEl("manual_leave_date");
    const leaveType      = getEl("manual_leave_type");
    const reason         = getEl("manual_reason");
    const reasonCount    = getEl("reasonCount");
    const firstNameCount = getEl("firstNameCount");
    const middleNameCount = getEl("middleNameCount");
    const lastNameCount  = getEl("lastNameCount");
    const addLeaveModal  = getEl("addLeaveModal");

    if (leaveDate)      leaveDate.value          = "";
    if (leaveType)      leaveType.value           = "";
    if (reason)         reason.value              = "";
    if (reasonCount)    reasonCount.textContent   = "0 / 100";
    if (firstNameCount) firstNameCount.textContent = "0 / 100";
    if (middleNameCount) middleNameCount.textContent = "0 / 100";
    if (lastNameCount)  lastNameCount.textContent  = "0 / 100";
    if (addLeaveModal)  addLeaveModal.classList.add("open");
}

function closeAddLeaveModal() {
    const addLeaveModal = getEl("addLeaveModal");
    if (addLeaveModal) addLeaveModal.classList.remove("open");
}

function openRejectModal(id) {
    const rejectAbsenceId = getEl("reject_absence_id");
    const rejectModal     = getEl("rejectModal");
    if (rejectAbsenceId) rejectAbsenceId.value    = id;
    if (rejectModal)     rejectModal.style.display = "flex";
}

function closeRejectModal() {
    const rejectModal = getEl("rejectModal");
    if (rejectModal) rejectModal.style.display = "none";
}

function openApproveModal(id) {
    const approveAbsenceId = getEl("approve_absence_id");
    const approveModal     = getEl("approveModal");
    if (approveAbsenceId) approveAbsenceId.value    = id;
    if (approveModal)     approveModal.style.display = "flex";
}

function closeApproveModal() {
    const approveModal = getEl("approveModal");
    if (approveModal) approveModal.style.display = "none";
}

window.setMode           = setMode;
window.submitAddLeave    = submitAddLeave;
window.openRejectModal   = openRejectModal;
window.closeRejectModal  = closeRejectModal;
window.openApproveModal  = openApproveModal;
window.closeApproveModal = closeApproveModal;
window.closeAddLeaveModal = closeAddLeaveModal;

document.addEventListener("DOMContentLoaded", () => {

    // ── Print / Export PDF ──────────────────────────────────────────────────
    const printBtn = getEl("printBtn");
    if (printBtn) {
        printBtn.addEventListener("click", () => window.print());
    }

    // ── Archive Toggle Switch ───────────────────────────────────────────────
    const archiveToggle = getEl("archiveToggle");
    if (archiveToggle) {
        archiveToggle.addEventListener("change", () => {
            const url = new URL(window.location.href);

            // Remove search/filter params so pagination resets cleanly
            url.searchParams.delete("page");

            if (archiveToggle.checked) {
                url.searchParams.set("archived", "1");
            } else {
                url.searchParams.delete("archived");
            }

            window.location.href = url.toString();
        });
    }

    // ── Employee Select → Auto-fill email ──────────────────────────────────
    const userSelect = getEl("user_select");
    if (userSelect) {
        userSelect.addEventListener("change", () => {
            const opt              = userSelect.options[userSelect.selectedIndex];
            const manualUserId     = getEl("manual_user_id");
            const selectEmailDisplay = getEl("select_email_display");
            if (manualUserId)       manualUserId.value      = opt?.value || "";
            if (selectEmailDisplay) selectEmailDisplay.value = opt?.value ? opt.getAttribute("data-email") : "";
        });
    }

    // ── Character Counters ──────────────────────────────────────────────────
    function attachCounter(inputId, countId) {
        const input = getEl(inputId);
        if (!input) return;
        input.addEventListener("input", () => {
            const len = input.value.length;
            const el  = getEl(countId);
            if (el) {
                el.textContent = `${len} / 100`;
                el.classList.toggle("warn", len >= 90);
            }
        });
    }
    attachCounter("manual_reason",      "reasonCount");
    attachCounter("manual_first_name",  "firstNameCount");
    attachCounter("manual_middle_name", "middleNameCount");
    attachCounter("manual_last_name",   "lastNameCount");

    // ── Open Add Leave Modal ────────────────────────────────────────────────
    const openAddLeaveBtn = getEl("openAddLeaveBtn");
    if (openAddLeaveBtn) {
        openAddLeaveBtn.addEventListener("click", openAddLeaveModal);
    }

    // ── Close Add Leave Modal on backdrop click ─────────────────────────────
    const addLeaveModal = getEl("addLeaveModal");
    if (addLeaveModal) {
        addLeaveModal.addEventListener("click", (e) => {
            if (e.target === addLeaveModal) closeAddLeaveModal();
        });
    }

    // ── Toast auto-dismiss ──────────────────────────────────────────────────
    const addLeaveToast = getEl("addLeaveToast");
    if (addLeaveToast) {
        setTimeout(() => { addLeaveToast.style.display = "none"; }, 3500);
    }

    // ── Logout Modal ────────────────────────────────────────────────────────
    const logoutModal  = getEl("logoutModal");
    const logoutBtn    = getEl("logoutBtn");
    const logoutCancel = getEl("logoutCancel");

    if (logoutBtn && logoutModal) {
        logoutBtn.addEventListener("click", (e) => {
            e.preventDefault();
            logoutModal.style.display = "flex";
        });
    }
    if (logoutCancel && logoutModal) {
        logoutCancel.addEventListener("click", () => {
            logoutModal.style.display = "none";
        });
    }
    if (logoutModal) {
        logoutModal.addEventListener("click", (e) => {
            if (e.target === logoutModal) logoutModal.style.display = "none";
        });
    }

    // ── Global Escape key ───────────────────────────────────────────────────
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            if (logoutModal && logoutModal.style.display === "flex") logoutModal.style.display = "none";
            closeAddLeaveModal();
            closeRejectModal();
            closeApproveModal();
        }
    });
});

function printTable() {
    const tableHTML = document.querySelector('.table-wrap').innerHTML;

    let iframe = document.getElementById('printFrame');
    if (iframe) iframe.remove();

    iframe = document.createElement('iframe');
    iframe.id = 'printFrame';
    iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:none;';
    document.body.appendChild(iframe);

    const doc = iframe.contentDocument || iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Admin | Attendance Leave Tracker</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: sans-serif; padding: 24px; font-size: 12px; }
                h2 { font-size: 15px; font-weight: 700; margin-bottom: 16px; color: #1a2e5a; }
                table { width: 100%; border-collapse: collapse; }
                thead tr { background: #1a2e5a !important; }
                th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 700;
                     color: #ffffff !important; letter-spacing: 0.5px; text-transform: uppercase;
                     -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                td { padding: 10px 12px; font-size: 12px; border-bottom: 1px solid #e0e4f0; color: #333; }
                tr:last-child td { border-bottom: none; }
                button, a, form { display: none !important; }
                @media print {
                    thead tr { background: #1a2e5a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    th { color: #ffffff !important; }
                }
            </style>
        </head>
        <body>
            <h2>Admin Leave Overview </h2>
            <table>${tableHTML}</table>
        </body>
        </html>
    `);
    doc.close();

    iframe.contentWindow.focus();
    iframe.contentWindow.print();
}