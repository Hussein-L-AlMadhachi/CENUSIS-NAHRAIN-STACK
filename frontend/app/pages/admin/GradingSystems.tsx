import { type JSX, useEffect, useState } from "react";
import { UserRoundPlus } from "lucide-react";

import { MainLayout } from "@/layout/MainLayout";

import { EditableTable } from "@/components/EditableTable";
import { Modal } from "@/components/Modal";
import { DynamicForm } from "@/components/DynamicForm";
import { Section, Subsection } from "@/components/Section";

import { useValidRoute } from "@/hooks/useValidRoute";

import {
    type GradingSystemData,
    type GradingSystemFieldData,
    adminRPC
} from "@/rpc";
import { sidebar_pages } from "./sidebar_pages";


interface OptionsProps {
    onAddClick: () => void;
}

function Options({ onAddClick }: OptionsProps) {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> إدارة أنظمة الدرجات </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">

            <li>
                <button className="btn btn-lg" onClick={onAddClick}>
                    <UserRoundPlus size={18} /> إضافة نظام درجات
                </button>
            </li>
        </ul>
    </div>;
}

interface AddGradingSystemModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
}

function AddGradingSystemModal({ isOpen, onClose, onSuccess }: AddGradingSystemModalProps) {
    const handleAddGradingSystem = async (data: { name?: string, fields?: GradingSystemFieldData[] }) => {
        const name = data.name?.trim();
        const fields = data.fields;

        if (!name || !Array.isArray(fields) || fields.length === 0) {
            throw "يجب ملئ جميع الحقول";
        }

        await adminRPC.newGradingSystem({ name, fields });
        onSuccess();
        onClose();
    };

    return (
        <Modal isOpen={isOpen} className="w-full flex flex-col justify-center max-w-lg">
            <h3 className="font-bold text-lg mb-4 text-center">إضافة نظام درجات جديد</h3>

            <DynamicForm
                key={isOpen ? "open" : "closed"}
                template={[
                    { title: "اسم النظام", key: "name", type: "text" },
                    {
                        title: "الحقول",
                        key: "fields",
                        type: "subform",
                        addLabel: "إضافة حقل",
                        subformTemplate: [
                            { title: "اسم الحقل", key: "field_name", type: "text" },
                            { title: "الحد الأدنى", key: "min_grade", type: "number", min: 0 },
                            { title: "الحد الأعلى", key: "max_grade", type: "number", min: 0 }
                        ]
                    }
                ]}
                onSubmit={handleAddGradingSystem}
                submitLabel="حفظ"
            />

            <button className="btn btn-ghost w-2xs mt-4" onClick={onClose}>إلغاء</button>
        </Modal>
    );
}

function MainContent(): JSX.Element {
    const [data, setData] = useState<GradingSystemData[]>([]);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const fetchData = () => {
        adminRPC.fetchGradingSystems().then((result) => setData(result));
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
                    data={data}
                    headers={{ "name": "اسم النظام", "@fields": "الحقول", ":edit:": "" }}
                    customRenderers={{
                        "@fields": (row) => {
                            const fields = row.fields || [];
                            return <div className="overflow-x-auto">
                                <table className="table table-xs">
                                    <thead>
                                        <tr>
                                            <th>الحقل</th>
                                            <th>درجة النجاح</th>
                                            <th>الدرجة الكاملة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {fields.map((field, index) => (
                                            <tr key={`field-${index}`}>
                                                <td>{field.field_name}</td>
                                                <td>{field.min_grade}</td>
                                                <td>{field.max_grade}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>;
                        }
                    }}
                    onDelete={(id: number) => {
                        adminRPC.deleteGradingSystem(id).then(() => fetchData());
                    }}
                    onSave={(id: number, row: GradingSystemData) => {
                        const name = row.name?.trim();
                        if (!name || !Array.isArray(row.fields) || row.fields.length === 0) {
                            throw "الاسم وحقول النظام مطلوبة";
                        }

                        adminRPC.updateGradingSystem(id, {
                            name,
                            fields: row.fields
                        }).then(() => fetchData());
                    }}
                    formTemplate={[
                        { title: "اسم النظام", key: "name", type: "text" },
                        {
                            title: "الحقول",
                            key: "fields",
                            type: "subform",
                            addLabel: "إضافة حقل",
                            subformTemplate: [
                                { title: "اسم الحقل", key: "field_name", type: "text" },
                                { title: "درجة النجاح", key: "min_grade", type: "number", min: 0 },
                                { title: "الدرجة الكاملة", key: "max_grade", type: "number", min: 0 }
                            ]
                        }
                    ]}
                />
            </Subsection>
        </Section>

        <AddGradingSystemModal
            isOpen={isAddModalOpen}
            onClose={() => setIsAddModalOpen(false)}
            onSuccess={fetchData}
        />
    </>;
}

export function GradingSystemsPage(): JSX.Element {
    useValidRoute(["admin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"أنظمة الدرجات"}
            sidebar={sidebar_pages}
        />
    </>;
}