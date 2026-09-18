import { type JSX, useState, useEffect, useCallback } from "react";
import { ArrowRightFromLine } from "lucide-react";
import { Link, useParams } from "wouter";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type LabAttendanceRecordData, superAdminRPC } from "@/rpc";
import { sidebar_pages } from "./sidebar_pages";

interface OptionsProps {
    subjectName: string;
}

function Options({ subjectName }: OptionsProps) {
    const title = subjectName ? `سجل حضور المختبر - ${subjectName}` : "سجل حضور المختبر";

    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href="/superadmin/lab/subjects" className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full flex-1">{title}</div>
        <div className="btn btn-md btn-ghost btn-circle invisible" aria-hidden="true">
            <ArrowRightFromLine />
        </div>
    </div>;
}

function MainContent(): JSX.Element {
    const params = useParams<{ subject_id: string }>();
    if (!params.subject_id) {
        throw "invalid subject";
    }

    const subject_id = parseInt(params.subject_id);
    const [data, setData] = useState<LabAttendanceRecordData[]>([]);
    const [subjectName, setSubjectName] = useState<string>("");

    const fetchData = useCallback(() => {
        superAdminRPC.fetchDailyLabAttendanceRecordsForTheSubject(subject_id).then((records) => setData(records));
    }, [subject_id]);

    const fetchSubjectName = useCallback(() => {
        superAdminRPC.fetchSingleSubject(subject_id).then((subject) => {
            setSubjectName(subject.subject_name ?? "");
        });
    }, [subject_id]);

    useEffect(() => {
        fetchData();
        fetchSubjectName();
    }, [fetchData, fetchSubjectName]);

    const customRenderers: Record<string, (row: LabAttendanceRecordData) => JSX.Element> = {
        "@view_absentees": (row: LabAttendanceRecordData) => {
            return (
                <div className="flex flex-col flex-nowrap">
                    <Link href={`/superadmin/lab/absented/${row.id}`} className="btn btn-xs">
                        الطلاب الغائبون
                    </Link>
                </div>
            );
        },
    };

    return <>
        <Section>
            <Subsection>
                <Options subjectName={subjectName} />
            </Subsection>
            <Subsection>
                <EditableTable
                    data={data || []}
                    headers={{ "date": "التاريخ", "created_at": "تاريخ الرفع", "@view_absentees": "" }}
                    customRenderers={customRenderers}
                />
            </Subsection>
        </Section>
    </>;
}

export function SuperLabAttendancePage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"سجل حضور المختبر"}
            sidebar={sidebar_pages}
        />
    </>;
}
