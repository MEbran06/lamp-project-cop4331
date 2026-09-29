const loginForm = document.getElementById("loginForm");
const loginButton = document.getElementById("loginButton");
const message = document.getElementById("message");

loginForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    if (!username || !password) {
        showMessage(
            "Please enter your username and password.",
            "error"
        );
        return;
    }

    loginButton.disabled = true;
    loginButton.textContent = "Signing in...";

    try {
        const data = await apiRequest("/api/login", {
            method: "POST",

            body: JSON.stringify({
                username: username,
                password: password
            })
        });

        // Save CSRF token returned by backend
        if (data.csrf_token) {
            sessionStorage.setItem(
                "csrfToken",
                data.csrf_token
            );
        }

        // Save username so it can be displayed
        // on the contact page
        sessionStorage.setItem(
            "username",
            username
        );

        // Save role so pages know whether to show admin features.
        // Admins and users share this login page.
        const role = getLoginRole(data);

        sessionStorage.setItem(
            "role",
            role
        );

        showMessage(
            "Login successful.",
            "success"
        );
        // Redirect to main contact page
        if (!data.is_admin)
            window.location.href = "contacts.html";
        else
             window.location.href = "admin.html"; // replace with actual admin page

    } catch (error) {

        // Keep technical details in console
        // but show a generic error to the user
        console.error("Login failed:", error);

        showMessage(
            error.message,
            "error"
        );

    } finally {
        loginButton.disabled = false;
        loginButton.textContent = "Sign In";
    }
});


/*
 * Read the role from the login response.
 *
 * CONFIRM with the backend which field it returns. This handles
 * several likely forms; anything else is treated as a regular user.
 */
function getLoginRole(data) {
    const source =
        (data && (data.user || data.data)) || data || {};

    const role = source.role || data.role;

    const isAdmin =
        source.is_admin ?? data.is_admin;

    if (
        role === "admin" ||
        isAdmin === true ||
        isAdmin === 1 ||
        isAdmin === "1"
    ) {
        return "admin";
    }

    return "user";
}


function showMessage(text, type) {
    message.textContent = text;
    message.className = `message ${type}`;
}