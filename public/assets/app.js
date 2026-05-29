const TOKEN_KEY = "qa_task_token";
const PATH_NOTES = "/account/notes";
const PATH_PROFILE = "/account/profile";

const authSection = document.getElementById("auth-section");
const accountSection = document.getElementById("account-section");
const notesView = document.getElementById("notes-view");
const profileView = document.getElementById("profile-view");
const accountTitle = document.getElementById("account-title");
const notesListEl = document.getElementById("notes-list");
const statusEl = document.getElementById("status");
const profileEmailEl = document.getElementById("profile-email");
const profileIdEl = document.getElementById("profile-id");
const searchQueryEl = document.getElementById("notes-search-query");
const searchFieldEl = document.getElementById("notes-search-field");
const searchSortEl = document.getElementById("notes-search-sort");
const pageSizeEl = document.getElementById("notes-page-size");
const prevPageEl = document.getElementById("notes-prev-page");
const nextPageEl = document.getElementById("notes-next-page");
const pageInfoEl = document.getElementById("notes-page-info");
const listTotalEl = document.getElementById("notes-list-total");
const signupFormEl = document.getElementById("signup-form");
const signinFormEl = document.getElementById("signin-form");
const createNoteFormEl = document.getElementById("create-note-form");
const logoutButtonEl = document.getElementById("logout-button");
const navNotesButtonEl = document.getElementById("nav-notes-button");
const navProfileButtonEl = document.getElementById("nav-profile-button");
const searchNotesFormEl = document.getElementById("search-notes-form");

const appDomReady = [
    authSection,
    accountSection,
    notesView,
    profileView,
    accountTitle,
    notesListEl,
    statusEl,
    profileEmailEl,
    profileIdEl,
    searchQueryEl,
    searchFieldEl,
    searchSortEl,
    pageSizeEl,
    prevPageEl,
    nextPageEl,
    pageInfoEl,
    listTotalEl,
    signupFormEl,
    signinFormEl,
    createNoteFormEl,
    logoutButtonEl,
    navNotesButtonEl,
    navProfileButtonEl,
    searchNotesFormEl,
].every(Boolean);

let notesCache = [];
let accountProfile = null;
let currentPage = 1;
let totalItems = 0;
let totalPages = 1;
let hasNextPage = false;
let hasPrevPage = false;

function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

function setStatus(message, isError = false, allowHtml = false) {
    if (allowHtml) {
        statusEl.innerHTML = message;
    } else {
        statusEl.textContent = message;
    }

    statusEl.classList.remove("status--ok", "status--error");
    if (message) {
        statusEl.classList.add(isError ? "status--error" : "status--ok");
    }
}

function currentPath() {
    const path = window.location.pathname.replace(/\/+$/, "");
    return path === "" ? "/" : path;
}

function navigateTo(path, replace = false) {
    if (replace) {
        window.history.replaceState({}, "", path);
    } else if (currentPath() !== path) {
        window.history.pushState({}, "", path);
    }
}

function clearEmailConfirmationParamsFromUrl() {
    const url = new URL(window.location.href);
    url.searchParams.delete("confirm_email");
    url.searchParams.delete("confirm_code");
    const query = url.searchParams.toString();
    const nextUrl = `${url.pathname}${query ? `?${query}` : ""}${url.hash}`;
    window.history.replaceState({}, "", nextUrl);
}

async function api(path, options = {}) {
    const headers = options.headers ? {...options.headers} : {};
    if (!("Accept" in headers) && !("accept" in headers)) {
        headers["Accept"] = "application/json";
    }
    if (!(options.body instanceof FormData)) {
        headers["Content-Type"] = "application/json";
    }

    const token = getToken();
    if (token) {
        headers["Authorization"] = `Bearer ${token}`;
    }

    const response = await fetch(path, {...options, headers});
    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));

        if (response.status >= 500) {
            throw new Error("500");
        }

        const validationMessage = extractValidationMessage(payload);
        if (validationMessage) {
            throw new Error(validationMessage);
        }

        throw new Error(
            payload.error
            || payload.message
            || payload.detail
            || payload["hydra:description"]
            || `Request failed: ${response.status}`,
        );
    }

    const contentType = response.headers.get("Content-Type") || "";
    if (!contentType.includes("application/json")) {
        return response;
    }

    return response.json();
}

function extractValidationMessage(payload) {
    if (!payload || typeof payload !== "object") {
        return "";
    }

    const violations = Array.isArray(payload.violations) ? payload.violations : [];
    if (violations.length > 0) {
        return violations
            .map((violation) => {
                const field = typeof violation?.propertyPath === "string" ? violation.propertyPath.trim() : "";
                const message = typeof violation?.message === "string" ? violation.message.trim() : "";
                if (!message) {
                    return "";
                }

                return field ? `${field}: ${message}` : message;
            })
            .filter(Boolean)
            .join("; ");
    }

    const hydraDescription = typeof payload["hydra:description"] === "string"
        ? payload["hydra:description"].trim()
        : "";

    if (
        hydraDescription
        && (
            payload["hydra:title"] === "An error occurred"
            || hydraDescription.toLowerCase().includes("validation")
        )
    ) {
        return hydraDescription;
    }

    return "";
}

function showAuthUI() {
    accountSection.classList.add("hidden");
    authSection.classList.remove("hidden");
}

function setActiveNav(path) {
    const isProfile = path === PATH_PROFILE;
    navNotesButtonEl.classList.toggle("active-nav", !isProfile);
    navProfileButtonEl.classList.toggle("active-nav", isProfile);
}

function showAccountView(path) {
    authSection.classList.add("hidden");
    accountSection.classList.remove("hidden");

    const isProfile = path === PATH_PROFILE;
    notesView.classList.toggle("hidden", isProfile);
    profileView.classList.toggle("hidden", !isProfile);
    accountTitle.textContent = isProfile ? "Profile" : "Notes";
    setActiveNav(path);
}

function escapeHtml(value) {
    return value
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function buildModalShell(titleText) {
    const backdrop = document.createElement("div");
    backdrop.className = "modal-backdrop";

    const dialog = document.createElement("div");
    dialog.className = "modal-dialog panel";
    dialog.setAttribute("role", "dialog");
    dialog.setAttribute("aria-modal", "true");

    const title = document.createElement("h3");
    title.className = "modal-title";
    title.textContent = titleText;

    const content = document.createElement("div");
    content.className = "modal-content";

    dialog.appendChild(title);
    dialog.appendChild(content);
    backdrop.appendChild(dialog);

    return {backdrop, content};
}

function openUpdateNoteModal(note) {
    return new Promise((resolve) => {
        const {backdrop, content} = buildModalShell("Update Note");

        const form = document.createElement("form");
        form.className = "modal-form";
        form.innerHTML = `
            <label>Title <input type="text" name="title" required></label>
            <label>Content <textarea name="content" rows="8" required></textarea></label>
            <div class="modal-actions">
                <button type="button" class="secondary" data-action="cancel">Cancel</button>
                <button type="submit">Save</button>
            </div>
        `;

        const titleInput = form.querySelector('input[name="title"]');
        const contentInput = form.querySelector('textarea[name="content"]');
        titleInput.value = note.title || "";
        contentInput.value = note.content || "";

        const close = (result) => {
            backdrop.remove();
            resolve(result);
        };

        form.addEventListener("submit", (event) => {
            event.preventDefault();
            close({
                title: titleInput.value.trim(),
                content: contentInput.value,
            });
        });

        form.querySelector('[data-action="cancel"]').addEventListener("click", () => close(null));
        backdrop.addEventListener("click", (event) => {
            if (event.target === backdrop) {
                close(null);
            }
        });

        content.appendChild(form);
        document.body.appendChild(backdrop);
        titleInput.focus();
    });
}

function openDeleteNoteModal(noteTitle) {
    return new Promise((resolve) => {
        const {backdrop, content} = buildModalShell("Delete Note");

        const body = document.createElement("div");
        body.className = "modal-form";
        body.innerHTML = `
            <p class="modal-message">Delete note "${escapeHtml(noteTitle || "")}"?</p>
            <div class="modal-actions">
                <button type="button" class="secondary" data-action="cancel">Cancel</button>
                <button type="button" class="danger" data-action="delete">Delete</button>
            </div>
        `;

        const close = (result) => {
            backdrop.remove();
            resolve(result);
        };

        body.querySelector('[data-action="cancel"]').addEventListener("click", () => close(false));
        body.querySelector('[data-action="delete"]').addEventListener("click", () => close(true));
        backdrop.addEventListener("click", (event) => {
            if (event.target === backdrop) {
                close(false);
            }
        });

        content.appendChild(body);
        document.body.appendChild(backdrop);
    });
}

function buildNotesSearchParams() {
    const params = new URLSearchParams();
    const query = searchQueryEl.value.trim();
    const field = searchFieldEl.value;
    const sort = searchSortEl.value;

    if (query) {
        if (field === "title") {
            params.set("title", query);
        } else if (field === "content") {
            params.set("content", query);
        } else {
            params.set("q", query);
        }
    }

    if (sort === "updated_desc") {
        params.set("sort[updatedAt]", "desc");
    } else if (sort === "updated_asc") {
        params.set("sort[updatedAt]", "asc");
    } else if (sort === "title_asc") {
        params.set("sort[title]", "asc");
    } else if (sort === "title_desc") {
        params.set("sort[title]", "desc");
    }

    params.set("page", String(currentPage));
    params.set("itemsPerPage", pageSizeEl.value);

    return params;
}

function renderNotes() {
    listTotalEl.textContent = `${totalItems} note${totalItems === 1 ? "" : "s"}`;
    pageInfoEl.textContent = Number.isFinite(totalPages) ? `Page ${currentPage} / ${totalPages}` : `Page ${currentPage}`;
    prevPageEl.disabled = !hasPrevPage;
    nextPageEl.disabled = !hasNextPage;

    notesListEl.innerHTML = "";

    if (totalItems === 0) {
        notesListEl.innerHTML = `<div class="panel">No notes found.</div>`;
        return;
    }

    for (const note of notesCache) {
        const wrapper = document.createElement("article");
        wrapper.className = "panel note-item";
        wrapper.innerHTML = `
            <h3>${escapeHtml(note.title || "")}</h3>
            <p>${escapeHtml(note.content || "")}</p>
            <small>Updated: ${note.updated_at || "-"}</small>
            <div class="note-actions">
                <button data-action="edit">Update</button>
                <button class="danger" data-action="delete">Delete</button>
            </div>
        `;

        wrapper.querySelector('[data-action="edit"]').addEventListener("click", async () => {
            const updates = await openUpdateNoteModal(note);
            if (!updates) {
                return;
            }

            try {
                await api(`/api/notes/${note.id}`, {
                    method: "PUT",
                    body: JSON.stringify({title: updates.title, content: updates.content}),
                });
                setStatus("Note updated.");
                await refreshNotes();
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        wrapper.querySelector('[data-action="delete"]').addEventListener("click", async () => {
            const confirmed = await openDeleteNoteModal(note.title || "");
            if (!confirmed) {
                return;
            }

            try {
                await api(`/api/notes/${note.id}`, {method: "DELETE"});
                setStatus("Note deleted.");
                await refreshNotes();
            } catch (error) {
                setStatus(error.message, true);
            }
        });

        notesListEl.appendChild(wrapper);
    }
}

function setProfile(profile) {
    accountProfile = profile;
    profileEmailEl.textContent = profile?.email ?? "-";
    profileIdEl.textContent = profile?.id ?? "-";
}

async function refreshNotes() {
    const params = buildNotesSearchParams();
    const notesPayload = await api(`/api/notes?${params.toString()}`);
    hasNextPage = false;
    hasPrevPage = currentPage > 1;

    if (Array.isArray(notesPayload)) {
        notesCache = notesPayload;
        totalItems = notesPayload.length;
        hasNextPage = notesCache.length >= (Number.parseInt(pageSizeEl.value, 10) || 10);
    } else if (notesPayload && typeof notesPayload === "object") {
        if (Array.isArray(notesPayload["hydra:member"])) {
            notesCache = notesPayload["hydra:member"];
        } else if (Array.isArray(notesPayload.member)) {
            notesCache = notesPayload.member;
        } else {
            notesCache = [];
        }

        const hydraTotal = Number.parseInt(String(notesPayload["hydra:totalItems"] ?? ""), 10);
        const plainTotal = Number.parseInt(String(notesPayload.totalItems ?? ""), 10);

        if (Number.isFinite(hydraTotal) && hydraTotal >= 0) {
            totalItems = hydraTotal;
        } else if (Number.isFinite(plainTotal) && plainTotal >= 0) {
            totalItems = plainTotal;
        } else {
            totalItems = notesCache.length;
        }

        const view = notesPayload["hydra:view"] && typeof notesPayload["hydra:view"] === "object"
            ? notesPayload["hydra:view"]
            : null;
        if (view) {
            hasNextPage = typeof view["hydra:next"] === "string" && view["hydra:next"] !== "";
            hasPrevPage = typeof view["hydra:previous"] === "string" && view["hydra:previous"] !== "";
        } else {
            hasNextPage = notesCache.length >= (Number.parseInt(pageSizeEl.value, 10) || 10);
        }
    } else {
        notesCache = [];
        totalItems = 0;
        hasNextPage = false;
        hasPrevPage = currentPage > 1;
    }

    const pageSize = Number.parseInt(pageSizeEl.value, 10) || 10;
    if (totalItems > notesCache.length || (!hasNextPage && !hasPrevPage)) {
        totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    } else if (hasNextPage) {
        totalPages = Number.POSITIVE_INFINITY;
    } else {
        totalPages = currentPage;
    }
    currentPage = Math.min(Math.max(1, currentPage), totalPages);

    renderNotes();
}

async function refreshProfile() {
    const me = await api("/api/auth/me");
    setProfile(me);
}

async function ensureAuthenticated() {
    await refreshProfile();
}

async function enterNotesAfterAuth(successMessage) {
    navigateTo(PATH_NOTES);
    showAccountView(PATH_NOTES);

    try {
        await refreshProfile();
        await refreshNotes();
        setStatus(successMessage);
    } catch (error) {
        const message = error instanceof Error ? error.message : "Failed to load account data.";
        setStatus(message, true);
    }
}

async function tryConfirmFromLink() {
    const url = new URL(window.location.href);
    const email = (url.searchParams.get("confirm_email") || "").trim();
    const code = (url.searchParams.get("confirm_code") || "").trim();

    if (!email || !code) {
        return false;
    }

    try {
        const result = await api("/api/auth/confirm", {
            method: "POST",
            body: JSON.stringify({email, code}),
        });
        setToken(result.token);
        clearEmailConfirmationParamsFromUrl();
        await enterNotesAfterAuth("Account confirmed.");
    } catch (error) {
        clearEmailConfirmationParamsFromUrl();
        showAuthUI();
        setStatus(error.message, true);
    }

    return true;
}

async function renderRoute() {
    const linkConfirmed = await tryConfirmFromLink();
    if (linkConfirmed) {
        return;
    }

    const token = getToken();
    const path = currentPath();
    const accountPath = path === PATH_PROFILE ? PATH_PROFILE : PATH_NOTES;

    if (!token) {
        showAuthUI();
        return;
    }

    try {
        await ensureAuthenticated();
        showAccountView(accountPath);
        if (accountPath === PATH_NOTES) {
            await refreshNotes();
        }
    } catch {
        clearToken();
        showAuthUI();
        setProfile(null);
    }
}

if (!appDomReady) {
    console.warn("App UI is not mounted on this page; skipping app.js bootstrap.");
} else {
signupFormEl.addEventListener("submit", async (event) => {
    event.preventDefault();
    const email = document.getElementById("signup-email").value;
    const password = document.getElementById("signup-password").value;

    try {
        await api("/api/auth/signup", {
            method: "POST",
            body: JSON.stringify({email, password}),
        });
        const safeEmail = escapeHtml(email);
        const mailhogUrl = "http://localhost:8025";
        setStatus(
            `Sign up request sent to ${safeEmail}. Check your inbox and MailHog at <a href="${mailhogUrl}" target="_blank" rel="noopener noreferrer">${mailhogUrl}</a>.`,
            false,
            true,
        );
    } catch (error) {
        setStatus(error.message, true);
    }
});

signinFormEl.addEventListener("submit", async (event) => {
    event.preventDefault();
    const email = document.getElementById("signin-email").value;
    const password = document.getElementById("signin-password").value;

    try {
        const result = await api("/api/auth/signin", {
            method: "POST",
            body: JSON.stringify({email, password}),
        });
        setToken(result.token);
        await enterNotesAfterAuth("Signed in.");
    } catch (error) {
        setStatus(error.message, true);
    }
});

createNoteFormEl.addEventListener("submit", async (event) => {
    event.preventDefault();
    const title = document.getElementById("note-title").value;
    const content = document.getElementById("note-content").value;

    try {
        await api("/api/notes", {
            method: "POST",
            body: JSON.stringify({title, content}),
        });
        event.target.reset();
        await refreshNotes();
        setStatus("Note created.");
    } catch (error) {
        setStatus(error.message, true);
    }
});

logoutButtonEl.addEventListener("click", () => {
    clearToken();
    notesCache = [];
    totalItems = 0;
    totalPages = 1;
    hasNextPage = false;
    hasPrevPage = false;
    currentPage = 1;
    notesListEl.innerHTML = "";
    setProfile(null);
    navigateTo("/");
    showAuthUI();
    setStatus("Logged out.");
});

navNotesButtonEl.addEventListener("click", async () => {
    navigateTo(PATH_NOTES);
    showAccountView(PATH_NOTES);
    await refreshNotes();
});

navProfileButtonEl.addEventListener("click", async () => {
    navigateTo(PATH_PROFILE);
    showAccountView(PATH_PROFILE);
    await refreshProfile();
});

searchNotesFormEl.addEventListener("submit", (event) => {
    event.preventDefault();
    currentPage = 1;
    refreshNotes().catch((error) => setStatus(error.message, true));
});

searchQueryEl.addEventListener("input", () => {
    currentPage = 1;
    refreshNotes().catch((error) => setStatus(error.message, true));
});
searchFieldEl.addEventListener("change", () => {
    currentPage = 1;
    refreshNotes().catch((error) => setStatus(error.message, true));
});
searchSortEl.addEventListener("change", () => {
    currentPage = 1;
    refreshNotes().catch((error) => setStatus(error.message, true));
});
pageSizeEl.addEventListener("change", () => {
    currentPage = 1;
    refreshNotes().catch((error) => setStatus(error.message, true));
});
prevPageEl.addEventListener("click", () => {
    if (currentPage > 1) {
        currentPage -= 1;
        refreshNotes().catch((error) => setStatus(error.message, true));
    }
});
nextPageEl.addEventListener("click", () => {
    if (hasNextPage || currentPage < totalPages) {
        currentPage += 1;
        refreshNotes().catch((error) => setStatus(error.message, true));
    }
});
window.addEventListener("popstate", () => {
    renderRoute();
});

renderRoute();
}
