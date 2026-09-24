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

        showMessage(
            "Login successful.",
            "success"
        );

        // Redirect to main contact page
        window.location.href = "contact.html";

    } catch (error) {

        // Keep technical details in console
        // but show a generic error to the user
        console.error("Login failed:", error);

        showMessage(
            "Login failed. Please check your username and password.",
            "error"
        );

    } finally {
        loginButton.disabled = false;
        loginButton.textContent = "Sign In";
    }
});


function showMessage(text, type) {
    message.textContent = text;
    message.className = `message ${type}`;
}