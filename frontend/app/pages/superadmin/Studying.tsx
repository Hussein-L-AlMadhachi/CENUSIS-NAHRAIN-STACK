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
import { type EnrollmentData, superAdminRPC, type studentData } from "@/rpc";
import { Link, useParams } from "wouter";
import { sidebar_pages } from "./sidebar_pages";






interface OptionsProps {
    onAddClick: () => void;
    subjectName: string;
}



function Options({ onAddClick, subjectName }: OptionsProps) {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href="/superadmin/subjects" className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> {subjectName ? `طلاب مادة ${subjectName}` : "الطلبة المسجلين"} </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

            <li>
                <button className="btn" onClick={onAddClick}>
                    <UserRoundPlus size={18} /> تسجيل طالب
                </button>
            </li>
        </ul>
    </div>;
}



interface EnrollModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    subjectId: number;
    teacherId: number;
}

interface EnrollFormData {
    student_name?: string;
    studying_year?: number | string;
    exam_retakes?: number | string;
    semester_retakes?: number | string;
    hours_missed?: number | string;
}

function EnrollModal({ isOpen, onClose, onSuccess, subjectId, teacherId }: EnrollModalProps) {
    const handleAddEnrollment = async (data: EnrollFormData) => {
        if (!data.student_name) {
            throw "يجب اختيار طالب";
        }

        try {
            // Find student by name to get ID
            const student: studentData = await superAdminRPC.findStudentByName(data.student_name);
            console.log(">>>", student);
            if (!student || !student.id) {
                throw "الطالب غير موجود";
            }

            const enrollmentData: EnrollmentData = {
                subject_id: subjectId,
                student_id: student.id,
                teacher_id: teacherId,
                student_name: student.name,

                // Required fields by backend with defaults for new enrollment
                studying_year: Number(data.studying_year),
                hours_missed: Number(data.hours_missed || 0),
            };

            await superAdminRPC.newEnrollment(enrollmentData);
            onSuccess();
            onClose();
        } catch (error) {
            throw `حدث خطأ أثناء إضافة الطالب: ${error}`;
        }
    };

    return (
        <Modal isOpen={isOpen} className="w-full flex flex-col justify-center max-w-lg">
            <h3 className="font-bold text-lg mb-4 text-center">تسجيل طالب في المادة</h3>

            <DynamicForm
                key={isOpen ? "open" : "closed"}
                template={[
                    {
                        title: "اسم الطالب",
                        key: "student_name",
                        type: "autocomplete",
                        fetchSuggestions: (q) => superAdminRPC.autocompleteStudent(q)
                    },
                    {
                        title: "السنة الدراسية",
                        key: "studying_year",
                        type: "number",
                        defaultValue: new Date().getFullYear()
                    }
                ]}
                onSubmit={handleAddEnrollment}
                submitLabel="إضافة"
            />

            <button className="btn btn-ghost w-2xs mt-4" onClick={onClose}>إلغاء</button>
        </Modal>
    );
}


function MainContent(): JSX.Element {
    const params = useParams();
    const subjectId = parseInt(params.id || "0", 10);
    const teacherId = parseInt(params.teacher || "0", 10);

    const [data, setData] = useState<EnrollmentData[]>([]);
    const [subjectName, setSubjectName] = useState<string>("");
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const fetchData = useCallback(() => {
        if (subjectId) {
            superAdminRPC.fetchEnrollmentsForSubject(subjectId).then((data) => {
                setData(data);
            });
            superAdminRPC.fetchSingleSubject(subjectId).then((subject) => {
                setSubjectName((subject as any).subject_name || subject.name);
            });
        }
    }, [subjectId]);

    useEffect(() => {
        fetchData();
    }, [fetchData, subjectId]);

    return <>
        <Section>
            <Subsection>
                <Options onAddClick={() => setIsAddModalOpen(true)} subjectName={subjectName} />
            </Subsection>
            <Subsection>
                <EditableTable
                    data={data || []}
                    headers={{
                        "student_name": "اسم الطالب",
                        "hours_missed": "عدد ساعات الغياب",
                        "studying_year": "سنة",
                        ":edit:": ""
                    }}
                    onDelete={(id: number) => {
                        superAdminRPC.deleteEnrollment(id).then(() => fetchData());
                    }}
                    onSave={async (id: number, form_data: EnrollFormData) => {
                        try {
                            const updates: Partial<EnrollmentData> = {
                                ...form_data,
                                studying_year: form_data.studying_year !== undefined ? Number(form_data.studying_year) : undefined,
                                hours_missed: form_data.hours_missed !== undefined ? Number(form_data.hours_missed) : undefined,
                                teacher_id: teacherId,
                                subject_id: subjectId
                            };

                            // If name changed, we need to find the new ID
                            if (form_data.student_name) {
                                const student = await superAdminRPC.findStudentByName(form_data.student_name);
                                if (student && student.id) {
                                    updates.student_id = student.id;
                                    updates.student_name = student.name;
                                }
                            }
                            await superAdminRPC.updateEnrollment(id, updates);
                            fetchData();
                        } catch (e) {
                            console.error("Update failed", e);
                            alert(`حدث خطأ أثناء التعديل: ${e}`);
                        }
                    }}
                    formTemplate={[
                        {
                            title: "اسم الطالب",
                            key: "student_name",
                            type: "autocomplete",
                            fetchSuggestions: (q) => superAdminRPC.autocompleteStudent(q)
                        },
                        { title: "السنة الدراسية", key: "studying_year", type: "number" }
                    ]}
                />
            </Subsection>
        </Section>

        <EnrollModal
            isOpen={isAddModalOpen}
            onClose={() => setIsAddModalOpen(false)}
            onSuccess={fetchData}
            subjectId={subjectId}
            teacherId={teacherId}
        />
    </>;
}



export function SuperStudyingPage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"الطلبة المسجلين"}
            sidebar={sidebar_pages}
        />
    </>
}
