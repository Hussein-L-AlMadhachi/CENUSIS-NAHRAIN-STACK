import type { JSX } from "react";

export function Title({ children, className }: { className?: string, children?: JSX.Element }) {
    return (
        <h1 className={`text-4xl font-bold py-10 ${className ? className : ""}`}>{children}</h1>
    );
}