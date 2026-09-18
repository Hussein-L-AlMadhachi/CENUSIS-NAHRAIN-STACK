import { type JSX, useState, useEffect } from "react";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type SubjectData, superAdminRPC } from "@/rpc";
import { sidebar_pages } from "./sidebar_pages";

function MainContent(): JSX.Element {
    const [data, setData] = useState<SubjectData[]>([]);

    const fetchData = () => {
        superAdminRPC.fetchSubjects().then((subjects) => {
            const labSubjects = subjects.filter((subject) => subject.has_lab);
            setData(labSubjects);
        });
    };

    useEffect(() => {
        fetchData();
    }, []);

    const table_headers = {
        "subject_name": "الاسم",
        "teacher_name": "تدريسي المادة",
        "lab_teacher_name": "تدريسي المختبر",
        "degree": "الدرجة العلمية",
        "class": "المرحلة",
        "semester": "الكورس",
        "@view_students": ""
    };

    const customRenderers: Record<string, (row: SubjectData) => JSX.Element> = {
        "@view_students": (row: SubjectData) => {
            return (
                <div className="flex flex-col flex-nowrap gap-1">
                    <a href={`/superadmin/lab/attendance/${row.id}`} className="btn btn-xs w-32">
                        عرض الغياب
                    </a>
                    <a href={`/superadmin/lab/grades/${row.id}`} className="btn btn-xs w-32">
                        عرض الدرجات
                    </a>
                </div>
            );
        },
    };

    return <>
        <Section>
            <Subsection>
                <EditableTable
                    data={data || []}
                    headers={table_headers}
                    customRenderers={customRenderers}
                />
            </Subsection>
        </Section>
    </>;
}

export function SuperLabSubjectsPage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"مواد المختبر"}
            sidebar={sidebar_pages}
        />
    </>;
}
