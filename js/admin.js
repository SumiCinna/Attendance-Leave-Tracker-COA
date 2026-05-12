// js/admin.js
const getEl = (id) => document.getElementById(id);

let currentMode = "select";

function setMode(mode) {
    currentMode = mode;

    const selectMode         = getEl("selectMode");
    const manualMode         = getEl("manualMode");
    const modeSelectBtn      = getEl("modeSelectBtn");
    const modeManualBtn      = getEl("modeManualBtn");
    const manualUserId       = getEl("manual_user_id");
    const userSelect         = getEl("user_select");
    const selectEmailDisplay = getEl("select_email_display");
    const manualFirstName    = getEl("manual_first_name");
    const manualMiddleName   = getEl("manual_middle_name");
    const manualLastName     = getEl("manual_last_name");
    const manualEmailInput   = getEl("manual_email_input");
    const addLeaveError      = getEl("addLeaveError");

    if (selectMode)         selectMode.style.display     = mode === "select" ? "block" : "none";
    if (manualMode)         manualMode.style.display      = mode === "manual" ? "block" : "none";
    if (modeSelectBtn)      modeSelectBtn.classList.toggle("active", mode === "select");
    if (modeManualBtn)      modeManualBtn.classList.toggle("active", mode === "manual");

    if (manualUserId)       manualUserId.value        = "";
    if (userSelect)         userSelect.value           = "";
    if (selectEmailDisplay) selectEmailDisplay.value   = "";
    if (manualFirstName)    manualFirstName.value      = "";
    if (manualMiddleName)   manualMiddleName.value     = "";
    if (manualLastName)     manualLastName.value        = "";
    if (manualEmailInput)   manualEmailInput.value     = "";
    if (addLeaveError)      addLeaveError.style.display = "none";
}

function showError(msg) {
    const el = getEl("addLeaveError");
    if (!el) return;
    el.textContent   = msg;
    el.style.display = "block";
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

    if (currentMode === "select") {
        const uid = manualUserId?.value || "";
        if (!uid) { showError("Please select an employee."); return; }
    } else {
        const first = getEl("manual_first_name")?.value.trim()  || "";
        const last  = getEl("manual_last_name")?.value.trim()   || "";
        const email = getEl("manual_email_input")?.value.trim() || "";
        if (!first || !last) { showError("First and last name are required."); return; }
        if (!email)           { showError("Gmail is required."); return; }
        if (!/^[^\s@]+@gmail\.com$/i.test(email)) {
            showError("Enter a valid @gmail.com address.");
            return;
        }
    }

    if (!leaveDate) { showError("Leave date is required."); return; }
    if (!leaveType) { showError("Please select a leave type."); return; }
    const form = getEl("addLeaveForm");
    if (form) form.submit();
}

function openAddLeaveModal() {
    setMode("select");
    const leaveDate       = getEl("manual_leave_date");
    const leaveType       = getEl("manual_leave_type");
    const firstNameCount  = getEl("firstNameCount");
    const middleNameCount = getEl("middleNameCount");
    const lastNameCount   = getEl("lastNameCount");
    const addLeaveModal   = getEl("addLeaveModal");

    if (leaveDate)       leaveDate.value               = "";
    if (leaveType)       leaveType.value                = "";
    if (firstNameCount)  firstNameCount.textContent     = "0 / 100";
    if (middleNameCount) middleNameCount.textContent    = "0 / 100";
    if (lastNameCount)   lastNameCount.textContent      = "0 / 100";
    if (addLeaveModal)   addLeaveModal.classList.add("open");
}

function closeAddLeaveModal() {
    const addLeaveModal = getEl("addLeaveModal");
    if (addLeaveModal) addLeaveModal.classList.remove("open");
}

// ── Archive Confirm Modal ────────────────────────────────────────────────────
let _pendingArchiveForm = null;

function openArchiveModal(formEl) {
    _pendingArchiveForm = formEl;
    const archiveModal = getEl("archiveConfirmModal");
    if (archiveModal) archiveModal.style.display = "flex";
}

function closeArchiveModal() {
    _pendingArchiveForm = null;
    const archiveModal = getEl("archiveConfirmModal");
    if (archiveModal) archiveModal.style.display = "none";
}

function confirmArchive() {
    if (_pendingArchiveForm) _pendingArchiveForm.submit();
    closeArchiveModal();
}

window.setMode            = setMode;
window.submitAddLeave     = submitAddLeave;
window.closeAddLeaveModal = closeAddLeaveModal;
window.openArchiveModal   = openArchiveModal;
window.closeArchiveModal  = closeArchiveModal;
window.confirmArchive     = confirmArchive;
window.closeDeleteLeavesModal = () => {
    const deleteLeavesModal = getEl("deleteLeavesModal");
    if (deleteLeavesModal) deleteLeavesModal.classList.remove("open");
};

document.addEventListener("DOMContentLoaded", () => {

    // ── Leave Type Modal ─────────────────────────────────────────────────────
    const ltModal       = getEl("ltModal");
    const ltDeleteModal = getEl("ltDeleteModal");

    window.openLtModal  = () => { if (ltModal) ltModal.style.display = "flex"; };
    window.closeLtModal = () => { if (ltModal) ltModal.style.display = "none"; };

    window.openLtDeleteModal = (id, name) => {
        const ltDeleteId   = getEl("ltDeleteId");
        const ltDeleteName = getEl("ltDeleteName");
        if (ltDeleteId)   ltDeleteId.value        = id;
        if (ltDeleteName) ltDeleteName.textContent = name;
        if (ltDeleteModal) ltDeleteModal.style.display = "flex";
    };
    window.closeLtDeleteModal = () => {
        if (ltDeleteModal) ltDeleteModal.style.display = "none";
    };

    // Close LT modals on backdrop click
    [ltModal, ltDeleteModal].forEach(modal => {
        if (modal) {
            modal.addEventListener("click", e => {
                if (e.target === modal) modal.style.display = "none";
            });
        }
    });

    getEl("openLtModalBtn")?.addEventListener("click", () => window.openLtModal());

    // Re-open LT modal after redirect (controlled via data attribute)
    if (document.body.dataset.reopenLt === "1") {
        window.openLtModal();
    }

    // ── LT name character counter (50-char limit) ────────────────────────────
    const ltInput = getEl("newLeaveTypeName");
    const ltCount = getEl("ltCharCount");
    if (ltInput && ltCount) {
        ltInput.addEventListener("input", () => {
            const len = ltInput.value.length;
            ltCount.textContent = `${len} / 50`;
            ltCount.classList.remove("near-limit", "at-limit");
            if (len >= 50)      ltCount.classList.add("at-limit");
            else if (len >= 40) ltCount.classList.add("near-limit");
        });
    }

    // Inline validation for add leave-type form
    window.validateNewLeaveType = () => {
        const input = getEl("newLeaveTypeName");
        const err   = getEl("ltAddError");
        if (!input.value.trim()) {
            err.textContent   = "Please enter a leave type name.";
            err.style.display = "block";
            input.focus();
            return false;
        }
        if (input.value.trim().length > 50) {
            err.textContent   = "Leave type name must be 50 characters or less.";
            err.style.display = "block";
            input.focus();
            return false;
        }
        err.style.display = "none";
        return true;
    };

    // ── Auto-dismiss toasts ──────────────────────────────────────────────────
    ["addLeaveToast", "ltToast", "deleteToast"].forEach(id => {
        const el = getEl(id);
        if (el) setTimeout(() => {
            el.style.transition = "opacity 0.5s";
            el.style.opacity    = "0";
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });

    // ── Toggle password visibility ─────────────────────────────────────────
    const toggleButtons = document.querySelectorAll(".toggle-password");
    toggleButtons.forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            const targetId = button.getAttribute("data-target");
            const input = document.getElementById(targetId);
            if (!input) return;
            const isPassword = input.getAttribute("type") === "password";
            input.setAttribute("type", isPassword ? "text" : "password");

            const eyeOpen = button.querySelector(".eye-open");
            const eyeClosed = button.querySelector(".eye-closed");
            if (eyeOpen && eyeClosed) {
                if (isPassword) {
                    eyeOpen.style.display = "none";
                    eyeClosed.style.display = "block";
                } else {
                    eyeOpen.style.display = "block";
                    eyeClosed.style.display = "none";
                }
            }
        });
    });

    // ── Print / Export PDF ───────────────────────────────────────────────────
    const printBtn = getEl("printBtn");
    if (printBtn) printBtn.addEventListener("click", () => window.print());

    // ── Archive Toggle Switch ────────────────────────────────────────────────
    const archiveToggle = getEl("archiveToggle");
    if (archiveToggle) {
        archiveToggle.addEventListener("change", () => {
            const url = new URL(window.location.href);
            url.searchParams.delete("page");
            if (archiveToggle.checked) {
                url.searchParams.set("archived", "1");
            } else {
                url.searchParams.delete("archived");
            }
            window.location.href = url.toString();
        });
    }

    // ── Employee Select → Auto-fill email ───────────────────────────────────
    const userSelect = getEl("user_select");
    if (userSelect) {
        userSelect.addEventListener("change", () => {
            const opt              = userSelect.options[userSelect.selectedIndex];
            const manualUserId     = getEl("manual_user_id");
            const selectEmailDisplay = getEl("select_email_display");
            if (manualUserId)        manualUserId.value       = opt?.value || "";
            if (selectEmailDisplay)  selectEmailDisplay.value = opt?.value ? opt.getAttribute("data-email") : "";
        });
    }

    // ── Character Counters ───────────────────────────────────────────────────
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
    attachCounter("manual_first_name",  "firstNameCount");
    attachCounter("manual_middle_name", "middleNameCount");
    attachCounter("manual_last_name",   "lastNameCount");

    // ── Open Add Leave Modal ─────────────────────────────────────────────────
    getEl("openAddLeaveBtn")?.addEventListener("click", openAddLeaveModal);

    // ── Close Add Leave Modal on backdrop click ──────────────────────────────
    const addLeaveModal = getEl("addLeaveModal");
    if (addLeaveModal) {
        addLeaveModal.addEventListener("click", e => {
            if (e.target === addLeaveModal) closeAddLeaveModal();
        });
    }

    // ── Archive Confirm Modal backdrop click ─────────────────────────────────
    const archiveConfirmModal = getEl("archiveConfirmModal");
    if (archiveConfirmModal) {
        archiveConfirmModal.addEventListener("click", e => {
            if (e.target === archiveConfirmModal) closeArchiveModal();
        });
    }

    // ── Logout Modal ─────────────────────────────────────────────────────────
    const logoutModal  = getEl("logoutModal");
    const logoutBtn    = getEl("logoutBtn");
    const logoutCancel = getEl("logoutCancel");

    if (logoutBtn && logoutModal) {
        logoutBtn.addEventListener("click", e => {
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
        logoutModal.addEventListener("click", e => {
            if (e.target === logoutModal) logoutModal.style.display = "none";
        });
    }

    // ── Global Escape key ────────────────────────────────────────────────────
    document.addEventListener("keydown", e => {
        if (e.key === "Escape") {
            if (logoutModal && logoutModal.style.display === "flex") logoutModal.style.display = "none";
            closeAddLeaveModal();
            closeArchiveModal();
            closeDeleteLeavesModal();
            if (typeof closeCalendarModal === "function") closeCalendarModal();
            if (ltModal && ltModal.style.display === "flex")             window.closeLtModal();
            if (ltDeleteModal && ltDeleteModal.style.display === "flex") window.closeLtDeleteModal();
        }
    });

    // ── Employee Calendar ─────────────────────────────────────────────────-
    const calendarData = window.calendarData || null;
    const calendarGrid = getEl("calendarGrid");
    const calendarTitle = getEl("calendarTitle");
    const calendarLegend = getEl("calendarLegend");
    const leaveList = getEl("leaveList");
    const calendarModal = getEl("calendarModal");
    const calendarSubtitle = getEl("calendarSubtitle");
    const calendarButtons = document.querySelectorAll(".btn-calendar");
    const archiveAbsenceForm = getEl("archiveAbsenceForm");
    const archiveAbsenceId = getEl("archiveAbsenceId");
    const archiveAbsenceValue = getEl("archiveAbsenceValue");
    const deleteLeavesModal = getEl("deleteLeavesModal");
    const deleteLeavesForm = getEl("deleteLeavesForm");
    const deleteAbsenceId = getEl("deleteAbsenceId");
    const deleteLeavesSub = getEl("deleteLeavesSub");

    if (calendarData && calendarGrid) {
        const year = Number(calendarData.year) || new Date().getFullYear();
        const entriesByUser = new Map();

(calendarData.entries || []).forEach(entry => {
    const key = String(entry.employee_name).trim().toLowerCase();

    if (!entriesByUser.has(key)) {
        entriesByUser.set(key, []);
    }

    entriesByUser.get(key).push(entry);
});

        const palette = [
            "#2563eb", "#16a34a", "#f97316", "#7c3aed", "#0ea5e9",
            "#dc2626", "#0f766e", "#f59e0b", "#ec4899", "#4f46e5",
            "#059669", "#9333ea"
        ];

        const normalizeType = (type) => {
            const raw = String(type || "").trim();
            if (!raw) return "Other";
            if (/vacation/i.test(raw)) return "Vacation Leave/Forced Leave";
            if (/forced/i.test(raw)) return "Vacation Leave/Forced Leave";
            return raw;
        };

        const typeColorMap = new Map();
        const typeList = (calendarData.types || []).map(normalizeType);
        const allowedTypes = new Set(typeList);
        typeList.forEach((type, index) => {
            if (!typeColorMap.has(type)) {
                typeColorMap.set(type, palette[index % palette.length]);
            }
        });

        const getTypeColor = (type) => {
            const key = normalizeType(type);
            if (!allowedTypes.has(key)) {
                return "#9ca3af";
            }
            return typeColorMap.get(key) || "#9ca3af";
        };

        const renderLegend = () => {
            if (!calendarLegend) return;
            calendarLegend.innerHTML = "";
            Array.from(typeColorMap.entries()).forEach(([type, color]) => {
                const item = document.createElement("span");
                item.className = "legend-item";
                const dot = document.createElement("span");
                dot.className = "legend-dot";
                dot.style.background = color;
                item.appendChild(dot);
                item.appendChild(document.createTextNode(type));
                calendarLegend.appendChild(item);
            });
        };

        const renderLeaveList = (entries) => {
            if (!leaveList) return;
            leaveList.innerHTML = "";
            if (!entries.length) {
                leaveList.innerHTML = '<div class="muted" style="padding:8px 0;">No leave records for this employee.</div>';
                return;
            }

                const listTitle = document.createElement("div");
            listTitle.className = "leave-list-title";
            listTitle.textContent = "Leave Dates";
            leaveList.appendChild(listTitle);

            const list = document.createElement("div");
            list.className = "leave-list-items";
            entries.forEach(entry => {
                const item = document.createElement("div");
                item.className = "leave-list-item";

                const date = document.createElement("span");
                date.textContent = entry.leave_date;
                date.className = "leave-list-date";

                const type = document.createElement("span");
                type.textContent = allowedTypes.has(normalizeType(entry.leave_type))
                    ? normalizeType(entry.leave_type)
                    : "Other";
                type.className = "leave-list-type";
                type.style.background = getTypeColor(entry.leave_type);

                const actions = document.createElement("div");
                actions.className = "leave-list-actions";

                const archiveBtn = document.createElement("button");
                archiveBtn.type = "button";
                archiveBtn.className = "btn btn-archive";
                const nextArchived = entry.is_archived ? 0 : 1;
                archiveBtn.textContent = entry.is_archived ? "Recover" : "Archive";
                archiveBtn.addEventListener("click", () => {
                    if (!archiveAbsenceForm) return;
                    if (archiveAbsenceId) archiveAbsenceId.value = entry.id;
                    if (archiveAbsenceValue) archiveAbsenceValue.value = String(nextArchived);
                    archiveAbsenceForm.submit();
                });

                const deleteBtn = document.createElement("button");
                deleteBtn.type = "button";
                deleteBtn.className = "btn btn-delete";
                deleteBtn.textContent = "Delete";
                deleteBtn.addEventListener("click", () => {
                    if (deleteLeavesModal) deleteLeavesModal.classList.add("open");
                    if (deleteAbsenceId) deleteAbsenceId.value = entry.id;
                    if (deleteLeavesForm) deleteLeavesForm.reset();
                    if (deleteLeavesSub) {
                        deleteLeavesSub.textContent = `Delete leave on ${entry.leave_date}? Enter your password to confirm.`;
                    }
                });

                actions.appendChild(archiveBtn);
                actions.appendChild(deleteBtn);

                item.appendChild(date);
                item.appendChild(type);
                item.appendChild(actions);
                list.appendChild(item);
            });
            leaveList.appendChild(list);
        };

        const renderCalendar = (employeeName) => {

    const userKey = String(employeeName)
        .trim()
        .toLowerCase();

    if (calendarTitle) {
        calendarTitle.textContent = `${employeeName} — ${year}`;
    }


            const userEntries = (entriesByUser.get(userKey) || []).slice();
            userEntries.sort((a, b) => (a.leave_date || "").localeCompare(b.leave_date || ""));
            renderLeaveList(userEntries);

            const entriesByMonthDay = Array.from({ length: 12 }, () => ({}));
            userEntries.forEach(entry => {
                if (!entry.leave_date) return;
                const [y, m, d] = entry.leave_date.split("-").map(Number);

const dateObj = new Date(y, m - 1, d);
                if (Number.isNaN(dateObj.getTime()) || dateObj.getFullYear() !== year) return;
                const monthIndex = dateObj.getMonth();
                const day = dateObj.getDate();
                if (!entriesByMonthDay[monthIndex][day]) entriesByMonthDay[monthIndex][day] = [];
                entriesByMonthDay[monthIndex][day].push(normalizeType(entry.leave_type || "Leave"));
            });

            calendarGrid.innerHTML = "";
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            const weekdayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

            monthNames.forEach((monthName, monthIndex) => {
                const card = document.createElement("div");
                card.className = "month-card";

                const header = document.createElement("div");
                header.className = "month-header";
                header.textContent = monthName;
                card.appendChild(header);

                const weekdays = document.createElement("div");
                weekdays.className = "weekday-row";
                weekdayNames.forEach(label => {
                    const dayLabel = document.createElement("div");
                    dayLabel.textContent = label;
                    weekdays.appendChild(dayLabel);
                });
                card.appendChild(weekdays);

                const grid = document.createElement("div");
                grid.className = "month-grid";

                const firstDay = new Date(year, monthIndex, 1).getDay();
                const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

                for (let i = 0; i < firstDay; i += 1) {
                    const emptyCell = document.createElement("div");
                    emptyCell.className = "calendar-day empty";
                    grid.appendChild(emptyCell);
                }

                for (let day = 1; day <= daysInMonth; day += 1) {
                    const cell = document.createElement("div");
                    cell.className = "calendar-day";

                    const number = document.createElement("div");
                    number.className = "day-number";
                    number.textContent = day;
                    cell.appendChild(number);

                    const labels = entriesByMonthDay[monthIndex][day] || [];
                    if (labels.length) {
                        const color = getTypeColor(labels[0]);
                        cell.style.borderColor = color;
                        number.style.background = color;
                        number.style.color = "#fff";

                        labels.forEach(labelText => {
                            const label = document.createElement("div");
                            label.className = "leave-label";
                            label.textContent = labelText;
                            label.style.background = color;
                            label.style.color = "#fff";
                            cell.appendChild(label);
                        });
                    }

                    grid.appendChild(cell);
                }

                card.appendChild(grid);
                calendarGrid.appendChild(card);
            });

            renderLegend();
        };

        const openCalendarModal = (userId) => {
            if (calendarModal) calendarModal.classList.add("open");
            if (calendarSubtitle) {
                calendarSubtitle.textContent = `Absences for ${year}.`;
            }
            renderCalendar(userId);
        };

        const closeCalendarModal = () => {
            if (calendarModal) calendarModal.classList.remove("open");
        };

        window.closeCalendarModal = closeCalendarModal;

        calendarButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        openCalendarModal(btn.dataset.employeeName);
    });
});

        if (calendarModal) {
            calendarModal.addEventListener("click", e => {
                if (e.target === calendarModal) closeCalendarModal();
            });
        }

        const validateDuplicateLeave = () => {
            const leaveDate = getEl("manual_leave_date")?.value || "";
            const userSelect = getEl("user_select")?.value || "";
            const manualUserId = getEl("manual_user_id")?.value || "";
            const userId = manualUserId || userSelect;
            if (!leaveDate || !userId) return true;
            if (existingLeaveSet.has(`${userId}|${leaveDate}`)) {
                showError("A leave entry already exists on that date for this employee.");
                return false;
            }
            return true;
        };

        ["manual_leave_date", "user_select"].forEach((id) => {
            const el = getEl(id);
            if (el) {
                el.addEventListener("change", () => {
                    const addLeaveError = getEl("addLeaveError");
                    if (addLeaveError) addLeaveError.style.display = "none";
                    validateDuplicateLeave();
                });
            }
        });

        const originalSubmit = submitAddLeave;
        window.submitAddLeave = () => {
            if (!validateDuplicateLeave()) return;
            originalSubmit();
        };
    }

    // ── Delete Leaves Modal ───────────────────────────────────────────────
    if (deleteLeavesModal) {
        deleteLeavesModal.addEventListener("click", e => {
            if (e.target === deleteLeavesModal) window.closeDeleteLeavesModal();
        });
    }
});

function printTable() {
    const calendarData = window.calendarData || null;
    if (!calendarData) return;
    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    const normalizeType = (type) => {
        const raw = String(type || "").trim();
        if (!raw) return "Other";
        if (/vacation/i.test(raw)) return "Vacation Leave/Forced Leave";
        if (/forced/i.test(raw)) return "Vacation Leave/Forced Leave";
        return raw;
    };
    const typeList = (calendarData.types || []).map(normalizeType);
    const users = calendarData.users || [];

    const data = {};
    (calendarData.entries || []).forEach(entry => {
        const type = normalizeType(entry.leave_type);
        if (!data[type]) data[type] = {};
        if (!data[type][entry.user_id]) data[type][entry.user_id] = {};
        if (!entry.leave_date) return;
        const [y, m, d] = entry.leave_date.split("-").map(Number);

const dateObj = new Date(y, m - 1, d);
        if (Number.isNaN(dateObj.getTime())) return;
        const month = dateObj.getMonth() + 1;
        const day = dateObj.getDate();
        if (!data[type][entry.user_id][month]) data[type][entry.user_id][month] = [];
        data[type][entry.user_id][month].push(day);
    });

    const buildTableHtml = () => {
        let html = "";
        typeList.forEach(type => {
            html += `
                <table class="export-table">
                    <tr class="type-row"><th colspan="13">${type}</th></tr>
                    <tr class="month-row">
                        <th>Employee</th>
                        ${months.map(m => `<th>${m}</th>`).join("")}
                    </tr>
            `;

            users.forEach(user => {
                const row = data[type]?.[user.id] || {};
                const cells = [];
                for (let m = 1; m <= 12; m += 1) {
                    const days = row[m] ? Array.from(new Set(row[m])).sort((a, b) => a - b) : [];
                    cells.push(`<td>${days.length ? days.join(", ") : ""}</td>`);
                }
                html += `
                    <tr>
                        <td>${user.name}</td>
                        ${cells.join("")}
                    </tr>
                `;
            });

            html += "</table><div class=\"section-gap\"></div>";
        });
        return html;
    };

    const tableHTML = buildTableHtml();

    let iframe = document.getElementById("printFrame");
    if (iframe) iframe.remove();

    iframe = document.createElement("iframe");
    iframe.id = "printFrame";
    iframe.style.cssText = "position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:none;";
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
                .export-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
                .export-table th, .export-table td {
                    border: 1px solid #dce4f5;
                    padding: 6px 8px;
                    font-size: 11px;
                    text-align: left;
                    vertical-align: top;
                }
                .type-row th {
                    background: #1a2e5a;
                    color: #fff;
                    font-size: 12px;
                    letter-spacing: 0.4px;
                }
                .month-row th {
                    background: #eef2fb;
                    color: #1a2e5a;
                    font-weight: 700;
                }
                .section-gap { height: 10px; }
                button, a, form { display: none !important; }
            </style>
        </head>
        <body>
            <h2>Leave Monitoring</h2>
            ${tableHTML}
        </body>
        </html>
    `);
    doc.close();

    iframe.contentWindow.focus();
    iframe.contentWindow.print();
}

function toggleEmployeeInput() {
    const isManual = document.getElementById('toggleManual').checked;
    document.getElementById('employeeSelectDiv').style.display = isManual ? 'none' : 'block';
    document.getElementById('employeeManualDiv').style.display = isManual ? 'block' : 'none';
}