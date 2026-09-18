import { useRef, useEffect } from "react";

interface ModalProps {
    children: React.ReactNode;
    isOpen: boolean;
    className?: string;
}

export function Modal({ children, isOpen, className }: ModalProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        if (dialogRef.current) {
            if (isOpen) {
                dialogRef.current.showModal();
            } else {
                dialogRef.current.close();
            }
        }
    }, [isOpen]);

    return (
        <dialog ref={dialogRef} className="modal modal-top flex justify-center p-0">
            <div className={`modal-box max-h-[calc(100vh-5em)] justify-start overflow-y-auto ${className || ""}`}>
                {children}
            </div>
        </dialog>
    );
}