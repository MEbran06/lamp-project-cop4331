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
    searchContacts
);


/*
 * Allow Enter to search
 */

searchInput.addEventListener(
    "keydown",
    function (event) {

        if (event.key === "Enter") {
            searchContacts();
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
                    `/api/contact/update?id=${encodeURIComponent(id)}`,
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

            await searchContacts();


        } catch (error) {

            showMessage(
                error.message,
                "error"
            );
        }

    }
);


/*
 * Search contacts
 */

async function searchContacts() {

    const searchTerm =
        searchInput.value.trim();

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

        const data =
            await apiRequest(
                `/api/contact/search?search=${
                    encodeURIComponent(
                        searchTerm
                    )
                }`
            );


        /*
         * Backend response structure may
         * differ slightly.
         *
         * Handle several likely forms.
         */

        let contacts = [];

        if (Array.isArray(data)) {

            contacts = data;

        } else if (
            Array.isArray(data.contacts)
        ) {

            contacts = data.contacts;

        } else if (
            Array.isArray(data.results)
        ) {

            contacts = data.results;

        }


        renderContacts(contacts);


    } catch (error) {

        showMessage(
            error.message,
            "error"
        );

        renderContacts([]);
    }
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
                `/api/contact?id=${
                    encodeURIComponent(id)
                }`
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

        const contact =
            data.contact || data;


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
            `/api/contact/delete?id=${
                encodeURIComponent(id)
            }`,
            {
                method: "DELETE"
            }
        );


        showMessage(
            "Contact deleted successfully.",
            "success"
        );


        await searchContacts();


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
        `page-message ${type}`;
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