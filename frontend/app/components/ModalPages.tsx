import type React from "react";
import { useRef, useState } from "react";



interface Props {
  pages: React.ComponentType[];
  text: string;
  className?: string;
  btnClassName?: string;
}



export function ModalPages({ pages, className, text, btnClassName }: Props) {

    const [page_index, setPageIndex] = useState(0);
    const dialogRef = useRef<HTMLDialogElement>(null);


    if (pages.length === 0) {
      console.warn("ModalPages: 'pages' array is empty");
      return null;
    }

    const highest_index = pages.length - 1;
        
    const openModal = () => {
        setPageIndex(0); // Reset to first page
        dialogRef.current?.showModal();
    };

    const closeModal = () => {
        dialogRef.current?.close();
    };

    const nextPage = () => {
        if (page_index < highest_index) {
        setPageIndex(page_index + 1);
        } else {
        closeModal();
        }
    };

    const SelectedPage = pages[page_index];

    return (
        <>
        <button
            className={`btn ${btnClassName || ""}`}
            onClick={openModal}
        >
            {text}
        </button>

        <dialog
            ref={dialogRef}
            className="modal"
            aria-label={`Step ${page_index + 1} of ${pages.length}`}
        >
            <div className={`modal-box w-11/12 max-w-5xl overflow-y-auto ${className || ""}`}>
            <SelectedPage />
            <div className="modal-action">
                {page_index > 0 && (
                    <button className="btn" onClick={() => setPageIndex(page_index - 1)}>
                        Back
                    </button>
                )}
                <button className="btn" onClick={nextPage}>
                {page_index === highest_index ? "Finish" : "Next"}
                </button>
                <button className="btn" onClick={closeModal}>
                Close
                </button>
            </div>
            </div>
        </dialog>
        </>
    );
}