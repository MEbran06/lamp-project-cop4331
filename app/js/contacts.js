const searchInput =
    document.getElementById("searchInput");

const searchButton =
    document.getElementById("searchButton");

const contactsTableBody =
    document.getElementById("contactsTableBody");

const contactModal =
    document.getElementById("contactModal");

const contactForm =
    document.getElementById("contactForm");

const contactId =
    document.getElementById("contactId");

const firstName =
    document.getElementById("firstName");

const lastName =
    document.getElementById("lastName");

const email =
    document.getElementById("email");

const phone =
    document.getElementById("phone");

const modalTitle =
    document.getElementById("modalTitle");

const pageMessage =
    document.getElementById("pageMessage");

const contactsTable =
    document.querySelector(".contacts-table");

const resultsSummary =
    document.getElementById("resultsSummary");

const paginationNav =
    document.getElementById("pagination");

/*
 * Search / pagination state
 *
 * query is the term from the last *submitted* search, not whatever is
 * currently typed in the box, so clicking page 2 keeps the same results.
 */

const CONTACTS_PER_PAGE = 10;

const searchState = {
    query: "",
    page: 1
};

let latestSearchRequestId = 0;
/*
 * Display logged-in username
 */

const savedUsername =
    sessionStorage.getItem("username");

if (savedUsername) {
    document.getElementById(
        "currentUsername"
    ).textContent = savedUsername;
}


/*
 * Open Add Contact modal
 */

document
    .getElementById("openAddContactButton")
    .addEventListener("click", function () {

        clearContactForm();

        modalTitle.textContent =
            "Add Contact";

        contactModal.classList.remove(
            "hidden"
        );
    });


/*
 * Close modal
 */

document
    .getElementById("closeModalButton")
    .addEventListener(
        "click",
        closeContactModal
    );

document
    .getElementById("cancelContactButton")
    .addEventListener(
        "click",
        closeContactModal
    );


/*
 * Search button
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
 * Add or update contact
 */

contactForm.addEventListener(
    "submit",
    async function (event) {

        event.preventDefault();

        const id =
            contactId.value;

        const contactData = {
            firstname:
                firstName.value.trim(),

            lastname:
                lastName.value.trim(),

            email:
                email.value.trim(),

            phone:
                phone.value.trim()
        };


        try {

            if (id) {

                await apiRequest(
                    `/api/contact/update/${encodeURIComponent(id)}`,
                    {
                        method: "PUT",

                        body:
                            JSON.stringify(
                                contactData
                            )
                    }
                );

                showMessage(
                    "Contact updated successfully.",
                    "success"
                );

            } else {

                await apiRequest(
                    "/api/contact/create",
                    {
                        method: "POST",

                        body:
                            JSON.stringify(
                                contactData
                            )
                    }
                );

                showMessage(
                    "Contact added successfully.",
                    "success"
                );
            }


            closeContactModal();
            // Reload the page user is on instead of always page 1
            await searchContacts(searchState.page, {keepMessage: true});


        } catch (error) {

            showMessage(
                error.message,
                "error"
            );
        }

    }
);


/*
 * Start a new search from the search box
 */
function runNewSearch(){

    searchState.query = searchInput.value.trim();
    return searchContacts(1);
}

/*
 * Search contacts
 */

async function searchContacts(page, options = {}) {

    page = Math.max(
        1,
        parseInt(page, 10) || 1
    );
    const requestId = ++latestSearchRequestId;

    setSearchLoading(true);

    if (!options.keepMessage){
        showMessage("", "");
    }

    try {

        /*
         * IMPORTANT:
         *
         * The Bruno collection currently sends
         * a JSON BODY with a GET request.
         *
         * Browsers do not reliably support GET
         * request bodies.
         *
         * This frontend therefore uses:
         *
         * /api/contact/search?search=value
         *
         * The backend should read "search"
         * from the query parameter.
         */
        const params =
            new URLSearchParams({
                search: searchState.query,
                page: String(page),
                per_page: String(CONTACTS_PER_PAGE)
            });

        
        const data =
            await apiRequest(
                `/api/contact/search?${params.toString()}`
            );

        // A newer search or page click started while this one was in flight.
        if (requestId !== latestSearchRequestId) {
            return;
        }

        if (data.success === false) {
            throw new Error(
                data.message ||
                data.error ||
                "Search failed."
            );
        }

        const contacts = Array.isArray(data.data) ? data.data : [];

        const meta = normalizeMeta(data.meta, page, contacts.length);
        /*
         * Backend response structure may
         * differ slightly.
         *
         * Handle several likely forms.
         */

        if (
            contacts.length === 0 &&
            meta.total > 0 &&
            meta.current_page > 1
        ) {
            return searchContacts(
                Math.min(
                    meta.current_page - 1,
                    Math.max(1, meta.total_pages)
                ),
                options
            );
        }
 
 
        if (contacts.length === 0 && meta.total > 0) {
            console.warn(
                `[contacts] API reported total=${meta.total} but returned ` +
                `no rows for page ${meta.current_page}. Check server offset logic.`
            );
        }
 
 
        searchState.page =
            meta.current_page;
 
        renderContacts(contacts);
 
        renderResultsSummary(
            meta,
            contacts.length
        );
 
        Pagination.render(
            paginationNav,
            contacts.length ? meta : { total_pages: 0 },
            {
                onPageChange: searchContacts,
                maxButtons: 10
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
 
        renderContacts([]);
 
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
 * Turns the API's meta block into readable numbers
 */
 
function normalizeMeta(meta, requestedPage, rowCount) {
 
    meta = meta || {};
 
    const perPage =
        parseInt(meta.per_page, 10) || CONTACTS_PER_PAGE;
 
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
 * Displays what indeces we are displaying(e.g "11-20 of 42")
 */

function renderResultsSummary(meta, rowCount) {
 
    if (!resultsSummary) {
        return;
    }
 
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
 
    if (contactsTable) {
        contactsTable.setAttribute(
            "aria-busy",
            isLoading ? "true" : "false"
        );
    }
 
    Pagination.setDisabled(
        paginationNav,
        isLoading
    );
}

/*
 * Display contacts in table
 */

function renderContacts(contacts) {

    contactsTableBody.innerHTML = "";


    if (!contacts.length) {

        contactsTableBody.innerHTML = `
            <tr>
                <td
                    colspan="5"
                    class="empty-message"
                >
                    No contacts found.
                </td>
            </tr>
        `;

        return;
    }


    contacts.forEach(
        function (contact) {

            const row =
                document.createElement("tr");


            row.innerHTML = `
                <td>
                    ${escapeHtml(
                        contact.firstname || ""
                    )}
                </td>

                <td>
                    ${escapeHtml(
                        contact.lastname || ""
                    )}
                </td>

                <td>
                    ${escapeHtml(
                        contact.email || ""
                    )}
                </td>

                <td>
                    ${escapeHtml(
                        contact.phone || ""
                    )}
                </td>

                <td class="action-buttons">

                    <button
                        class="edit-button"
                        data-id="${contact.id}"
                    >
                        Edit
                    </button>

                    <button
                        class="delete-button"
                        data-id="${contact.id}"
                    >
                        Delete
                    </button>

                </td>
            `;


            row
                .querySelector(".edit-button")
                .addEventListener(
                    "click",
                    function () {

                        editContact(
                            contact.id
                        );
                    }
                );


            row
                .querySelector(".delete-button")
                .addEventListener(
                    "click",
                    function () {

                        deleteContact(
                            contact.id
                        );
                    }
                );


            contactsTableBody.appendChild(
                row
            );
        }
    );
}


/*
 * Get one contact and open edit modal
 */

async function editContact(id) {

    try {

        const data =
            await apiRequest(
                `/api/contact/search/${encodeURIComponent(id)}`
            );


        /*
         * Handle either:
         *
         * { contact: {...} }
         *
         * or simply:
         *
         * {...}
         */

        const contact = data.data;


        contactId.value =
            contact.id || id;

        firstName.value =
            contact.firstname || "";

        lastName.value =
            contact.lastname || "";

        email.value =
            contact.email || "";

        phone.value =
            contact.phone || "";


        modalTitle.textContent =
            "Edit Contact";


        contactModal.classList.remove(
            "hidden"
        );


    } catch (error) {

        showMessage(
            error.message,
            "error"
        );
    }
}


/*
 * Delete contact
 */

async function deleteContact(id) {

    const confirmed =
        window.confirm(
            "Are you sure you want to delete this contact?"
        );


    if (!confirmed) {
        return;
    }


    try {

        await apiRequest(
            `/api/contact/delete/${encodeURIComponent(id)}`,
            {
                method: "DELETE"
            }
        );


        showMessage(
            "Contact deleted successfully.",
            "success"
        );


        await searchContacts(searchState.page, {keepMessage: true});


    } catch (error) {

        showMessage(
            error.message,
            "error"
        );
    }
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

                window.location.href =
                    "login.html";
            }
        }
    );


function closeContactModal() {

    contactModal.classList.add(
        "hidden"
    );

    clearContactForm();
}


function clearContactForm() {

    contactId.value = "";
    firstName.value = "";
    lastName.value = "";
    email.value = "";
    phone.value = "";
}


function showMessage(text, type) {

    pageMessage.textContent =
        text;

    pageMessage.className =
        type 
        ? `page-message ${type}`
        : "page-message";
}


/*
 * Prevent contact data from being
 * interpreted as HTML.
 */

function escapeHtml(value) {

    const div =
        document.createElement("div");

    div.textContent = value;

    return div.innerHTML;
}