(function () {
    "use strict";

    const trip = {
        title: "Vienna – Hamburg – Denmark Loop",
        range: "12–17 June 2026",
        traveler: "Felipe",
        currentTime: "2026-06-14T12:20",
        stops: ["Vienna", "Hamburg", "Ribe", "Kiel", "Vienna"]
    };

    let items = [
        { id: "flight-out", type: "flight", title: "OS 123 Vienna → Hamburg", date: "2026-06-12", time: "08:15", endTime: "09:45", location: "VIE", endLocation: "HAM", details: "Economy, seat 14C. Carry-on only." },
        { id: "hamburg-hotel", type: "lodging", title: "Hotel am Fischmarkt, Hamburg", date: "2026-06-12", endDate: "2026-06-14", time: "15:00", endTime: "11:00", location: "Hotel am Fischmarkt, Hamburg", details: "Breakfast included. Free cancellation until June 5." },
        { id: "hamburg-tour", type: "activity", title: "Speicherstadt walking tour", date: "2026-06-13", time: "10:00", endTime: "12:00", location: "Speicherstadt, Hamburg", details: "Meet at the Wasserschloss bridge." },
        { id: "rental-car", type: "car", title: "Rental car: Hamburg Airport → Ribe", date: "2026-06-14", time: "12:00", location: "Hamburg Airport", endLocation: "Ribe, Denmark", details: "Compact car, unlimited mileage." },
        { id: "ribe-hotel", type: "lodging", title: "Weis Stue Guesthouse, Ribe", date: "2026-06-14", endDate: "2026-06-16", time: "15:00", endTime: "10:00", location: "Weis Stue Guesthouse, Ribe, Denmark", details: "Denmark's oldest town. Ask for a room facing the square." },
        { id: "wadden-walk", type: "activity", title: "Wadden Sea tidal flat walk", date: "2026-06-15", time: "14:00", endTime: "17:00", location: "Vester Vedsted, Denmark", details: "Rubber boots provided. Start time depends on the tide." },
        { id: "drive-kiel", type: "car", title: "Drive: Ribe → Kiel", date: "2026-06-16", time: "10:00", location: "Ribe, Denmark", endLocation: "Kiel, Germany", details: "Border crossing near Flensburg, no stop needed." },
        { id: "kiel-hotel", type: "lodging", title: "Hotel Hafenblick, Kiel", date: "2026-06-16", endDate: "2026-06-17", time: "15:00", endTime: "11:00", location: "Hotel Hafenblick, Kiel", details: "" },
        { id: "return-car", type: "car", kind: "return", generated: true, title: "Return rental car", date: "2026-06-17", time: "07:30", location: "Kiel station", details: "Derived from the train note: rental car dropped off beforehand." },
        { id: "train-hamburg", type: "train", title: "RE Kiel Hbf → Hamburg Hbf", date: "2026-06-17", time: "08:20", endTime: "09:35", location: "Kiel Hbf", endLocation: "Hamburg Hbf", details: "Rental car dropped off at Kiel station beforehand." },
        { id: "flight-home", type: "flight", title: "OS 128 Hamburg → Vienna", date: "2026-06-17", time: "18:40", endTime: "20:05", location: "HAM", endLocation: "VIE", details: "Economy, seat 22A." }
    ];

    const dayDates = ["2026-06-12", "2026-06-13", "2026-06-14", "2026-06-15", "2026-06-16", "2026-06-17"];
    const typeLabels = { flight: "Flight", lodging: "Lodging", train: "Train", car: "Rental car", activity: "Activity", other: "Other" };
    const cityByDate = {
        "2026-06-12": "Hamburg arrival", "2026-06-13": "Hamburg", "2026-06-14": "Hamburg → Ribe",
        "2026-06-15": "Ribe & Wadden Sea", "2026-06-16": "Ribe → Kiel", "2026-06-17": "Kiel → Hamburg → Vienna"
    };
    let activeConcept = "ledger";
    let lastFocusedElement = null;
    let toastTimer = null;

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const escapeHtml = (value) => String(value || "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[char]);
    const dateTime = (item) => new Date(`${item.date}T${item.time || "00:00"}:00`).getTime();
    const currentMillis = () => new Date(`${trip.currentTime}:00`).getTime();
    const sortedItems = () => [...items].sort((a, b) => dateTime(a) - dateTime(b));
    const itemsForDay = (date) => {
        const entries = sortedItems().filter((item) => item.date === date);
        sortedItems().filter((item) => item.type === "lodging" && item.endDate === date && item.date !== date).forEach((item) => {
            entries.push({ ...item, date, time: item.endTime, title: `Check out: ${item.title}`, generated: true, checkout: true });
        });
        return entries.sort((a, b) => dateTime(a) - dateTime(b));
    };
    const actualItemsForDay = (date) => sortedItems().filter((item) => item.date === date);
    const formatDay = (date, options = {}) => new Intl.DateTimeFormat("en-GB", options).format(new Date(`${date}T12:00:00`));
    const isPast = (item) => dateTime(item) < currentMillis();
    const isCurrent = (item) => item.id === "rental-car";
    const isToday = (date) => date === trip.currentTime.slice(0, 10);
    const itemMeta = (item) => [item.location, item.endLocation && item.endLocation !== item.location ? `to ${item.endLocation}` : ""].filter(Boolean).join(" · ");
    const timeRange = (item) => [item.time || "Any time", item.endTime].filter(Boolean).join("–");

    function icon(type) {
        const paths = {
            flight: '<path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2.5 1.5V22l4-1 4 1v-1.5L13 19v-5.5L21 16Z"/>',
            lodging: '<path d="M3 20V8m0 8h18v4M7 16v-5h5a4 4 0 0 1 4 4v1M5.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/>',
            train: '<rect x="5" y="3" width="14" height="15" rx="3"/><path d="M8 21l2-3m6 3-2-3M8 7h8M8 12h.01M16 12h.01"/>',
            car: '<path d="m5 17-1 3m15-3 1 3M4 13l2-6h12l2 6v5H4v-5Zm0 0h16M7 15h.01M17 15h.01"/>',
            activity: '<path d="M12 21s7-5.1 7-12A7 7 0 0 0 5 9c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.2"/>',
            other: '<circle cx="12" cy="12" r="8"/><path d="M8 12h8M12 8v8"/>'
        };
        return `<svg aria-hidden="true" viewBox="0 0 24 24">${paths[type] || paths.other}</svg>`;
    }

    function conceptHeaderActions(className) {
        return `<div class="${className}">
            <button class="button-secondary" type="button" data-jump-now>Jump to now</button>
            <button class="button-primary" type="button" data-add-item>Add item</button>
        </div>`;
    }

    function briefContent(mode) {
        const current = items.find(isCurrent);
        const next = sortedItems().find((item) => dateTime(item) > currentMillis());
        if (mode === "ledger") {
            return `<div class="ledger-now">
                <article class="ledger-brief"><span class="ledger-brief-label">Now</span><div><h3>${escapeHtml(current.title)}</h3><p>${escapeHtml(timeRange(current))} · ${escapeHtml(itemMeta(current))}</p></div></article>
                <article class="ledger-brief"><span class="ledger-brief-label">Next</span><div><h3>${escapeHtml(next.title)}</h3><p>${escapeHtml(timeRange(next))} · ${escapeHtml(next.location)}</p></div></article>
            </div>`;
        }
        if (mode === "canvas") {
            return `<div class="canvas-briefs">
                <article class="canvas-brief"><span>Now</span><div><strong>${escapeHtml(current.title)}</strong><small>${escapeHtml(itemMeta(current))}</small></div></article>
                <article class="canvas-brief"><span>Next · ${escapeHtml(next.time)}</span><div><strong>${escapeHtml(next.title)}</strong><small>${escapeHtml(next.location)}</small></div></article>
            </div>`;
        }
        return "";
    }

    function renderLedger() {
        const dayNav = dayDates.map((date) => { const count = actualItemsForDay(date).length; return `<button class="day-jump${isToday(date) ? " is-current" : ""}" type="button" data-day-jump="${date}"><strong>${formatDay(date, { day: "2-digit" })}</strong><span>${formatDay(date, { weekday: "short" })}</span><small>${count} ${count === 1 ? "item" : "items"}</small></button>`; }).join("");
        const days = dayDates.map((date) => {
            const rows = itemsForDay(date).map((item) => {
                const checkout = Boolean(item.checkout);
                const displayed = item;
                return `<div class="ledger-row${isPast(displayed) ? " is-past" : ""}${isCurrent(displayed) ? " is-current" : ""}" ${isCurrent(displayed) ? "data-current-event" : ""}>
                    <time class="ledger-time" datetime="${displayed.date}T${displayed.time || "00:00"}">${escapeHtml(displayed.time || "—")}</time>
                    <span class="event-icon">${icon(displayed.type)}</span>
                    <div class="ledger-event">${eventTitle(displayed)}<small>${escapeHtml(checkout ? "Generated from lodging dates" : displayed.details)}</small></div>
                    <span class="ledger-place">${escapeHtml(checkout ? displayed.location : itemMeta(displayed))}</span>
                    <span class="ledger-state ${isCurrent(displayed) ? "now" : ""}">${isCurrent(displayed) ? "In progress" : isPast(displayed) ? "Passed" : displayed.generated ? "Generated" : "Planned"}</span>
                </div>`;
            }).join("");
            return `<section class="ledger-day" id="ledger-day-${date}" data-day-section="${date}">
                <header class="ledger-day-head"><strong>${formatDay(date, { day: "2-digit" })}</strong><span>${formatDay(date, { weekday: "long" })}, ${formatDay(date, { month: "long" })} · ${escapeHtml(cityByDate[date])}</span><button class="collapse-button" type="button" data-collapse-day aria-expanded="true">Collapse</button></header>
                <div data-day-content>${rows || '<p class="ledger-empty">Nothing scheduled. Add a plan when you are ready.</p>'}${date === "2026-06-15" ? '<div class="ledger-open-slot"><strong>Evening open</strong><span>Nothing scheduled after 17:00</span></div>' : ""}</div>
            </section>`;
        }).join("");
        $("#concept-ledger").innerHTML = `<div class="ledger-shell">
            <aside class="ledger-rail"><h2>${escapeHtml(trip.title)}</h2><p>${escapeHtml(trip.range)} · ${items.filter((item) => !item.generated).length} items</p><nav class="day-rail-list" aria-label="Jump to day">${dayNav}</nav></aside>
            <div class="ledger-content"><header class="ledger-topline"><span class="ledger-status">Prototype session · Edits are not saved</span>${conceptHeaderActions("ledger-actions")}</header>${briefContent("ledger")}${days}</div>
        </div>`;
    }

    function renderAtlas() {
        const stops = trip.stops.map((stop, index) => `<div class="route-stop">${escapeHtml(stop)}<small>${index === 0 ? "Depart" : index === trip.stops.length - 1 ? "Return" : index === 1 ? "2 nights" : "1–2 nights"}</small></div>`).join("");
        const chapters = dayDates.map((date) => {
            const events = itemsForDay(date).map((item) => {
                const checkout = Boolean(item.checkout);
                const displayed = item;
                const transport = ["flight", "train", "car"].includes(displayed.type);
                return `<article class="atlas-event${transport ? " transport" : ""}${isCurrent(displayed) ? " is-current" : ""}" ${isCurrent(displayed) ? "data-current-event" : ""}>
                    <time class="atlas-time">${escapeHtml(displayed.time || "—")}</time><div><h3>${eventTitle(displayed)}</h3><p>${escapeHtml(itemMeta(displayed))}</p>${displayed.details ? `<p>${escapeHtml(displayed.details)}</p>` : ""}</div><span class="atlas-type">${checkout ? "Check out" : escapeHtml(typeLabels[displayed.type])}</span>
                </article>`;
            }).join("");
            return `<section class="atlas-chapter" data-day-section="${date}"><header class="atlas-date"><span>${isToday(date) ? "Today" : formatDay(date, { weekday: "long" })}</span><strong>${formatDay(date, { day: "numeric", month: "long" })}</strong><p>${escapeHtml(cityByDate[date])}</p><button class="collapse-button" type="button" data-collapse-day aria-expanded="true">Fold chapter</button></header><div class="atlas-events" data-day-content><div class="atlas-ribbon" aria-hidden="true"></div>${events}</div></section>`;
        }).join("");
        const current = items.find(isCurrent);
        $("#concept-atlas").innerHTML = `<div class="atlas-shell"><header class="atlas-header"><div><h2>Vienna to the North Sea—and back.</h2><p>${escapeHtml(trip.range)} · A six-day route through five transport legs, three stays, and one day shaped by the tide.</p></div>${conceptHeaderActions("atlas-actions")}</header><div class="atlas-now-mobile"><span>Now</span><div><strong>${escapeHtml(current.title)}</strong><small>${escapeHtml(timeRange(current))} · ${escapeHtml(itemMeta(current))}</small></div></div><div class="route-summary" aria-label="Route overview">${stops}</div>${chapters}</div>`;
    }

    function renderCanvas() {
        const heads = dayDates.map((date) => `<div class="canvas-day-head${isToday(date) ? " is-current" : ""}"><strong>${formatDay(date, { weekday: "short", day: "numeric" })}</strong><span>${escapeHtml(cityByDate[date])}</span></div>`).join("");
        const allDay = dayDates.map((date) => {
            const stay = items.find((item) => item.type === "lodging" && item.date === date && item.endDate);
            const spanDays = stay ? Math.max(1, dayDates.indexOf(stay.endDate) - dayDates.indexOf(stay.date)) : 0;
            return `<div class="canvas-allday-lane">${stay ? `<button class="stay-block event-trigger" type="button" style="right: calc(-${Math.max(0, spanDays - 1)} * (100% + 1px) + 7px)" data-edit-id="${stay.id}">${escapeHtml(stay.title)} · ${spanDays} nights</button>` : ""}</div>`;
        }).join("");
        const lanes = dayDates.map((date) => {
            const blocks = itemsForDay(date).filter((item) => item.type !== "lodging" || item.checkout).map((item) => {
                const [hour, minute] = (item.time || "12:00").split(":").map(Number);
                const startMinutes = hour * 60 + minute;
                const [endHour, endMinute] = (item.endTime || "").split(":").map(Number);
                const endMinutes = Number.isFinite(endHour) ? endHour * 60 + endMinute : startMinutes + 75;
                const top = Math.max(0, ((startMinutes - 420) / 900) * 100);
                const height = Math.max(5.5, ((endMinutes - startMinutes) / 900) * 100);
                const tag = item.checkout ? "div" : "button";
                const editAttr = item.checkout ? "" : ` data-edit-id="${item.id}"`;
                return `<${tag} class="canvas-event type-${item.type}${isCurrent(item) ? " is-current" : ""}"${tag === "button" ? ' type="button"' : ""} style="top:${top}%;height:${height}%"${editAttr} ${isCurrent(item) ? "data-current-event" : ""}><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(timeRange(item))} · ${escapeHtml(item.location)}</small></${tag}>`;
            }).join("");
            return `<div class="canvas-lane">${blocks}</div>`;
        }).join("");
        const hours = Array.from({ length: 16 }, (_, index) => `<span class="canvas-hour" style="top:${(index / 15) * 100}%">${String(index + 7).padStart(2, "0")}:00</span>`).join("");
        const nowMinutes = 12 * 60 + 20;
        const nowTop = ((nowMinutes - 420) / 900) * 100;
        $("#concept-canvas").innerHTML = `<div class="canvas-shell"><header class="canvas-header"><div><h2>${escapeHtml(trip.title)}</h2><p>${escapeHtml(trip.range)} · The shape of every day, from 07:00 to 22:00</p></div>${conceptHeaderActions("canvas-actions")}</header>${briefContent("canvas")}<div class="canvas-scroll" tabindex="0" aria-label="Horizontally scrollable six-day schedule"><div class="canvas-grid"><div class="canvas-days"><div class="canvas-corner"></div>${heads}</div><div class="canvas-allday"><div class="canvas-allday-label">STAYS</div>${allDay}</div><div class="canvas-timegrid"><div class="canvas-hours">${hours}</div>${lanes}<div class="canvas-now-line" style="top:${nowTop}%"></div></div></div></div></div>`;
    }

    function renderPocket() {
        const current = items.find(isCurrent);
        const next = sortedItems().find((item) => dateTime(item) > currentMillis());
        const days = dayDates.map((date) => {
            const events = itemsForDay(date).map((item) => {
                const checkout = Boolean(item.checkout);
                const displayed = item;
                return `<article class="pocket-event" ${isCurrent(displayed) ? "data-current-event" : ""}><time class="pocket-event-time">${escapeHtml(displayed.time || "—")}</time><div>${eventTitle(displayed)}<p>${escapeHtml(itemMeta(displayed) || displayed.details)}</p></div></article>`;
            }).join("");
            const expanded = isToday(date) || date === "2026-06-15";
            return `<section class="pocket-day${isToday(date) ? " is-current" : ""}" data-day-section="${date}"><button class="pocket-day-toggle" type="button" data-collapse-day aria-expanded="${expanded}" aria-controls="pocket-day-${date}"><span class="date-box"><span><strong>${formatDay(date, { day: "2-digit" })}</strong><small>${formatDay(date, { month: "short" })}</small></span></span><span><strong>${isToday(date) ? "Today · " : ""}${escapeHtml(cityByDate[date])}</strong><small>${actualItemsForDay(date).length} scheduled ${actualItemsForDay(date).length === 1 ? "item" : "items"}</small></span><svg class="chevron" aria-hidden="true" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></button><div id="pocket-day-${date}" class="pocket-day-events" data-day-content ${expanded ? "" : "hidden"}>${events}${date === "2026-06-15" ? '<div class="pocket-empty"><strong>Evening free</strong>Nothing scheduled after 17:00.</div>' : ""}</div></section>`;
        }).join("");
        $("#concept-pocket").innerHTML = `<div class="pocket-shell"><aside class="pocket-briefing"><div class="pocket-top"><div class="pocket-topline"><span>Sunday · 14 June</span><button class="icon-button" type="button" data-jump-now aria-label="Jump to current item">${icon("activity")}</button></div><h2>Good afternoon, ${escapeHtml(trip.traveler)}.</h2><p>You are leaving Hamburg for Ribe. Your next check-in is at 15:00.</p></div><article class="pocket-now"><div class="pocket-now-label"><span>Happening now</span><span>${escapeHtml(timeRange(current))}</span></div><h3>${eventTitle(current)}</h3><p>${escapeHtml(itemMeta(current))}</p></article><article class="pocket-next"><span>Next</span><div><strong>${escapeHtml(next.title)}</strong><small>${escapeHtml(next.time)} · ${escapeHtml(next.location)}</small></div></article></aside><div class="pocket-days"><header class="pocket-days-header"><h2>Your days</h2><button class="button-primary" type="button" data-add-item>Add item</button></header>${days}</div></div><button class="pocket-fab" type="button" data-add-item>+ Add plan</button>`;
    }

    function eventTitle(item) {
        if (item.generated) return `<span>${escapeHtml(item.title)}</span>`;
        return `<button class="event-trigger" type="button" data-edit-id="${escapeHtml(item.id)}">${escapeHtml(item.title)}</button>`;
    }

    function renderAll() {
        renderLedger();
        renderAtlas();
        renderCanvas();
        renderPocket();
        bindConceptInteractions();
    }

    function bindConceptInteractions() {
        $$('[data-day-jump]').forEach((button) => button.addEventListener("click", () => {
            const target = $(`#ledger-day-${button.dataset.dayJump}`);
            if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
        }));
        $$('[data-collapse-day]').forEach((button) => button.addEventListener("click", () => {
            const section = button.closest("[data-day-section]");
            const content = $("[data-day-content]", section);
            const expanded = button.getAttribute("aria-expanded") === "true";
            button.setAttribute("aria-expanded", String(!expanded));
            content.hidden = expanded;
            if (button.classList.contains("collapse-button")) button.textContent = expanded ? "Expand" : button.closest(".atlas-date") ? "Fold chapter" : "Collapse";
        }));
        $$('[data-jump-now]').forEach((button) => button.addEventListener("click", () => {
            const panel = $(`[data-concept="${activeConcept}"]`);
            const target = $("[data-current-event]", panel);
            if (target) {
                target.scrollIntoView({ behavior: "smooth", block: "center", inline: "center" });
                const focusTarget = target.matches("button") ? target : $("button", target);
                if (focusTarget) window.setTimeout(() => focusTarget.focus({ preventScroll: true }), 350);
                showToast("Current item: rental car to Ribe");
            }
        }));
        $$('[data-edit-id]').forEach((button) => button.addEventListener("click", () => openEditor(button.dataset.editId, button)));
        $$('[data-add-item]').forEach((button) => button.addEventListener("click", () => openEditor(null, button)));
    }

    function activateConcept(name, focusTab = false) {
        if (!$("#concept-" + name)) return;
        activeConcept = name;
        $$('[data-concept-tab]').forEach((tab) => {
            const selected = tab.dataset.conceptTab === name;
            tab.setAttribute("aria-selected", String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focusTab) tab.focus();
        });
        $$('[data-concept]').forEach((panel) => { panel.hidden = panel.dataset.concept !== name; });
        $("[data-editor]").dataset.theme = name;
        history.replaceState(null, "", `#${name}`);
        document.title = `Traveler — ${$("#tab-" + name + " span").textContent}`;
    }

    function openEditor(id, trigger) {
        const drawer = $("[data-editor]");
        const form = $("[data-editor-form]");
        const item = items.find((candidate) => candidate.id === id);
        lastFocusedElement = trigger || document.activeElement;
        form.reset();
        form.elements.id.value = item ? item.id : "";
        form.elements.title.value = item ? item.title : "";
        form.elements.type.value = item ? item.type : "activity";
        form.elements.date.value = item ? item.date : trip.currentTime.slice(0, 10);
        form.elements.date.min = dayDates[0];
        form.elements.date.max = dayDates[dayDates.length - 1];
        form.elements.time.value = item ? item.time || "" : "13:00";
        form.elements.endTime.value = item ? item.endTime || "" : "";
        form.elements.location.value = item ? item.location || "" : "";
        form.elements.endLocation.value = item ? item.endLocation || "" : "";
        form.elements.details.value = item ? item.details || "" : "";
        $("#editor-title").textContent = item ? "Edit itinerary item" : "Add itinerary item";
        $("[data-save-label]").textContent = item ? "Save changes" : "Add to trip";
        drawer.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        window.setTimeout(() => form.elements.title.focus(), 250);
    }

    function closeEditor(restoreFocus = true) {
        const drawer = $("[data-editor]");
        if (drawer.getAttribute("aria-hidden") === "true") return;
        drawer.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        if (restoreFocus) window.setTimeout(() => { if (lastFocusedElement && document.contains(lastFocusedElement)) lastFocusedElement.focus(); }, 220);
    }

    function showToast(message) {
        const toast = $("[data-toast]");
        toast.textContent = message;
        toast.classList.add("is-visible");
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 2600);
    }

    function init() {
        renderAll();
        $$('[data-concept-tab]').forEach((tab, index, tabs) => {
            tab.addEventListener("click", () => activateConcept(tab.dataset.conceptTab));
            tab.addEventListener("keydown", (event) => {
                let nextIndex = index;
                if (event.key === "ArrowRight") nextIndex = (index + 1) % tabs.length;
                else if (event.key === "ArrowLeft") nextIndex = (index - 1 + tabs.length) % tabs.length;
                else if (event.key === "Home") nextIndex = 0;
                else if (event.key === "End") nextIndex = tabs.length - 1;
                else return;
                event.preventDefault();
                activateConcept(tabs[nextIndex].dataset.conceptTab, true);
            });
        });
        $$('[data-editor-close]').forEach((button) => button.addEventListener("click", () => closeEditor()));
        $("[data-editor-form]").addEventListener("submit", (event) => {
            event.preventDefault();
            const formData = new FormData(event.currentTarget);
            const id = formData.get("id");
            const updated = {
                id: id || `prototype-${Date.now()}`,
                type: formData.get("type"), title: formData.get("title").trim(), date: formData.get("date"),
                time: formData.get("time"), endTime: formData.get("endTime"), location: formData.get("location").trim(),
                endLocation: formData.get("endLocation").trim(), details: formData.get("details").trim()
            };
            if (id) items = items.map((item) => item.id === id ? { ...item, ...updated } : item);
            else items.push(updated);
            closeEditor(false);
            renderAll();
            showToast(id ? "Item updated in all four concepts" : "Item added to all four concepts");
            window.setTimeout(() => {
                const panel = $(`[data-concept="${activeConcept}"]`);
                const updatedTrigger = $(`[data-edit-id="${updated.id}"]`, panel);
                (updatedTrigger || $(`[data-concept-tab="${activeConcept}"]`)).focus({ preventScroll: true });
            }, 240);
        });
        document.addEventListener("keydown", (event) => {
            const drawer = $("[data-editor]");
            if (drawer.getAttribute("aria-hidden") === "false" && event.key === "Escape") closeEditor();
            if (drawer.getAttribute("aria-hidden") === "false" && event.key === "Tab") {
                const focusable = $$('button:not([disabled]), input, select, textarea', $(".editor-sheet"));
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        const requested = location.hash.replace("#", "");
        activateConcept(["ledger", "atlas", "canvas", "pocket"].includes(requested) ? requested : "ledger");
    }

    document.addEventListener("DOMContentLoaded", init);
}());
