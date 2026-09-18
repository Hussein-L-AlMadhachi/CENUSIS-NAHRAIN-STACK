import { type JSX, useState, useEffect, useCallback } from "react";
import { ArrowRightFromLine, FileDown, Upload } from "lucide-react";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type LabGradesData, teacherRPC } from "@/rpc";
import { Link, useParams } from "wouter";
import { sidebar_pages } from "./sidebar_pages";







interface OptionsProps {
    onImportSuccess: () => void;
    studying_id: number;
}



function Options({ onImportSuccess, studying_id }: OptionsProps) {
    const [isErrorModalOpen, setIsErrorModalOpen] = useState(false);
    const [errorMessage, setErrorMessage] = useState("");

    const showErrorModal = (message: string) => {
        setErrorMessage(message);
        setIsErrorModalOpen(true);
    };

    const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const input = e.currentTarget;
        if (!input.files || input.files.length === 0) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append("file", file);

        try {
            const response = await fetch(`/api/lab/grades/import/${studying_id}`, {
                method: "POST",
                body: formData,
            });

            if (response.ok) {
                onImportSuccess();
            } else {
                const result = await response.json();
                console.log(result);
                showErrorModal("خطأ في معالجة الملف: " + (result?.err || "حدث خطأ غير متوقع"));
            }
        } catch (error) {
            console.error("Upload error:", error);
            showErrorModal("خطأ في رفع الملف");
        } finally {
            // Allow selecting the same file again so failures can retrigger the flow.
            input.value = "";
        }
    };

    return <>
        <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
            <Link href="/teacher/subjects" className="btn btn-md btn-ghost btn-circle">
                <ArrowRightFromLine />
            </Link>
            <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> رفع درجات الطلاب </div>
            <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

                <li>
                    <a className="btn" href={`/api/lab/grades/template/${studying_id}`} download>
                        <FileDown size={18} /> تحميل نموذج الملف
                    </a>
                </li>

                <li>
                    <label className="btn">
                        <input type="file" className="hidden" accept=".xlsx, .xls" onChange={handleFileUpload} />
                        <Upload size={18} /> رفع البيانات من برنامج أكسل
                    </label>
                </li>
            </ul>
        </div>

        <dialog className={`modal ${isErrorModalOpen ? "modal-open" : ""}`}>
            <div className="modal-box">
                <h3 className="text-lg font-bold">حدث خطأ</h3>
                <p className="py-4">{errorMessage}</p>
                <div className="modal-action">
                    <button className="btn btn-error btn-outline" onClick={() => setIsErrorModalOpen(false)}>
                        إغلاق
                    </button>
                </div>
            </div>
            <form method="dialog" className="modal-backdrop">
                <button onClick={() => setIsErrorModalOpen(false)}>close</button>
            </form>
        </dialog>
    </>;
}







function MainContent(): JSX.Element {
    const params = useParams<{ studying_id: string }>();
    if (!params.studying_id) {
        throw "invalid studying id"
    }

    const [data, setData] = useState<LabGradesData[]>([]);

    const fetchData = useCallback(() => {
        if (params.studying_id) {
            teacherRPC.fetchStudentLabGradesPerStudying(parseInt(params.studying_id)).then((data) => {
                setData(data);
            });
        }
    }, [params.studying_id]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const headers: Record<string, string> = {
        student_name: "اسم الطالب",
        lab_grade: "درجة المختبر",
    };

    const rows = (data || []).map((row) => ({
        id: row.id,
        student_name: row.student_name ?? "",
        lab_grade: row.lab_grade ?? 0,
    }));

    return <>
        <Section>
            <Subsection>
                <Options onImportSuccess={fetchData} studying_id={parseInt(params.studying_id)} />
            </Subsection>
            <Subsection>

                <EditableTable
                    data={rows}
                    headers={headers}
                />

            </Subsection>
        </Section>

    </>;
}







export function TeachersLabGradesPage(): JSX.Element {
    useValidRoute(["teacher"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"درجات مادة"}
            sidebar={sidebar_pages}
        />
    </>
}


