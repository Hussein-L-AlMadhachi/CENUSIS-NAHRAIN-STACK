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
import { type AbsentedData, superAdminRPC } from "@/rpc";
import { Link, useParams } from "wouter";
import { sidebar_pages } from "./sidebar_pages";

interface OptionsProps {
    subjectName: string;
    recordDate: string;
}

function Options({ subjectName, recordDate }: OptionsProps) {
    const title = subjectName && recordDate
        ? `غيابات مختبر ${subjectName} لتاريخ ${recordDate}`
        : "غيابات المختبر";

    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href="/superadmin/lab/subjects" className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full">{title}</div>
    </div>;
}

function MainContent(): JSX.Element {
    const params = useParams<{ attendance_record: string }>();
    if (!params.attendance_record) {
        throw "invalid attendance record";
    }

    const attendance_record_id = parseInt(params.attendance_record);
    const [data, setData] = useState<AbsentedData[]>([]);
    const [subjectName, setSubjectName] = useState<string>("");
    const [recordDate, setRecordDate] = useState<string>("");

    const fetchData = useCallback(() => {
        superAdminRPC.fetchAbsentStudents(attendance_record_id).then((absented) => setData(absented));
    }, [attendance_record_id]);

    const fetchRecordInfo = useCallback(() => {
        superAdminRPC.fetchAttendanceRecordWithSubject(attendance_record_id)
            .then((record) => {
                setSubjectName(record.subject_name);
                setRecordDate(record.date);
            })
            .catch((err) => {
                console.error("Failed to fetch record info:", err);
            });
    }, [attendance_record_id]);

    useEffect(() => {
        fetchData();
        fetchRecordInfo();
    }, [fetchData, fetchRecordInfo]);

    return <>
        <Section>
            <Subsection>
                <Options subjectName={subjectName} recordDate={recordDate} />
            </Subsection>
            <Subsection>
                <EditableTable
                    data={data || []}
                    headers={{ "student_name": "الاسم", "hours_absent": "عدد ساعات الغياب" }}
                />
            </Subsection>
        </Section>
    </>;
}

export function SuperLabAbsentedPage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"غيابات المختبر"}
            sidebar={sidebar_pages}
        />
    </>;
}
