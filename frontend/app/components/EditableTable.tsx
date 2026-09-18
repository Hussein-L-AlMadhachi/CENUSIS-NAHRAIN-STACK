import { type JSX, useState, type FC } from "react";
import { DynamicForm, type DynamicFormTemplate } from "./DynamicForm";
import { Modal } from "./Modal";




type autocompleteFetchers = Record<string, (query: string) => Promise<string[]> | string[]>;


interface Props<T> {
    data: T[] | null | undefined;
    moreOptions?: FC<{ id: number }>;
    headers: Record<string, string>;
    className?: string;
    onSave?: (id: number, data: T) => Promise<void> | void;
    onDelete?: (id: number) => Promise<void> | void;
    autocomplete_fetchers?: autocompleteFetchers;
    formTemplate?: DynamicFormTemplate[];
    customRenderers?: Record<string, (row: T) => JSX.Element>;
}





export function EditableTable<T extends { id?: number }>(props: Props<T>) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editFormData, setEditFormData] = useState<Partial<T>>({});
    const [isModalOpen, setIsModalOpen] = useState(false);

    const handleEditClick = (row: T) => {
        setEditingId(row.id as number);
        setEditFormData({ ...row });
        if (props.formTemplate) {
            setIsModalOpen(true);
        }
    };

    const handleCancelClick = () => {
        setEditingId(null);
        setEditFormData({});
        setIsModalOpen(false);
    };

    const handleModalSubmit = async (data: any) => {
        if (editingId !== null && props.onSave) {
            await props.onSave(editingId, data);
            setEditingId(null);
            setEditFormData({});
            setIsModalOpen(false);
        }
    };

    const handleDeleteClick = async () => {
        if (editingId !== null && props.onDelete) {
            await props.onDelete(editingId);
            setEditingId(null);
            setEditFormData({});
            setIsModalOpen(false);
        }
    };

    const handleInputChange = (key: string, value: string) => {
        setEditFormData((prev) => ({
            ...prev,
            [key as keyof T]: value,
        }));
    };

    if (props.data === null) {
        return <div className={`overflow-x-auto h-[200px] rounded-2xl border border-base-content/5 p-4 min-w-4xs ${props.className ? props.className : ""}`} >
            <table className="table">

                <thead>
                    <tr>
                        <th><div className="skeleton h-4 w-[100px]"></div></th>
                        <th><div className="skeleton h-4 w-[100px]"></div></th>
                        <th><div className="skeleton h-4 w-[100px]"></div></th>
                        <th><div className="skeleton h-4 w-[100px]"></div></th>
                        <th><div className="skeleton h-4 w-[100px]"></div></th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                    <tr>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                    <tr>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                    <tr>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                </tbody>

            </table>
        </div>;
    }

    // indicate no data
    if (props.data === undefined) {
        return (
            <div className={`overflow-x-auto border border-base-content/5 p-4 min-w-4xs ${props.className ? props.className : ""}`}>
                <table className="table flex justify-center items-center">
                    <div className="text-3xl text-center"> لا يوجد معلومات لحد الآن </div>
                </table>
            </div>
        );
    }

    const table_body: JSX.Element[] = props.data.map((row_data) => {
        const isEditing = editingId === row_data.id && !props.formTemplate;

        return (
            <tr key={row_data.id}>
                {Object.keys(props.headers).map((header) => {

                    if (header === ":edit:") {
                        return (
                            <td key={header} className="flex gap-2 items-center w-43">
                                <button className="btn btn-xs" onClick={() => handleEditClick(row_data)}>
                                    تعديل
                                </button>
                            </td>
                        );
                    } else if (header.startsWith('@')) {
                        return (
                            <td className="w-fit" key={header}>
                                {props.customRenderers![header](row_data)}
                            </td>
                        );

                    }

                    return (
                        <td className="w-fit" key={header}>
                            {isEditing && header !== "id" ? (
                                <input
                                    type="text"
                                    className="input input-outline h-[25px] w-fit"
                                    value={(editFormData as any)[header] || ""}
                                    onChange={(e) => handleInputChange(header, e.target.value)}
                                />
                            ) : (
                                (row_data as any)[header]?.toString()
                            )}
                        </td>
                    );
                })}
            </tr>
        );
    });

    const table_headers: JSX.Element[] = Object.keys(props.headers).map(
        (header) => <th key={header} className="w-43"> {header === ":edit:" ? props.headers[":edit:"] : props.headers[header]} </th>
    );

    return (
        <div className={`overflow-x-auto rounded-box border border-base-content/5 bg-base-100 ${props.className ? props.className : ""}`}>
            <table className="table">
                {/* head */}
                <thead>
                    <tr>
                        {table_headers}
                    </tr>
                </thead>
                <tbody>
                    {table_body}
                </tbody>
            </table>

            {props.formTemplate && (
                <Modal isOpen={isModalOpen} className="w-full flex flex-col justify-center max-w-lg">
                    <h3 className="font-bold text-lg mb-4 text-center text-a">تعديل البيانات</h3>
                    <DynamicForm
                        key={isModalOpen ? "open" : "closed"}
                        template={props.formTemplate}
                        onSubmit={handleModalSubmit}
                        submitLabel="حفظ التعديلات"
                        initialData={editFormData}
                    />
                    <div className="grid grid-cols-[repeat(2,1fr)] grid-rows-[100px] gap-y-2.5 gap-x-2.5">
                        <button className="row-1 col-1 btn btn-outline mt-4" onClick={handleCancelClick}>إلغاء</button>
                        <button className="row-1 col-2 btn btn-error mt-4" onClick={handleDeleteClick}>حذف</button>
                    </div>
                </Modal>
            )}
        </div>
    );
}
