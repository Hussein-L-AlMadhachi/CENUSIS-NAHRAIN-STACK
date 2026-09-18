import { type JSX, useCallback, useState, useEffect, useRef } from "react";
import { ArrowRightFromLine, Minus, Plus } from "lucide-react";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { InPlaceEditableTable } from "../../components/InPlaceEditableTable";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type AbsentedData, type AttendanceRecordWithSubject, teacherRPC } from "@/rpc";
import { Link, useParams } from "wouter";
import { sidebar_pages } from "./sidebar_pages";




interface OptionsProps {
    subjectName: string;
}

function Options({ subjectName }: OptionsProps) {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href={`/teacher/subjects`} className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> {subjectName ? `غيابات طلاب مادة ${subjectName}` : "إدارة الغيابات"} </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

        </ul>
    </div>;
}



interface MainContentProps {
    onRecordLoaded: (record: AttendanceRecordWithSubject) => void;
}

function MainContent({ onRecordLoaded }: MainContentProps): JSX.Element {
    const params = useParams<{ attendance_record: string }>();
    if (!params.attendance_record) {
        throw "invalid attendance record"
    }

    const attendance_record_id = parseInt(params.attendance_record);

    const [data, setData] = useState<AbsentedData[]>([]);
    const [subjectName, setSubjectName] = useState<string>("");
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [bulkHours, setBulkHours] = useState(0);
    const [isBulkUpdating, setIsBulkUpdating] = useState(false);
    const bulkHoursRef = useRef(0);

    const fetchData = useCallback(async () => {
        const data = await teacherRPC.fetchAbsentStudents(attendance_record_id);
        setData(data);
    }, [attendance_record_id]);

    const fetchRecordInfo = useCallback(async () => {
        const record = await teacherRPC.fetchAttendanceRecordWithSubject(attendance_record_id);
        setSubjectName(record.subject_name);
        onRecordLoaded(record);
    }, [attendance_record_id, onRecordLoaded]);

    useEffect(() => {
        fetchData();
        fetchRecordInfo();
    }, [fetchData, fetchRecordInfo]);

    const handleInlineSave = async (_id: string | number, row: AbsentedData) => {
        const student_id = row.student_id ?? row.id;

        if (student_id === undefined || !row.student_name || typeof row.hours_absent !== "number" || Number.isNaN(row.hours_absent)) {
            throw "بيانات غير صالحة للتحديث";
        }

        await teacherRPC.markStudentAbsent({
            attendance_record_id: attendance_record_id,
            student_id,
            hours_absent: row.hours_absent,
        },true);

        await fetchData();
    };

    const toggleSelection = (studentId: number) => {
        setSelectedIds(prev => {
            const next = new Set(prev);
            if (next.has(studentId)) {
                next.delete(studentId);
            } else {
                next.add(studentId);
            }
            return next;
        });
    };

    const toggleSelectAll = () => {
        if (selectedIds.size === data.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(data.map(row => row.student_id ?? row.id).filter((id): id is number => id !== undefined)));
        }
    };

    const handleBulkUpdate = async () => {
        if (selectedIds.size === 0) return;

        setIsBulkUpdating(true);
        try {
            await teacherRPC.markStudentAbsentBulk({
                attendance_record_id,
                student_ids: Array.from(selectedIds),
                hours_absent: bulkHoursRef.current,
            }, true);
            await fetchData();
            setSelectedIds(new Set());
            setBulkHours(0);
            bulkHoursRef.current = 0;
        } finally {
            setIsBulkUpdating(false);
        }
    };

    return <>
        <Section>
            <Subsection>
                <Options subjectName={subjectName} />
            </Subsection>

            {data.length > 0 && (
                <Subsection>
                    <div className="flex items-center gap-4 flex-wrap">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                className="checkbox"
                                checked={selectedIds.size === data.length && data.length > 0}
                                onChange={toggleSelectAll}
                            />
                            <span className="p-4">تحديد الكل ({selectedIds.size}/{data.length})</span>
                        </label>

                        {selectedIds.size > 0 && (
                            <div className="flex items-center gap-2">
                                <span>ساعات الغياب:</span>
                                <div className="flex items-center w-[100px]">
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-square h-[30px] min-h-[30px] w-[30px]"
                                        disabled={isBulkUpdating}
                                        onClick={() => {
                                            const newValue = Math.max(0, bulkHoursRef.current - 1);
                                            bulkHoursRef.current = newValue;
                                            setBulkHours(newValue);
                                        }}
                                    >
                                        <Minus size={16} />
                                    </button>
                                    <div className="h-[30px] w-[40px] flex items-center justify-center font-medium">
                                        {bulkHours}
                                    </div>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-square h-[30px] min-h-[30px] w-[30px]"
                                        disabled={isBulkUpdating}
                                        onClick={() => {
                                            const newValue = bulkHoursRef.current + 1;
                                            bulkHoursRef.current = newValue;
                                            setBulkHours(newValue);
                                        }}
                                    >
                                        <Plus size={16} />
                                    </button>
                                </div>
                                <button
                                    className="btn btn-primary btn-sm"
                                    disabled={isBulkUpdating}
                                    onClick={handleBulkUpdate}
                                >
                                    {isBulkUpdating ? "جاري التحديث..." : "تطبيق"}
                                </button>
                            </div>
                        )}
                    </div>
                </Subsection>
            )}

            <Subsection>
                <InPlaceEditableTable
                    data={data || []}
                    headers={{ "student_name": "الاسم", "hours_absent": "عدد ساعات الغياب", "@select": "" }}
                    onSave={handleInlineSave}
                    editableColumns={["hours_absent"]}
                    nonEditableColumns={["student_name", "student_id"]}
                    columnFieldTypes={{ hours_absent: "number" }}
                    customRenderers={{
                        "@select": (row) => (
                            <input
                                type="checkbox"
                                className="checkbox"
                                checked={selectedIds.has(row.student_id ?? row.id ?? -1)}
                                onChange={() => {
                                    const id = row.student_id ?? row.id;
                                    if (id !== undefined) {
                                        toggleSelection(id);
                                    }
                                }}
                            />
                        ),
                    }}
                />
            </Subsection>
        </Section>
    </>;
}







export function TeachersLabAbsentedPage(): JSX.Element {
    useValidRoute(["teacher"], "/login");

    const [recordInfo, setRecordInfo] = useState<AttendanceRecordWithSubject | null>(null);

    const handleRecordLoaded = useCallback((record: AttendanceRecordWithSubject) => {
        setRecordInfo(record);
    }, []);

    const title = recordInfo
        ? `غيابات الطلاب لمادة ${recordInfo.subject_name} في تاريخ ${recordInfo.date}`
        : "غيابات الطلاب";

    const MainContentWithCallback = useCallback(
        () => <MainContent onRecordLoaded={handleRecordLoaded} />,
        [handleRecordLoaded]
    );

    return <>
        <MainLayout
            main={MainContentWithCallback}
            title={title}
            sidebar={sidebar_pages}
        />
    </>
}
