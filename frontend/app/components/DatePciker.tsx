import { useRef, type ChangeEvent } from "react";

function hassonDateFormat(date: Date): string {
    return `${String(date.getDate()).padStart(2, "0")}-${String(date.getMonth() + 1).padStart(2, "0")}-${date.getFullYear()}`;
}

interface DatePickerProps {
    setDate: (date: Date | null) => void;
    date: Date | null;
    className?: string;
}

export function DatePicker({ setDate, date, className }: DatePickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);

    const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
        setDate(e.target.value ? new Date(e.target.value) : null);
    };

    // Format date for input value (YYYY-MM-DD) in local timezone
    const formatDateForInput = (date: Date | null): string => {
        if (!date) return "";

        // Get local date parts
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    };

    const handleButtonClick = () => {
        inputRef.current?.showPicker();
    };

    return (
        <div style={{ position: "relative", display: "inline-block" }}>
            <button
                type="button"
                onClick={handleButtonClick}
                className={`input input-border cursor-pointer ${className || ""}`}
            >
                {date ? hassonDateFormat(date) : "pick a date"}
            </button>

            <input
                ref={inputRef}
                type="date"
                value={date ? formatDateForInput(date) : ""}
                onChange={handleChange}
                style={{
                    position: "absolute",
                    top: 0,
                    left: 0,
                    width: "100%",
                    height: "100%",
                    opacity: 0,
                    pointerEvents: "none"
                }}
            />
        </div>
    );
}