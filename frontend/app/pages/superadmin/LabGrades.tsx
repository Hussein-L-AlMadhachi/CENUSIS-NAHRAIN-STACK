import { type JSX, useState, useEffect, useCallback } from "react";
import { ArrowRightFromLine } from "lucide-react";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type LabGradesData, superAdminRPC } from "@/rpc";
import { Link, useParams } from "wouter";
import { sidebar_pages } from "./sidebar_pages";

function Options() {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href="/superadmin/lab/subjects" className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
    </div>;
}

function MainContent(): JSX.Element {
    const params = useParams<{ studying_id: string }>();
    if (!params.studying_id) {
        throw "invalid studying id";
    }

    const [data, setData] = useState<LabGradesData[]>([]);

    const fetchData = useCallback(() => {
        superAdminRPC.fetchStudentLabGradesPerStudying(parseInt(params.studying_id)).then((grades) => {
            setData(grades);
        });
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
                <Options />
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

export function SuperLabGradesPage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    const params = useParams<{ studying_id: string }>();
    const [subjectName, setSubjectName] = useState<string>("");

    useEffect(() => {
        if (!params.studying_id) {
            return;
        }
        superAdminRPC.fetchSingleSubject(parseInt(params.studying_id)).then((subject) => {
            setSubjectName(subject.subject_name ?? "");
        });
    }, [params.studying_id]);

    const title = subjectName ? `درجات المختبر - ${subjectName}` : "درجات المختبر";

    return <>
        <MainLayout
            main={MainContent}
            title={title}
            sidebar={sidebar_pages}
        />
    </>;
}
