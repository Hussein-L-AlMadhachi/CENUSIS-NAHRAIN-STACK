import { type JSX, useState, useEffect } from "react";
import { UserRoundPlus } from "lucide-react";

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
import { type teacherData, adminRPC } from "@/rpc";
import { sidebar_pages } from "./sidebar_pages";



interface OptionsProps {
    onAddClick: () => void;
}



function Options({ onAddClick }: OptionsProps) {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> إدارة حسابات الأساتذة </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

            <li>
                <button className="btn btn-lg" onClick={onAddClick}>
                    <UserRoundPlus size={18} /> إضافة حساب
                </button>
            </li>
        </ul>
    </div>;
}



interface AddTeacherModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
}

interface AddTeacherFormData {
    teacher_name?: string;
    password?: string;
}

interface TeacherUpdateFormData {
    id?: number;
    teacher_name?: string;
    password?: string;
}

function AddTeacherModal({ isOpen, onClose, onSuccess }: AddTeacherModalProps) {
    const handleAddTeacher = async (data: AddTeacherFormData) => {
        if (!data.teacher_name || !data.password) {
            throw "يجب ملئ جميع الحقول";
        }

        try {
            await adminRPC.registerTeacher({
                teacher_name: data.teacher_name,
                password: data.password
            });
            onSuccess();
            onClose();
        } catch (error) {
            throw `حدث خطأ أثناء إضافة الحساب ${error}`;
        }
    };

    return (
        <Modal isOpen={isOpen} className="w-full flex flex-col justify-center max-w-lg">
            <h3 className="font-bold text-lg mb-4 text-center">إضافة حساب تدريسي جديد</h3>

            <DynamicForm
                key={isOpen ? "open" : "closed"}
                template={[
                    { title: "الاسم الكامل", key: "teacher_name", type: "text" },
                    { title: "كلمة السر", key: "password", type: "text" }
                ]}
                onSubmit={handleAddTeacher}
                submitLabel="حفظ"
            />

            <button className="btn btn-ghost w-2xs mt-4" onClick={onClose}>إلغاء</button>
        </Modal>
    );
}


function MainContent(): JSX.Element {

    const [data, setData] = useState<teacherData[]>([]);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const fetchData = () => {
        adminRPC.fetchTeachers().then((data) => setData(data));
    };

    useEffect(() => {
        fetchData();
    }, []);


    return <>
        <Section>
            <Subsection>
                <Options onAddClick={() => setIsAddModalOpen(true)} />
            </Subsection>
            <Subsection>

                <EditableTable
                    data={data || []}
                    headers={{ "teacher_name": "الاسم", ":edit:": "" }}
                    onDelete={(id: number) => {
                        adminRPC.deleteUser(id).then(() => fetchData());
                    }}
                    onSave={(id: number, rowData: TeacherUpdateFormData) => {
                        adminRPC.updateUser(String(rowData.id ?? id), {
                            id: rowData.id ?? id,
                            teacher_name: rowData.teacher_name || "",
                            password: rowData.password
                        }).then(() => fetchData());
                    }}
                    formTemplate={[
                        { title: "الاسم الكامل", key: "teacher_name", type: "text" ,disabled:true},
                        { title: "كلمة السر", key: "password", type: "text" }
                    ]}
                />

            </Subsection>
        </Section>

        <AddTeacherModal
            isOpen={isAddModalOpen}
            onClose={() => setIsAddModalOpen(false)}
            onSuccess={fetchData}
        />
    </>;
}



export function TeachersPage(): JSX.Element {
    useValidRoute(["admin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"حسابات التدريسيين"}
            sidebar={sidebar_pages}
        />
    </>
}
