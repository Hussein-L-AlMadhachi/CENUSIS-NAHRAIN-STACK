import { type JSX, useState, useEffect } from "react";
import { UserRoundPlus, Download } from "lucide-react";

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
import { type SubjectData, type GradingSystemData, type teacherData, superAdminRPC } from "@/rpc";
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
    const handleAddSubject = async (data: SubjectData) => {

        try {
            validateParams(data as unknown as Record<string, unknown>, [
                "subject_name", "degree", "class", "total_hours", "hours_weekly",
                "semester", "teacher_name", "grading_system_name"]
            );
            if (data.degree !== "بكلوريوس") {
                data["class"] = 1;
            }

            await superAdminRPC.newSubject(data);
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

    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const fetchData = () => {
        superAdminRPC.filterSubjectsByClassDegree("بكلوريوس", 1).then((data) => setData_1st(data));
        superAdminRPC.filterSubjectsByClassDegree("بكلوريوس", 2).then((data) => setData_2nd(data));
        superAdminRPC.filterSubjectsByClassDegree("بكلوريوس", 3).then((data) => setData_3rd(data));
        superAdminRPC.filterSubjectsByClassDegree("بكلوريوس", 4).then((data) => setData_4th(data));

        superAdminRPC.filterSubjectsByDegree("ماجستير").then((data) => setData_master(data));

        superAdminRPC.filterSubjectsByDegree("دكتوراه").then((data) => setData_phd(data));

    };

    useEffect(() => {
        fetchData();
    }, []);

    const handleUpdateSubject = async (id: number, data: Partial<SubjectData>) => {
        await superAdminRPC.updateSubject(id, data).then(() => fetchData());
    };

    const handleDeleteSubject = async (id: number) => {
        await superAdminRPC.deleteSubject(id).then(() => fetchData());
    };

    const [gradingSystems, setGradingSystems] = useState<GradingSystemData[]>([]);
    const [selectedGradingSystem, setSelectedGradingSystem] = useState<string>('');

    useEffect(() => {
        superAdminRPC.fetchTeachers().then((data) => setTeachers(data));
        superAdminRPC.fetchGradingSystems().then((data) => {
            setGradingSystems(data);
            if (data.length > 0 && data[0].name) {
                setSelectedGradingSystem(data[0].name);
            }
        });
    }, []);

    const subjectFormTemplate = buildSubjectFormTemplate(teachers, gradingSystems);

    const handleExportGrades = (degree: string, classNumber: number) => {
        if (!selectedGradingSystem) return;
        window.open(
            `/api/grades/export?degree=${encodeURIComponent(degree)}&class=${classNumber}&grading_system=${encodeURIComponent(selectedGradingSystem)}`,
            '_blank'
        );
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
                    <a href={`/superadmin/enrolled/${row.teacher}/${row.id}`} className="btn btn-xs  w-32">
                        إدارة الطلاب
                    </a>
                    <a href={`/superadmin/attendance/${row.id}`} className="btn btn-xs w-32">
                        إدارة الغياب
                    </a>
                    <a href={`/superadmin/grades/${row.id}`} className="btn btn-xs w-32">
                        إدارة الدرجات
                    </a>
                </div>
            )
        },
    }

    const ExportBar = ({ degree, classNumber }: { degree: string; classNumber: number }) => (
        <div className="flex justify-end items-center gap-2">
            <select
                className="select select-sm select-bordered"
                value={selectedGradingSystem}
                onChange={(e) => setSelectedGradingSystem(e.target.value)}
            >
                {gradingSystems.length === 0 && (
                    <option value="">لا توجد أنظمة درجات</option>
                )}
                {gradingSystems.map((gs) => (
                    <option key={gs.id} value={gs.name}>{gs.name}</option>
                ))}
            </select>
            <button
                className="btn btn-sm"
                onClick={() => handleExportGrades(degree, classNumber)}
                disabled={!selectedGradingSystem}
            >
                <Download size={16} /> تصدير الدرجات
            </button>
        </div>
    );

    return <>
        <Section>
            <Subsection>
                <Options onAddClick={() => setIsAddModalOpen(true)} />
            </Subsection>
            <Subsection>
                <Tabs className="w-full" group="students" tabs={
                    [
                        {
                            label: "المرحلة الأولى", content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={1} />
                                <EditableTable
                                    data={data_1st || []}
                                    headers={table_headers}
                                    onDelete={handleDeleteSubject}
                                    onSave={handleUpdateSubject}
                                    formTemplate={subjectFormTemplate}
                                    customRenderers={customRenderers}
                                />
                            </div>
                        },
                        {
                            label: "المرحلة الثانية", content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={2} />
                                <EditableTable
                                    data={data_2nd || []}
                                    headers={table_headers}
                                    onDelete={handleDeleteSubject}
                                    onSave={handleUpdateSubject}
                                    formTemplate={subjectFormTemplate}
                                    customRenderers={customRenderers}
                                />
                            </div>
                        },
                        {
                            label: "المرحلة الثالثة", content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={3} />
                                <EditableTable
                                    data={data_3rd || []}
                                    headers={table_headers}
                                    onDelete={handleDeleteSubject}
                                    onSave={handleUpdateSubject}
                                    formTemplate={subjectFormTemplate}
                                    customRenderers={customRenderers}
                                />
                            </div>
                        },
                        {
                            label: "المرحلة الرابعة", content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={4} />
                                <EditableTable
                                    data={data_4th || []}
                                    headers={table_headers}
                                    onDelete={handleDeleteSubject}
                                    onSave={handleUpdateSubject}
                                    formTemplate={subjectFormTemplate}
                                    customRenderers={customRenderers}
                                />
                            </div>
                        },
                        {
                            label: "الماجستير", content: <div className="flex flex-col gap-4">
                                <ExportBar degree="ماجستير" classNumber={1} />
                                <EditableTable
                                    data={data_master || []}
                                    headers={table_headers}
                                    onDelete={handleDeleteSubject}
                                    onSave={handleUpdateSubject}
                                    formTemplate={subjectFormTemplate}
                                    customRenderers={customRenderers}
                                />
                            </div>
                        },
                        {
                            label: "الدكتوراه", content: <div className="flex flex-col gap-4">
                                <ExportBar degree="دكتوراه" classNumber={1} />
                                <EditableTable
                                    data={data_phd || []}
                                    headers={table_headers}
                                    onDelete={handleDeleteSubject}
                                    onSave={handleUpdateSubject}
                                    formTemplate={subjectFormTemplate}
                                    customRenderers={customRenderers}
                                />
                            </div>
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




export function SuperSubjectsPage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"المواد الدراسية"}
            sidebar={sidebar_pages}
        />
    </>
}
