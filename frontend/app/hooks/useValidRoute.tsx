import { useLocation } from "wouter";


function parseCookies() {
    const cookies: Record<string, string> = {};
    const raw = document.cookie;

    if (!raw) return cookies;

    raw.split(";").forEach(entry => {
        const [name, ...rest] = entry.split("=");
        const trimmedName = name.trim();
        const value = rest.join("=").trim(); // in case value contains "="
        cookies[trimmedName] = decodeURIComponent(value);
    });

    return cookies;
}



/**
 * NOTE: roles can be null indicating not auth required
 */
export function useValidRoute(
    roles: string | string[], login_page: string
) {
    const [, navigate] = useLocation();

    const current_user_role = parseCookies()["auth-role"];

    if (current_user_role === null) {
        navigate(login_page);
        return;
    }

    if (typeof roles === "string") {
        if (current_user_role !== roles) {
            navigate(login_page);
            return;
        }
    }

    if (roles instanceof Array) {
        if (!roles.includes(current_user_role)) {
            navigate(login_page);
            return;
        }
    }

}
