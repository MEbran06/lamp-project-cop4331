const registerForm =
    document.getElementById("registerForm");

const registerButton =
    document.getElementById("registerButton");

const message =
    document.getElementById("message");


registerForm.addEventListener(
    "submit",
    async function (event) {

        event.preventDefault();


        const firstname =
            document
                .getElementById("firstName")
                .value
                .trim();

        const lastname =
            document
                .getElementById("lastName")
                .value
                .trim();

        const username =
            document
                .getElementById("username")
                .value
                .trim();

        const password =
            document
                .getElementById("password")
                .value;


        if (
            !firstname ||
            !lastname ||
            !username ||
            !password
        ) {

            showMessage(
                "Please complete all fields.",
                "error"
            );

            return;
        }


        registerButton.disabled = true;
        registerButton.textContent =
            "Creating Account...";


        try {

            await apiRequest(
                "/api/signup",
                {
                    method: "POST",

                    body:
                        JSON.stringify({
                            firstname,
                            lastname,
                            username,
                            password
                        })
                }
            );


            showMessage(
                "Account created successfully.",
                "success"
            );


            setTimeout(
                function () {

                    window.location.href =
                        "login.html";

                },
                1000
            );


        } catch (error) {

            showMessage(
                error.message,
                "error"
            );


        } finally {

            registerButton.disabled =
                false;

            registerButton.textContent =
                "Register";
        }

    }
);


function showMessage(text, type) {

    message.textContent =
        text;

    message.className =
        `message ${type}`;
}