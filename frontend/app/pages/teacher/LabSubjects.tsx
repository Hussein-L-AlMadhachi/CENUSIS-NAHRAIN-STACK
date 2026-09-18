import { type JSX, useState, useEffect } from "react";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type SubjectData, teacherRPC } from "@/rpc";
import { sidebar_pages } from "./sidebar_pages";



function MainContent(): JSX.Element {

    const [data, setData] = useState<SubjectData[]>([]);

    const fetchData = () => {
        teacherRPC.fetchSubjectsByLabTeacher().then((data) => setData(data));

    };

    useEffect(() => {
        fetchData();
    }, []);

    const handleUpdateSubject = async (id: number, data: Partial<SubjectData>) => {
        await teacherRPC.updateSubject(id, data).then(() => fetchData());
    };

    const handleDeleteSubject = async (id: number) => {
        await teacherRPC.deleteSubject(id).then(() => fetchData());
    };

    const table_headers = {
        "subject_name": "الاسم",
        "grading_system_name": "نظام الدرجات",
        "teacher_name": "التدريسي", "degree": "الدرجة العلمية", "class": "المرحلة",
        "semester": "الكورس", "@view_students": ""
    }

    const customRenderers: Record<string, (row: SubjectData) => JSX.Element> = {
        "@view_students": (row: SubjectData) => {

            return (
                <div className="flex flex-col flex-nowrap gap-1">
                    <a href={`/teacher/lab/attendance/${row.id}`} className="btn btn-xs w-32">
                        إدارة الغياب
                    </a>
                    <a href={`/teacher/lab/grades/${row.id}`} className="btn btn-xs w-32">
                        إدارة الدرجات
                    </a>
                </div>
            )
        },
    }

    return <>
        <Section>
            <Subsection>
                <EditableTable
                    data={data || []}
                    headers={table_headers}
                    onDelete={handleDeleteSubject}
                    onSave={handleUpdateSubject}
                    customRenderers={customRenderers}
                />
            </Subsection>
        </Section>
    </>;
}


export function TeachersLabSubjectsPage(): JSX.Element {
    useValidRoute(["teacher"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"المواد المختبرية"}
            sidebar={sidebar_pages}
        />
    </>
}
