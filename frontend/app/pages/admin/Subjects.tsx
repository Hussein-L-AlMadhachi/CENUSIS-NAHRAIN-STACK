import { type JSX, useState, useEffect } from "react";
import { UserRoundPlus } from "lucide-react";

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
import { type SubjectData, type teacherData, type GradingSystemData, adminRPC } from "@/rpc";
import { useValidParams as validateParams } from "@/hooks/useValidParams";
import Tabs from "@/components/Tabs";
import { sidebar_pages } from "./sidebar_pages";



interface OptionsProps {
    onAddClick: () => void;
}



function Options({ onAddClick }: OptionsProps) {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> إدارة المواد الدراسية </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

            <li>
                <button className="btn btn-lg" onClick={onAddClick}>
                    <UserRoundPlus size={18} /> إضافة مادة
                </button>
            </li>


        </ul>
    </div>;
}



interface AddSubjectModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    subjectFormTemplate: DynamicFormTemplate[];
}

const getTeacherDisplayName = (teacher: teacherData & { teacher_name?: string; username?: string }): string =>
    teacher.teacher_name ?? teacher.teacher_name ?? teacher.username ?? "";

const buildSubjectFormTemplate = (teachers: teacherData[], gradingSystems: GradingSystemData[]): DynamicFormTemplate[] => {
    const teacherOptions = teachers
        .map((teacher) => getTeacherDisplayName(teacher))
        .filter((name) => name.length > 0)
        .map((name) => ({ label: name, value: name }));

    return [
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
            ]
        },
        { title: "عدد الساعات اسبوعياً", key: "hours_weekly", type: "number", min: 0 },
        {
            title: "التدريسي",
            key: "teacher_name",
            type: "select",
            options: teacherOptions
        },
        { title: "مختبر", key: "has_lab", type: "checkbox", defaultValue: false },
        {
            title: "تدريسي المختبر",
            key: "lab_teacher_name",
            type: "select",
            options: teacherOptions,
            condition: { key: "has_lab", value: true }
        },
        {
            title: "درجة المختبر",
            key: "max_lab_grade",
            type: "number",
            min: 0,
            condition: { key: "has_lab", value: true }
        },
        {
            title: "عدد ساعات المختبر اسبوعياً",
            key: "lab_weekly_hours",
            type: "number",
            min: 0,
            condition: { key: "has_lab", value: true }
        },
        {
            title: "تحسب درجة المختبر من:",
            key: "lab_grade_field",
            type: "select",
            options: (formData) => {
                const selectedGradingSystemName = String(formData.grading_system_name || "");
                const selectedSystem = gradingSystems.find((system) => system.name === selectedGradingSystemName);
                if (!selectedSystem) {
                    return [];
                }

                return selectedSystem.fields
                    .map((field) => field.field_name)
                    .filter((fieldName) => fieldName.length > 0)
                    .map((fieldName) => ({ label: fieldName, value: fieldName }));
            },
            condition: { key: "has_lab", value: true }
        },
    ];
};

function AddSubjectModal({ isOpen, onClose, onSuccess, subjectFormTemplate }: AddSubjectModalProps) {
    const handleAddSubject = async (data: SubjectData) => {

        try {
            validateParams(data as unknown as Record<string, unknown>, [
                "subject_name", "degree", "class", "total_hours", "hours_weekly",
                "semester", "teacher_name", "grading_system_name"]
            );
            if (data.degree !== "بكلوريوس") {
                data["class"] = 1;
            }

            if (!data.has_lab) {
                data.lab_teacher_name = undefined;
                data.max_lab_grade = undefined;
                data.lab_grade_field = undefined;
                data.lab_weekly_hours = 0;
            }

            await adminRPC.newSubject(data);
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
        adminRPC.filterSubjectsByClassDegree("بكلوريوس", 1).then((data) => setData_1st(data));
        adminRPC.filterSubjectsByClassDegree("بكلوريوس", 2).then((data) => setData_2nd(data));
        adminRPC.filterSubjectsByClassDegree("بكلوريوس", 3).then((data) => setData_3rd(data));
        adminRPC.filterSubjectsByClassDegree("بكلوريوس", 4).then((data) => setData_4th(data));

        adminRPC.filterSubjectsByDegree("ماجستير").then((data) => setData_master(data));

        adminRPC.filterSubjectsByDegree("دكتوراه").then((data) => setData_phd(data));

    };

    useEffect(() => {
        fetchData();
        adminRPC.fetchTeachers().then((data) => setTeachers(data));
        adminRPC.fetchGradingSystems().then((data) => setGradingSystems(data));
    }, []);

    const subjectFormTemplate = buildSubjectFormTemplate(teachers, gradingSystems);

    const handleUpdateSubject = async (id: number, data: Partial<SubjectData>) => {
        await adminRPC.updateSubject(id, data).then(() => fetchData());
    };

    const handleDeleteSubject = async (id: number) => {
        await adminRPC.deleteSubject(id).then(() => fetchData());
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
                    <a href={`/admin/enrolled/${row.teacher}/${row.id}`} className="btn btn-xs  w-32">
                        إدارة الطلاب
                    </a>
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




export function SubjectsPage(): JSX.Element {
    useValidRoute(["admin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"المواد الدراسية"}
            sidebar={sidebar_pages}
        />
    </>
}
