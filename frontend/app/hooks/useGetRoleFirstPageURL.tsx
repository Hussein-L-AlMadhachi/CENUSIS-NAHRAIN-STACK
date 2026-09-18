import { useLocation } from "wouter";


export function useGetRoleFirstPageURL(routes: Record<string, string>) {
    const [, navigate] = useLocation();

    return (role: string) => navigate(routes[role]);
}