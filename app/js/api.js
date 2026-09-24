const API_BASE_URL = "https://webappsmanuel.xyz";

async function apiRequest(endpoint, options = {}) {
    const csrfToken = sessionStorage.getItem("csrfToken");

    const headers = {
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...(options.headers || {})
    };

    if (csrfToken) {
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
