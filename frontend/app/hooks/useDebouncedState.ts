import { useState, useRef, useEffect } from 'react';



export function useDebouncedState<T>(initial: T, delay: number): [T, (value: T) => void] {
    const [state, setState] = useState<T>(initial);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        // Cleanup on unmount
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    const setDebounced = (value: T) => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
        timeoutRef.current = setTimeout(() => {
            setState(value);
        }, delay);
    };

    return [state, setDebounced];
}

