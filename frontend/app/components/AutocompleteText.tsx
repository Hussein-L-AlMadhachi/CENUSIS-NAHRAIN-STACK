import { useState, useEffect, useRef, type ChangeEvent } from "react";





interface Props {
    fetchSuggestions: (query: string) => Promise<string[]> | string[];
    onSelect: (value: string) => void;
    placeholder: string;
    className?: string;
    debounceTime?: number;
    expand?: boolean;
    ghost?: boolean;
    disabled?: boolean;
}





export function AutocompleteText({
    fetchSuggestions,
    onSelect,
    placeholder = "",
    className = "",
    debounceTime = 300,
    expand = false,
    value,
    onChange,
    ghost = false,
    disabled = false,
}: Props & { value?: string; onChange?: (value: string) => void }) {
    const [query, setQuery] = useState(value || "");
    const [suggestions, setSuggestions] = useState<string[]>([]);
    const [loading, setLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const wrapperRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };

        document.addEventListener("mousedown", handleClickOutside);
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, []);

    useEffect(() => {
        if (value !== undefined && value !== query) {
            setQuery(value);
        }
    }, [value]);

    useEffect(() => {
        const timer = setTimeout(async () => {
            if (query.trim().length === 0) {
                setSuggestions([]);
                setIsOpen(false);
                return;
            }

            setLoading(true);
            try {
                const results = await fetchSuggestions(query);
                setSuggestions(results);
                setIsOpen(results.length > 0);
            } catch (error) {
                console.error("Error fetching suggestions:", error);
                setSuggestions([]);
            } finally {
                setLoading(false);
            }
        }, debounceTime);

        return () => clearTimeout(timer);
    }, [query, fetchSuggestions, debounceTime]);

    const handleInputChange = (e: ChangeEvent<HTMLInputElement>) => {
        const newVal = e.target.value;
        setQuery(newVal);
        onChange?.(newVal);
    };

    const handleSelect = (value: string) => {
        setQuery(value);
        setIsOpen(false);
        onSelect(value);
        onChange?.(value);
    };

    return (
        <div ref={wrapperRef} className={`dropdown w-full ${isOpen ? "dropdown-open" : ""} ${className}`}>
            <label className={`input ${ghost ? "input-ghost" : "input-bordered"} flex items-center gap-2 w-full`}>
                <input
                    type="text"
                    className="grow"
                    placeholder={placeholder}
                    value={query}
                    onChange={handleInputChange}
                    onFocus={() => {
                        if (suggestions.length > 0) setIsOpen(true);
                    }}
                    disabled={disabled}
                />
                {loading && <span className="loading loading-spinner loading-sm"></span>}
            </label>

            {isOpen && (

                <ul dir="auto" className={`${expand ? "" : "dropdown-content"} flex-nowrap menu p-2 shadow bg-base-100 rounded-box w-full mt-1 max-h-60 overflow-y-scroll h-20 z-50`}>
                    {suggestions.map((suggestion, index) => (
                        <li key={index}>
                            <a onClick={() => handleSelect(suggestion)}>{suggestion}</a>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

