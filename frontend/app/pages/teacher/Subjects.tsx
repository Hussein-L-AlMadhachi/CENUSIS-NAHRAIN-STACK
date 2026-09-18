import { type JSX, useState, useEffect } from "react";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Modal } from "@/components/Modal";
import { DynamicForm, type DynamicFormTemplate } from "@/components/DynamicForm";
import { Section, Subsection } from "@/components/Section";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type SubjectData, type teacherData, type GradingSystemData, teacherRPC } from "@/rpc";
import { useValidParams as validateParams } from "@/hooks/useValidParams";
import Tabs from "@/components/Tabs";
import { sidebar_pages } from "./sidebar_pages";


interface AddSubjectModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    subjectFormTemplate: DynamicFormTemplate[];
}

const getTeacherDisplayName = (teacher: teacherData & { teacher_name?: string; username?: string }): string =>
    teacher.teacher_name ?? teacher.teacher_name ?? teacher.username ?? "";

const buildSubjectFormTemplate = (teachers: teacherData[], gradingSystems: GradingSystemData[]): DynamicFormTemplate[] => [
    { title: "اسم المادة", key: "subject_name", type: "text" },
    {
        title: "نظام الدرجات",
        key: "grading_system_name",
        type: "select",
        options: gradingSystems.map((system) => ({ label: system.name, value: system.name }))
    },
    {
        title: "الدرجة العلمية", key: "degree",
        type: "select", options: [
            { label: "بكلوريوس", value: "بكلوريوس" },
            { label: "ماجستير", value: "ماجستير" },
            { label: "دكتوراه", value: "دكتوراه" }
        ]
    },
    {
        title: "المرحلة", key: "class", type: "select", options: [
            { label: "الأولى", value: 1 },
            { label: "الثانية", value: 2 },
            { label: "الثالثة", value: 3 },
            { label: "الرابعة", value: 4 }
        ], condition: { key: "degree", value: "بكلوريوس" }
    },
    {
        title: "الكورس", key: "semester", type: "select", options: [
            { label: "الأول", value: 1 },
            { label: "الثاني", value: 2 },
        ], condition: { key: "degree", value: "بكلوريوس" }
    },
    { title: "عدد الساعات اسبوعياً", key: "hours_weekly", type: "number", min: 0 },
    {
        title: "التدريسي",
        key: "teacher_name",
        type: "select",
        options: teachers
            .map((teacher) => getTeacherDisplayName(teacher))
            .filter((name) => name.length > 0)
            .map((name) => ({ label: name, value: name }))
    },
];

function AddSubjectModal({ isOpen, onClose, onSuccess, subjectFormTemplate }: AddSubjectModalProps) {
    const handleAddSubject = async (data: SubjectData & Record<string, unknown>) => {

        try {
            validateParams(data, [
                "subject_name", "degree", "class", "total_hours", "hours_weekly",
                "semester", "teacher_name", "grading_system_name"]
            );
            if (data.degree !== "بكلوريوس") {
                data.class = 1;
            }

            await teacherRPC.newSubject(data);
            onSuccess();
            onClose();
        } catch (error) {
            throw `حدث خطأ أثناء إضافة المادة: ${error}`;
        }
    };

    return (
        <Modal isOpen={isOpen} className="w-full flex flex-col justify-center max-w-lg">
            <h3 className="font-bold text-lg mb-4 text-center">إضافة مادة جديدة</h3>

            <DynamicForm
                key={isOpen ? "open" : "closed"}
                template={subjectFormTemplate}
                onSubmit={handleAddSubject}
                submitLabel="حفظ"
            />

            <button className="btn btn-ghost w-2xs mt-4" onClick={onClose}>إلغاء</button>
        </Modal>
    );
}


function MainContent(): JSX.Element {

    const [data_1st, setData_1st] = useState<SubjectData[]>([]);
    const [data_2nd, setData_2nd] = useState<SubjectData[]>([]);
    const [data_3rd, setData_3rd] = useState<SubjectData[]>([]);
    const [data_4th, setData_4th] = useState<SubjectData[]>([]);
    const [data_master, setData_master] = useState<SubjectData[]>([]);
    const [data_phd, setData_phd] = useState<SubjectData[]>([]);
    const [teachers, setTeachers] = useState<teacherData[]>([]);
    const [gradingSystems, setGradingSystems] = useState<GradingSystemData[]>([]);

    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const fetchData = () => {
        teacherRPC.fetchSubjectsByTeacher("بكلوريوس", 1).then((data) => setData_1st(data));
        teacherRPC.fetchSubjectsByTeacher("بكلوريوس", 2).then((data) => setData_2nd(data));
        teacherRPC.fetchSubjectsByTeacher("بكلوريوس", 3).then((data) => setData_3rd(data));
        teacherRPC.fetchSubjectsByTeacher("بكلوريوس", 4).then((data) => setData_4th(data));

        teacherRPC.fetchSubjectsByTeacher("ماجستير").then((data) => setData_master(data));

        teacherRPC.fetchSubjectsByTeacher("دكتوراه").then((data) => setData_phd(data));

    };

    useEffect(() => {
        fetchData();
        teacherRPC.fetchTeachers().then((data) => setTeachers(data));
        teacherRPC.fetchGradingSystems().then((data) => setGradingSystems(data));
    }, []);

    const subjectFormTemplate = buildSubjectFormTemplate(teachers, gradingSystems);

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
        "semester": "الكورس", "@view_students": "",
        ":edit:": ""
    }

    const customRenderers: Record<string, (row: SubjectData) => JSX.Element> = {
        "@view_students": (row: SubjectData) => {

            return (
                <div className="flex flex-col flex-nowrap gap-1">
                    <a href={`/teacher/enrolled/${row.teacher}/${row.id}`} className="btn btn-xs  w-32">
                        عرض الطلاب
                    </a>
                    <a href={`/teacher/attendance/${row.id}`} className="btn btn-xs w-32">
                        إدارة الغياب
                    </a>
                    <a href={`/teacher/grades/${row.id}`} className="btn btn-xs w-32">
                      إدارة الدرجات
                    </a>
                </div>
            )
        },
    }

    return <>
        <Section>
            <Subsection>
                <Tabs group="students" tabs={
                    [
                        {
                            label: "المرحلة الأولى", content: <EditableTable
                                data={data_1st || []}
                                headers={table_headers}
                                onDelete={handleDeleteSubject}
                                onSave={handleUpdateSubject}
                                formTemplate={subjectFormTemplate}
                                customRenderers={customRenderers}
                            />
                        },
                        {
                            label: "المرحلة الثانية", content: <EditableTable
                                data={data_2nd || []}
                                headers={table_headers}
                                onDelete={handleDeleteSubject}
                                onSave={handleUpdateSubject}
                                formTemplate={subjectFormTemplate}
                                customRenderers={customRenderers}
                            />
                        },
                        {
                            label: "المرحلة الثالثة", content: <EditableTable
                                data={data_3rd || []}
                                headers={table_headers}
                                onDelete={handleDeleteSubject}
                                onSave={handleUpdateSubject}
                                formTemplate={subjectFormTemplate}
                                customRenderers={customRenderers}
                            />
                        },
                        {
                            label: "المرحلة الرابعة", content: <EditableTable
                                data={data_4th || []}
                                headers={table_headers}
                                onDelete={handleDeleteSubject}
                                onSave={handleUpdateSubject}
                                formTemplate={subjectFormTemplate}
                                customRenderers={customRenderers}
                            />
                        },
                        {
                            label: "الماجستير", content: <EditableTable
                                data={data_master || []}
                                headers={table_headers}
                                onDelete={handleDeleteSubject}
                                onSave={handleUpdateSubject}
                                formTemplate={subjectFormTemplate}
                                customRenderers={customRenderers}
                            />
                        },
                        {
                            label: "الدكتوراه", content: <EditableTable
                                data={data_phd || []}
                                headers={table_headers}
                                onDelete={handleDeleteSubject}
                                onSave={handleUpdateSubject}
                                formTemplate={subjectFormTemplate}
                                customRenderers={customRenderers}
                            />
                        }
                    ]
                } />
            </Subsection>
        </Section>

        <AddSubjectModal
            isOpen={isAddModalOpen}
            onClose={() => setIsAddModalOpen(false)}
            onSuccess={fetchData}
            subjectFormTemplate={subjectFormTemplate}
        />
    </>;
}


///


export function TeachersSubjectsPage(): JSX.Element {
    useValidRoute(["teacher"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"المواد الدراسية"}
            sidebar={sidebar_pages}
        />
    </>
}
