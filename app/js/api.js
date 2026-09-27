const API_BASE_URL = "http://localhost:80";

async function getCsrfToken() {
    let csrfToken = sessionStorage.getItem("csrfToken");

    if (!csrfToken) {
        const response = await fetch(`${API_BASE_URL}/api/csrf-token`, {
            credentials: "include"
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.error || `Failed to obtain CSRF token (${response.status})`
            );
        }

        csrfToken = data.csrf_token;
        sessionStorage.setItem("csrfToken", csrfToken);
    }

    return csrfToken;
}

async function apiRequest(endpoint, options = {}) {
    const method = (options.method || "GET").toUpperCase();

    const headers = {
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...(options.headers || {})
    };

    // Only send a CSRF token for state-changing requests, ignore login
    if (["POST", "PUT", "PATCH", "DELETE"].includes(method) 
        && endpoint !== "/api/login") {
        const csrfToken = await getCsrfToken();
        headers["X-CSRF-Token"] = csrfToken;
    }

    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        credentials: "include",
        ...options,
        headers
    });

    let data = {};

    try {
        data = await response.json();
    } catch {
        // Some successful responses may not return JSON.
    }

    if (!response.ok) {
        throw new Error(
            data.message ||
            data.error ||
            `Request failed with status ${response.status}`
        );
    }

    return data;
}