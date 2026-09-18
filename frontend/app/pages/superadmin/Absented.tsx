import { type JSX, useState, useEffect, useCallback } from "react";
import { ArrowRightFromLine, UserRoundPlus } from "lucide-react";

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
import { type AbsentedData, superAdminRPC } from "@/rpc";
import { Link, useParams } from "wouter";
import { sidebar_pages } from "./sidebar_pages";







interface OptionsProps {
    onAddClick: () => void;
    subjectName: string;
    recordDate: string;
}



function Options({ onAddClick, subjectName, recordDate }: OptionsProps) {
    const title = subjectName && recordDate
        ? `غيابات طلاب مادة ${subjectName} لتاريخ ${recordDate}`
        : "إدارة الغيابات";

    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href={`/superadmin/subjects`} className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> {title} </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

            <li>
                <button className="btn" onClick={onAddClick}>
                    <UserRoundPlus size={18} /> إضافة غياب
                </button>
            </li>

        </ul>
    </div>;
}



interface AddAbsentStudentModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    record_id: number;
}

interface AddAbsentStudentFormData {
    student_name?: string;
    hours_absent?: number;
}



function AddAbsentStudentModal({ isOpen, onClose, onSuccess, record_id }: AddAbsentStudentModalProps) {
    const handleAddAbsentStudent = async (data: AddAbsentStudentFormData) => {
        if (!data.student_name || !data.hours_absent || typeof data.hours_absent !== "number") {
            throw "يجب ملئ جميع الحقول";
        }

        try {
            await superAdminRPC.markStudentAbsent({
                attendance_record_id: record_id, student_name: data.student_name,
                hours_absent: data.hours_absent
            });
            onSuccess();
            onClose();
        } catch (error) {
            throw `حدث خطأ أثناء إضافة الحساب ${error}`;
        }
    };

    return (
        <Modal isOpen={isOpen} className="w-full flex flex-col justify-center max-w-lg">
            <h3 className="font-bold text-lg mb-4 text-center">تسجيل غياب طالب</h3>

            <DynamicForm
                key={isOpen ? "open" : "closed"}
                template={[
                    { title: "اسم الطالب", key: "student_name", type: "autocomplete", fetchSuggestions: async (query: string) => await superAdminRPC.autocompleteStudent(query) },
                    { title: "ساعات الغياب", key: "hours_absent", type: "number" }
                ]}
                onSubmit={handleAddAbsentStudent}
                submitLabel="حفظ"
            />

            <button className="btn btn-ghost w-2xs mt-4" onClick={onClose}>إلغاء</button>
        </Modal>
    );
}



function MainContent(): JSX.Element {

    const params = useParams<{ attendance_record: string }>();
    if (!params.attendance_record) {
        throw "invalid attendance record"
    }

    const attendance_record_id = parseInt(params.attendance_record);

    const [data, setData] = useState<AbsentedData[]>([]);
    const [subjectName, setSubjectName] = useState<string>("");
    const [recordDate, setRecordDate] = useState<string>("");
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const fetchData = useCallback(() => {
        superAdminRPC.fetchAbsentStudents(attendance_record_id).then((data) => setData(data));
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
                <Options onAddClick={() => setIsAddModalOpen(true)} subjectName={subjectName} recordDate={recordDate} />
            </Subsection>
            <Subsection>

                <EditableTable
                    data={data || []}
                    headers={{ "student_name": "الاسم", "hours_absent": "عدد ساعات الغياب" }}
                />

            </Subsection>
        </Section>

        <AddAbsentStudentModal
            isOpen={isAddModalOpen}
            onClose={() => setIsAddModalOpen(false)}
            onSuccess={fetchData}
            record_id={attendance_record_id}
        />
    </>;

}



export function SuperAbsentedPage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"غيابات الطلاب"}
            sidebar={sidebar_pages}
        />
    </>
}
