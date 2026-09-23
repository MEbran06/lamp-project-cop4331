const loginForm = document.getElementById("loginForm");
const loginButton = document.getElementById("loginButton");
const message = document.getElementById("message");

loginForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    if (!username || !password) {
        showMessage("Please enter your username and password.", "error");
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

        /*
         * The Bruno collection shows that login returns:
         * csrf_token
         */
        if (data.csrf_token) {
            sessionStorage.setItem(
                "csrfToken",
                data.csrf_token
            );
        }

        // Save username so we can display it.
        sessionStorage.setItem("username", username);

        showMessage("Login successful.", "success");

        window.location.href = "contacts.html";

    } catch (error) {
        showMessage(error.message, "error");

    } finally {
        loginButton.disabled = false;
        loginButton.textContent = "Sign In";
    }
});


function showMessage(text, type) {
    message.textContent = text;
    message.className = `message ${type}`;
}