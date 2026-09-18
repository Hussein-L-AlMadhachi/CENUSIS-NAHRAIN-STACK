import { type JSX, useState, useEffect, useCallback } from "react";
import { ArrowRightFromLine, UserRoundPlus } from "lucide-react";
import { Link, useParams } from "wouter";
// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Modal } from "@/components/Modal";
import { DynamicForm } from "@/components/DynamicForm";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type LabAttendanceRecordData, teacherRPC } from "@/rpc";
import { DatePicker } from "@/components/DatePciker";
import { sidebar_pages } from "./sidebar_pages";



function dateToYMD(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0'); // months are 0-indexed
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}



interface OptionsProps {
    onAddClick: () => void;
}



function Options({ onAddClick }: OptionsProps) {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href={`/teacher/subjects`} className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> إدارة سجل الحضور </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

            <li>
                <button className="btn" onClick={onAddClick}>
                    <UserRoundPlus size={18} /> إضافة سجل جديد للمحاضرة
                </button>
            </li>

        </ul>
    </div>;
}



interface AddAttendanceRecordModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    subjectId: number;
}

function AddAttendanceRecordModal({ isOpen, onClose, onSuccess, subjectId }: AddAttendanceRecordModalProps) {
    const handleAddAttendanceRecord = async (data: { date: string }) => {
        try {
            await teacherRPC.createDailyAttendanceRecord(subjectId, data.date, true);
            onSuccess();
            onClose();
        } catch (error) {
            throw `حدث خطأ أثناء إضافة الحساب ${error}`;
        }
    };

    return (
        <Modal isOpen={isOpen} className="w-full flex flex-col justify-center max-w-lg">
            <h3 className="font-bold text-lg mb-4 text-center">إضافة سجل حضور يومي</h3>

            <DynamicForm
                key={isOpen ? "open" : "closed"}
                template={[
                    { title: "التاريخ", key: "date", type: "date" }
                ]}
                onSubmit={handleAddAttendanceRecord}
                submitLabel="حفظ"
                customComponents={{
                    "date": ({ value, onChange }) => {
                        return <DatePicker
                            date={value ? new Date(value) : null}
                            setDate={(date) => {
                                if (date) onChange(dateToYMD(date));
                            }}
                            className="input input-bordered w-full"
                        />
                    }
                }}
            />

            <button className="btn btn-ghost w-full mt-4" onClick={onClose}>إلغاء</button>
        </Modal>
    );
}



function MainContent(): JSX.Element {
    const params = useParams();
    if (!params.subject_id) {
        throw "invalid subject"
    }

    const subject_id = parseInt(params.subject_id);

    const [data, setData] = useState<LabAttendanceRecordData[]>([]);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const fetchData = useCallback(() => {
        teacherRPC.fetchDailyLabAttendanceRecordsForTheSubject(subject_id).then((data) => setData(data));
    }, [subject_id]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const customRenderers: Record<string, (row: LabAttendanceRecordData) => JSX.Element> = {
        "@view_absentees": (row: LabAttendanceRecordData) => {

            return (
                <div className="flex flex-col flex-nowrap">
                    <Link href={`/teacher/lab/absented/${row.id}`} className="btn btn-xs">
                        الطلاب الغائبون
                    </Link>
                </div>
            )
        },
    }

    return <>
        <Section>
            <Subsection>
                <Options onAddClick={() => setIsAddModalOpen(true)} />
            </Subsection>
            <Subsection>

                <EditableTable
                    data={data || []}
                    headers={{ "date": "التاريخ", "created_at": "تاريخ الرفع", "@view_absentees": "" }}
                    customRenderers={customRenderers}
                />

            </Subsection>
        </Section>

        <AddAttendanceRecordModal
            isOpen={isAddModalOpen}
            onClose={() => setIsAddModalOpen(false)}
            onSuccess={fetchData}
            subjectId={subject_id}
        />
    </>;
}



export function TeachersLabAttendancePage(): JSX.Element {
    useValidRoute(["teacher"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"حضور الطلاب"}
            sidebar={sidebar_pages}
        />
    </>
}
