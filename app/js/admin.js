/*
 * Admin dashboard
 *
 * Modeled on contacts.js. Admins can:
 *   - search users (with pagination)
 *   - change a user's password
 *   - disable / enable a user
 *   - create new admins
 * Admins cannot create regular users.
 */


/*
 * API endpoints (match index.php)
 */

const ADMIN_ENDPOINTS = {
    // POST, body: { firstname, lastname, username, password }
    createAdmin: "/api/admin/signup",

    // POST ?page=N, body: { username } (prefix match, "" = everyone)
    searchUsers: "/api/admin/search",

    // GET, returns one user and their contacts
    getUser: function (id) {
        return `/api/admin/search/${encodeURIComponent(id)}`;
    },

    // POST, body: { username, password }
    setPassword: "/api/admin/set-password",

    // PUT, no body
    disableUser: function (id) {
        return `/api/admin/disable/${encodeURIComponent(id)}`;
    },

    // PUT, no body
    enableUser: function (id) {
        return `/api/admin/enable/${encodeURIComponent(id)}`;
    }
};


/*
 * Page guard
 *
 * Only admins should see this page. login.js saves the role.
 * This is a convenience redirect only; the backend must reject
 * /api/admin/* requests from non-admin sessions.
 */

const isAdminSession =
    localStorage.getItem("role") === "admin";

if (!localStorage.getItem("username")) {

    window.location.replace("login.html");

} else if (!isAdminSession) {

    window.location.replace("contacts.html");
}


const searchInput =
    document.getElementById("searchInput");

const searchButton =
    document.getElementById("searchButton");

const usersTableBody =
    document.getElementById("usersTableBody");

const usersTable =
    document.querySelector(".users-table");

const resultsSummary =
    document.getElementById("resultsSummary");

const paginationNav =
    document.getElementById("pagination");

const pageMessage =
    document.getElementById("pageMessage");


// Create New Admin modal

const adminModal =
    document.getElementById("adminModal");

const adminForm =
    document.getElementById("adminForm");

const adminModalMessage =
    document.getElementById("adminModalMessage");

const saveAdminButton =
    document.getElementById("saveAdminButton");

const adminFirstName =
    document.getElementById("adminFirstName");

const adminLastName =
    document.getElementById("adminLastName");

const adminUsername =
    document.getElementById("adminUsername");

const adminPassword =
    document.getElementById("adminPassword");

const adminConfirmPassword =
    document.getElementById("adminConfirmPassword");


// Change Password modal

const passwordModal =
    document.getElementById("passwordModal");

const passwordForm =
    document.getElementById("passwordForm");

const passwordModalMessage =
    document.getElementById("passwordModalMessage");

const passwordModalUser =
    document.getElementById("passwordModalUser");

const passwordUsername =
    document.getElementById("passwordUsername");

const savePasswordButton =
    document.getElementById("savePasswordButton");

const resetPassword =
    document.getElementById("resetPassword");

const resetConfirmPassword =
    document.getElementById("resetConfirmPassword");


// User details modal

const userModal =
    document.getElementById("userModal");

const userModalMessage =
    document.getElementById("userModalMessage");

const userDetails =
    document.getElementById("userDetails");

const userContactsBody =
    document.getElementById("userContactsBody");

const userContactsCount =
    document.getElementById("userContactsCount");

const userContactsSection =
    document.getElementById("userContactsSection");

let latestUserRequestId = 0;


const MIN_PASSWORD_LENGTH = 8;

const USERS_PER_PAGE = 10;

const savedUsername =
    localStorage.getItem("username");


/*
 * Search / pagination state
 *
 * query is the term from the last *submitted* search, not whatever is
 * currently typed in the box, so clicking page 2 keeps the same results.
 */

const searchState = {
    query: "",
    page: 1
};

let latestSearchRequestId = 0;


/*
 * Display logged-in username
 */

if (savedUsername) {
    document.getElementById(
        "currentUsername"
    ).textContent = savedUsername;
}


/*
 * Open Create New Admin modal
 */

document
    .getElementById("openCreateAdminButton")
    .addEventListener("click", function () {

        adminForm.reset();

        clearFieldErrors(adminForm);

        showMessage("", "", adminModalMessage);

        openModal(adminModal, adminFirstName);
    });


/*
 * Close modals (× and Cancel buttons, and Escape)
 */

document
    .querySelectorAll("[data-close-modal]")
    .forEach(function (button) {

        button.addEventListener(
            "click",
            function () {
                closeModal(
                    button.closest(".modal")
                );
            }
        );
    });

document.addEventListener(
    "keydown",
    function (event) {

        if (event.key !== "Escape") {
            return;
        }

        [adminModal, passwordModal, userModal].forEach(function (modal) {

            if (!modal.classList.contains("hidden")) {
                closeModal(modal);
            }
        });
    }
);


/*
 * Search button — a new search always starts at page 1
 */

searchButton.addEventListener(
    "click",
    runNewSearch
);


/*
 * Allow Enter to search
 */

searchInput.addEventListener(
    "keydown",
    function (event) {

        if (event.key === "Enter") {
            event.preventDefault();
            runNewSearch();
        }
    }
);


/*
 * Create new admin
 */

adminForm.addEventListener(
    "submit",
    async function (event) {

        event.preventDefault();

        clearFieldErrors(adminForm);


        const adminData = {
            firstname:
                adminFirstName.value.trim(),

            lastname:
                adminLastName.value.trim(),

            username:
                adminUsername.value.trim(),

            // Passwords are sent as typed; spaces can be intentional.
            password:
                adminPassword.value
        };


        const validationError =
            validateNewAdmin(adminData);

        if (validationError) {

            showMessage(
                validationError.message,
                "error",
                adminModalMessage
            );

            markFieldInvalid(
                validationError.field
            );

            return;
        }


        setButtonBusy(saveAdminButton, true, "Creating...", "Create Admin");

        showMessage("", "", adminModalMessage);


        try {

            const data =
                await apiRequest(
                    ADMIN_ENDPOINTS.createAdmin,
                    {
                        method: "POST",

                        body:
                            JSON.stringify(
                                adminData
                            )
                    }
                );

            throwIfFailed(data, "Admin could not be created.");


            closeModal(adminModal);

            showMessage(
                "Admin created successfully.",
                "success"
            );

            await searchUsers(searchState.page, { keepMessage: true });


        } catch (error) {

            showMessage(
                error.message,
                "error",
                adminModalMessage
            );

            // Never keep a rejected password in the field.
            adminPassword.value = "";
            adminConfirmPassword.value = "";

        } finally {

            setButtonBusy(saveAdminButton, false, "Creating...", "Create Admin");
        }
    }
);


/*
 * Change a user's password
 */

passwordForm.addEventListener(
    "submit",
    async function (event) {

        event.preventDefault();

        clearFieldErrors(passwordForm);


        const username =
            passwordUsername.value;

        const validationError =
            validatePassword(
                resetPassword,
                resetConfirmPassword
            );

        if (validationError) {

            showMessage(
                validationError.message,
                "error",
                passwordModalMessage
            );

            markFieldInvalid(
                validationError.field
            );

            return;
        }


        setButtonBusy(savePasswordButton, true, "Saving...", "Change Password");

        showMessage("", "", passwordModalMessage);


        try {

            const data =
                await apiRequest(
                    ADMIN_ENDPOINTS.setPassword,
                    {
                        method: "POST",

                        body:
                            JSON.stringify({
                                username: username,
                                password: resetPassword.value
                            })
                    }
                );

            throwIfFailed(data, "Password could not be changed.");


            closeModal(passwordModal);

            showMessage(
                `Password changed for ${username}.`,
                "success"
            );


        } catch (error) {

            showMessage(
                error.message,
                "error",
                passwordModalMessage
            );

            resetPassword.value = "";
            resetConfirmPassword.value = "";

        } finally {

            setButtonBusy(savePasswordButton, false, "Saving...", "Change Password");
        }
    }
);


/*
 * Start a new search from the search box
 */

function runNewSearch() {

    searchState.query =
        searchInput.value.trim();

    return searchUsers(1);
}


/*
 * Search users (one page)
 *
 * page:    1-based page number to load
 * options: { keepMessage } — keep the current success message
 */

async function searchUsers(page, options = {}) {

    page = Math.max(
        1,
        parseInt(page, 10) || 1
    );

    const requestId =
        ++latestSearchRequestId;

    setSearchLoading(true);

    if (!options.keepMessage) {
        showMessage("", "");
    }


    try {

        /*
         * The backend route is POST /api/admin/search?page=N
         * with the search term in the JSON body as "username".
         * It matches usernames that START with the term;
         * an empty term returns everyone.
         */

        const data =
            await apiRequest(
                `${ADMIN_ENDPOINTS.searchUsers}?page=${encodeURIComponent(page)}`,
                {
                    method: "POST",

                    body:
                        JSON.stringify({
                            username: searchState.query
                        })
                }
            );


        // A newer search or page click started while this one was in flight.
        if (requestId !== latestSearchRequestId) {
            return;
        }

        throwIfFailed(data, "Users could not be loaded.");


        const users =
            Array.isArray(data.data)
                ? data.data.map(normalizeUser)
                : [];

        const meta =
            normalizeMeta(
                data.meta,
                page,
                users.length
            );


        // Requested page no longer has rows; step back. Bounded, cannot loop.
        if (
            users.length === 0 &&
            meta.total > 0 &&
            meta.current_page > 1
        ) {
            return searchUsers(
                Math.min(
                    meta.current_page - 1,
                    Math.max(1, meta.total_pages)
                ),
                options
            );
        }


        searchState.page =
            meta.current_page;

        renderUsers(users);

        renderResultsSummary(
            meta,
            users.length
        );

        Pagination.render(
            paginationNav,
            users.length ? meta : { total_pages: 0 },
            {
                onPageChange: searchUsers,
                maxButtons: isNarrowScreen() ? 7 : 10
            }
        );


    } catch (error) {

        if (requestId !== latestSearchRequestId) {
            return;
        }

        showMessage(
            error.message,
            "error"
        );

        renderUsers([], "Users could not be loaded.");

        renderResultsSummary(
            { total: 0 },
            0
        );

        Pagination.render(
            paginationNav,
            { total_pages: 0 }
        );

    } finally {

        if (requestId === latestSearchRequestId) {
            setSearchLoading(false);
        }
    }
}


/*
 * Disable or enable a user
 */

async function setUserActive(user, makeActive) {

    const name =
        user.username || "this user";

    const confirmed =
        window.confirm(
            makeActive
                ? `Enable ${name}? They will be able to sign in again.`
                : `Disable ${name}? They will not be able to sign in until re-enabled.`
        );

    if (!confirmed) {
        return;
    }


    try {

        const data =
            await apiRequest(
                makeActive
                    ? ADMIN_ENDPOINTS.enableUser(user.id)
                    : ADMIN_ENDPOINTS.disableUser(user.id),
                {
                    method: "PUT"
                }
            );

        throwIfFailed(
            data,
            makeActive
                ? "User could not be enabled."
                : "User could not be disabled."
        );


        showMessage(
            makeActive
                ? `${name} has been enabled.`
                : `${name} has been disabled.`,
            "success"
        );

        await searchUsers(searchState.page, { keepMessage: true });


    } catch (error) {

        showMessage(
            error.message,
            "error"
        );

        // The row may be out of date (e.g. the user was already
        // disabled), so reload the page to show the real status.
        await searchUsers(searchState.page, { keepMessage: true });
    }
}


/*
 * Open the details modal for one user.
 * Loads fresh data from GET /api/admin/search/{id}.
 */

async function openUserModal(user) {

    const requestId =
        ++latestUserRequestId;

    showMessage("", "", userModalMessage);

    // Show what we already know while the full record loads.
    renderUserDetails(user);

    // Admins can't have contacts, so only users get the contacts section.
    const isAdminAccount =
        getUserRole(user) === "admin";

    userContactsSection.hidden =
        isAdminAccount;

    if (!isAdminAccount) {
        renderUserContacts(null);
    }

    openModal(userModal, userModal.querySelector(".close-button"));


    try {

        const data =
            await apiRequest(
                ADMIN_ENDPOINTS.getUser(user.id)
            );

        // Another user was opened while this one was loading.
        if (requestId !== latestUserRequestId) {
            return;
        }

        throwIfFailed(data, "User could not be loaded.");

        const fullUser =
            normalizeUser(data.data || {});

        renderUserDetails(fullUser);

        userContactsSection.hidden =
            getUserRole(fullUser) === "admin";

        renderUserContacts(fullUser.contacts);


    } catch (error) {

        if (requestId !== latestUserRequestId) {
            return;
        }

        showMessage(
            error.message,
            "error",
            userModalMessage
        );

        renderUserContacts([], "Contacts could not be loaded.");
    }
}


function renderUserDetails(user) {

    const role =
        getUserRole(user);

    const active =
        isUserActive(user);

    const fullName =
        `${user.firstname || ""} ${user.lastname || ""}`.trim();

    userDetails.innerHTML = `
        <div class="detail-item">
            <span class="detail-label">Name</span>
            <span>${escapeHtml(fullName || "—")}</span>
        </div>

        <div class="detail-item">
            <span class="detail-label">Username</span>
            <span>${escapeHtml(user.username || "—")}</span>
        </div>

        <div class="detail-item">
            <span class="detail-label">Role</span>
            <span class="role-badge role-${role}">
                ${role === "admin" ? "Admin" : "User"}
            </span>
        </div>

        <div class="detail-item">
            <span class="detail-label">Status</span>
            <span class="status-badge ${active ? "status-active" : "status-disabled"}">
                ${active ? "Active" : "Inactive"}
            </span>
        </div>
    `;
}


/*
 * contacts: array to show, or null while loading
 */

function renderUserContacts(contacts, emptyText) {

    if (contacts === null) {

        userContactsCount.textContent = "";

        userContactsBody.innerHTML = `
            <tr>
                <td colspan="4" class="empty-message">
                    Loading contacts...
                </td>
            </tr>
        `;

        return;
    }


    userContactsCount.textContent =
        contacts.length
            ? `(${contacts.length})`
            : "";


    if (!contacts.length) {

        userContactsBody.innerHTML = `
            <tr>
                <td colspan="4" class="empty-message">
                    ${escapeHtml(emptyText || "This user has no contacts.")}
                </td>
            </tr>
        `;

        return;
    }


    userContactsBody.innerHTML =
        contacts.map(function (contact) {
            return `
                <tr>
                    <td>${escapeHtml(contact.firstname)}</td>
                    <td>${escapeHtml(contact.lastname)}</td>
                    <td>${escapeHtml(contact.email)}</td>
                    <td>${escapeHtml(contact.phone)}</td>
                </tr>
            `;
        }).join("");
}


/*
 * Open Change Password modal for one user
 */

function openPasswordModal(user) {

    passwordForm.reset();

    clearFieldErrors(passwordForm);

    showMessage("", "", passwordModalMessage);

    // set-password identifies the user by username, not id
    passwordUsername.value =
        user.username;

    passwordModalUser.textContent =
        user.username || "this user";

    openModal(passwordModal, resetPassword);
}


/*
 * Turns the API's meta block into readable numbers
 */

function normalizeMeta(meta, requestedPage, rowCount) {

    meta = meta || {};

    const perPage =
        parseInt(meta.per_page, 10) || USERS_PER_PAGE;

    const parsedTotal =
        parseInt(meta.total, 10);

    const total =
        Number.isFinite(parsedTotal)
            ? parsedTotal
            : rowCount;

    const totalPages =
        parseInt(meta.total_pages, 10) ||
        Math.ceil(total / perPage) ||
        0;

    return {
        current_page:
            parseInt(meta.current_page, 10) || requestedPage,
        per_page: perPage,
        total: total,
        total_pages: totalPages
    };
}


/*
 * "Showing 11–20 of 42"
 */

function renderResultsSummary(meta, rowCount) {

    if (!meta.total || !rowCount) {
        resultsSummary.textContent = "";
        return;
    }

    const first =
        (meta.current_page - 1) * meta.per_page + 1;

    const last =
        first + rowCount - 1;

    resultsSummary.textContent =
        `Showing ${first}–${last} of ${meta.total}`;
}


function setSearchLoading(isLoading) {

    searchButton.disabled =
        isLoading;

    usersTable.setAttribute(
        "aria-busy",
        isLoading ? "true" : "false"
    );

    Pagination.setDisabled(
        paginationNav,
        isLoading
    );
}


function isNarrowScreen() {

    return typeof window.matchMedia === "function" &&
        window.matchMedia("(max-width: 750px)").matches;
}


/*
 * Display users in table
 */

function renderUsers(users, emptyText) {

    usersTableBody.innerHTML = "";


    if (!users.length) {

        usersTableBody.innerHTML = `
            <tr>
                <td
                    colspan="7"
                    class="empty-message"
                >
                    ${escapeHtml(emptyText || "No users found.")}
                </td>
            </tr>
        `;

        return;
    }


    users.forEach(
        function (user) {

            const row =
                document.createElement("tr");

            const role =
                getUserRole(user);

            const active =
                isUserActive(user);

            // Actions are for regular users only, never admins or yourself.
            const canManage =
                role === "user" &&
                user.username !== savedUsername;


            if (!active) {
                row.classList.add("user-disabled");
            }


            row.innerHTML = `
                <td>
                    ${escapeHtml(
                        user.firstname || ""
                    )}
                </td>

                <td>
                    ${escapeHtml(
                        user.lastname || ""
                    )}
                </td>

                <td>
                    ${escapeHtml(
                        user.username || ""
                    )}
                </td>

                <td>
                    <span class="role-badge role-${role}">
                        ${role === "admin" ? "Admin" : "User"}
                    </span>
                </td>

                <td>
                    <span class="status-badge ${active ? "status-active" : "status-disabled"}">
                        ${active ? "Active" : "Inactive"}
                    </span>
                </td>

                <td class="contacts-count">
                    ${role === "admin" ? `<span class="no-actions">—</span>` : user.contacts.length}
                </td>

                <td class="action-buttons">
                    <button
                        type="button"
                        class="view-button"
                        data-action="view"
                    >
                        View
                    </button>

                    ${canManage ? `
                        <button
                            type="button"
                            class="edit-button"
                            data-action="password"
                        >
                            Change Password
                        </button>

                        <button
                            type="button"
                            class="${active ? "delete-button" : "enable-button"}"
                            data-action="toggle"
                        >
                            ${active ? "Disable" : "Enable"}
                        </button>
                    ` : ""}
                </td>
            `;


            row
                .querySelector('[data-action="view"]')
                .addEventListener(
                    "click",
                    function () {
                        openUserModal(user);
                    }
                );


            if (canManage) {

                row
                    .querySelector('[data-action="password"]')
                    .addEventListener(
                        "click",
                        function () {
                            openPasswordModal(user);
                        }
                    );

                row
                    .querySelector('[data-action="toggle"]')
                    .addEventListener(
                        "click",
                        function () {
                            setUserActive(user, !active);
                        }
                    );
            }


            usersTableBody.appendChild(
                row
            );
        }
    );
}


/*
 * Both admin search endpoints return user_* fields:
 *   POST /admin/search      -> user_enabled, user_admin
 *   GET  /admin/search/{id} -> user_active,  user_admin
 * Map them (and their contacts) to the names this file uses.
 */

function normalizeUser(raw) {

    raw = raw || {};

    return {
        id:
            raw.user_id ?? raw.id,

        firstname:
            raw.user_fname ?? raw.firstname ?? "",

        lastname:
            raw.user_lname ?? raw.lastname ?? "",

        username:
            raw.user_uname ?? raw.username ?? "",

        is_admin:
            raw.user_admin ?? raw.is_admin ?? raw.is_elevated,

        is_active:
            raw.user_enabled ?? raw.user_active ?? raw.is_active ?? raw.is_enabled,

        contacts:
            Array.isArray(raw.contacts)
                ? raw.contacts.map(normalizeContact)
                : []
    };
}


function normalizeContact(raw) {

    raw = raw || {};

    return {
        id:
            raw.contact_id ?? raw.id,

        firstname:
            raw.contact_fname ?? raw.firstname ?? "",

        lastname:
            raw.contact_lname ?? raw.lastname ?? "",

        email:
            raw.contact_email ?? raw.email ?? "",

        phone:
            raw.contact_phone ?? raw.phone ?? ""
    };
}


/*
 * Role and status from the normalized user
 */

function getUserRole(user) {

    if (isTruthyFlag(user.is_admin)) {
        return "admin";
    }

    return "user";
}


function isUserActive(user) {

    if (user.is_active !== undefined && user.is_active !== null) {
        return isTruthyFlag(user.is_active);
    }

    // No status field: assume active.
    return true;
}


function isTruthyFlag(value) {

    return value === true ||
        value === 1 ||
        value === "1" ||
        value === "true";
}


/*
 * Validation (the backend must repeat these checks)
 */

function validateNewAdmin(data) {

    if (!data.firstname) {
        return { field: adminFirstName, message: "First name is required." };
    }

    if (!data.lastname) {
        return { field: adminLastName, message: "Last name is required." };
    }

    if (!data.username) {
        return { field: adminUsername, message: "Username is required." };
    }

    if (/\s/.test(data.username)) {
        return { field: adminUsername, message: "Username cannot contain spaces." };
    }

    return validatePassword(
        adminPassword,
        adminConfirmPassword
    );
}


function validatePassword(passwordInput, confirmInput) {

    if (passwordInput.value.length < MIN_PASSWORD_LENGTH) {
        return {
            field: passwordInput,
            message: `Password must be at least ${MIN_PASSWORD_LENGTH} characters.`
        };
    }

    if (passwordInput.value !== confirmInput.value) {
        return { field: confirmInput, message: "Passwords do not match." };
    }

    return null;
}


function markFieldInvalid(input) {

    if (!input) {
        return;
    }

    input.setAttribute(
        "aria-invalid",
        "true"
    );

    input.focus();
}


function clearFieldErrors(form) {

    form
        .querySelectorAll("[aria-invalid]")
        .forEach(function (input) {
            input.removeAttribute("aria-invalid");
        });
}


/*
 * Shared helpers
 */

function throwIfFailed(data, fallbackMessage) {

    if (data && data.success === false) {
        throw new Error(
            data.message ||
            data.error ||
            data.reason ||
            fallbackMessage
        );
    }
}


function openModal(modal, focusTarget) {

    modal.classList.remove(
        "hidden"
    );

    if (focusTarget) {
        focusTarget.focus();
    }
}


function closeModal(modal) {

    if (!modal) {
        return;
    }

    modal.classList.add(
        "hidden"
    );

    // Don't leave passwords sitting in hidden fields.
    modal
        .querySelectorAll('input[type="password"]')
        .forEach(function (input) {
            input.value = "";
        });
}


function setButtonBusy(button, isBusy, busyText, idleText) {

    button.disabled =
        isBusy;

    button.textContent =
        isBusy
            ? busyText
            : idleText;
}


/*
 * Logout
 */

document
    .getElementById("logoutButton")
    .addEventListener(
        "click",
        async function () {

            try {

                await apiRequest(
                    "/api/logout",
                    {
                        method: "POST"
                    }
                );

            } catch (error) {

                console.error(
                    "Logout failed:",
                    error
                );

            } finally {

                sessionStorage.clear();
                localStorage.clear();

                window.location.href =
                    "login.html";
            }
        }
    );


/*
 * Show a status message. Defaults to the page message;
 * pass a modal's message element to show it inside the modal.
 */

function showMessage(text, type, target = pageMessage) {

    target.textContent =
        text;

    target.className =
        type
            ? `page-message ${type}`
            : "page-message";
}


/*
 * Prevent user data from being
 * interpreted as HTML.
 */

function escapeHtml(value) {

    const div =
        document.createElement("div");

    div.textContent = value;

    return div.innerHTML;
}


/*
 * Initial load: show the first page of users
 */

if (isAdminSession) {
    runNewSearch();
}