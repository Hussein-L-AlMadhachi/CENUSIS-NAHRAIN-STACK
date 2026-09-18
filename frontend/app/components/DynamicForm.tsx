import { useState, useEffect } from "react";
import { AutocompleteText } from "./AutocompleteText";



type SelectOption = { label: string; value: any };

export interface DynamicFormTemplate {
    title: string;
    key?: string;
    type: "text" | "number" | "checkbox" | "select" | "autocomplete" | (string & {});
    fieldsCheckbox?: string[];
    options?: SelectOption[] | ((formData: Record<string, any>) => SelectOption[]);
    fetchSuggestions?: (query: string) => Promise<string[]> | string[];
    condition?: {
        key: string;
        value: any;
    };
    defaultValue?: any;
    min?: number;
    max?: number;
    disabled?: boolean;
    subformTemplate?: DynamicFormTemplate[];
    addLabel?: string;
}



interface DynamicFormProps {
    template: DynamicFormTemplate[];
    onSubmit: (data: any) => void | Promise<void>;
    submitLabel: string;
    initialData?: any;
    customComponents?: Record<string, React.ComponentType<{
        value: any;
        onChange: (val: any) => void;
        field: DynamicFormTemplate;
        disabled?: boolean;
    }>>;
}



export function DynamicForm(props: DynamicFormProps) {

    const [is_loading, setIsLoading] = useState<boolean>(false);
    const [error_msg, setErrorMsg] = useState<string>("");
    const [data, setData] = useState<any>(props.initialData || {});

    useEffect(() => {
        let hasChanges = false;
        const newData = { ...data };

        props.template.forEach((field) => {
            if (field.condition && data[field.condition.key] !== field.condition.value) {
                const key = field.key || field.title;
                if (newData[key] !== field.defaultValue) {
                    newData[key] = field.defaultValue;
                    hasChanges = true;
                }
            }
        });

        if (hasChanges) {
            setData(newData);
        }
    }, [data, props.template]);

    const handleChange = (key: string, value: any) => {
        setData((prev: any) => ({ ...prev, [key]: value }));
    };

    const getDefaultSubformRow = (template: DynamicFormTemplate[]) => {
        const row: Record<string, any> = {};

        template.forEach((subfield) => {
            const key = subfield.key || subfield.title;

            if (subfield.defaultValue !== undefined) {
                row[key] = subfield.defaultValue;
                return;
            }

            if (subfield.type === "number") {
                row[key] = 0;
                return;
            }

            row[key] = "";
        });

        return row;
    };

    const updateSubformRow = (fieldKey: string, rowIndex: number, key: string, value: any) => {
        const currentRows = Array.isArray(data[fieldKey]) ? [...data[fieldKey]] : [];
        currentRows[rowIndex] = {
            ...(currentRows[rowIndex] || {}),
            [key]: value
        };
        handleChange(fieldKey, currentRows);
    };

    const addSubformRow = (fieldKey: string, template: DynamicFormTemplate[]) => {
        const currentRows = Array.isArray(data[fieldKey]) ? [...data[fieldKey]] : [];
        currentRows.push(getDefaultSubformRow(template));
        handleChange(fieldKey, currentRows);
    };

    const removeSubformRow = (fieldKey: string, rowIndex: number) => {
        const currentRows = Array.isArray(data[fieldKey]) ? [...data[fieldKey]] : [];
        currentRows.splice(rowIndex, 1);
        handleChange(fieldKey, currentRows);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        try {
            await props.onSubmit(data);
        } catch (e: any) {
            setErrorMsg(e.toString());
            setTimeout(() => {
                setErrorMsg("");
            }, 10000);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="p-4 w-full">
            <p className="text-right text-xs mt-2 pr-1.5 text-error">{error_msg ? error_msg : ""}</p>

            <form onSubmit={handleSubmit}>
                {props.template.map((field, i) => {
                    if (field.condition && data[field.condition.key] !== field.condition.value) {
                        return null;
                    }
                    const CustomComponent = props.customComponents?.[field.type];
                    const selectOptions: SelectOption[] | undefined = field.type === "select"
                        ? (typeof field.options === "function" ? field.options(data as Record<string, any>) : (field.options ?? []))
                        : undefined;

                    return (
                        <fieldset className="fieldset p-1" key={i}>
                            <legend className="fieldset-legend text-gray-500 text-sm">{field.title}</legend>
                            <div className="flex flex-col flex-nowrap gap-2">
                                {field.type === "text" && (
                                    <input
                                        type="text"
                                        className="input input-bordered w-full"
                                        value={data[field.key || field.title] ?? ""}
                                        onChange={(e) => handleChange(field.key || field.title, e.target.value)}
                                        disabled={field.disabled}
                                    />
                                )}
                                {field.type === "number" && (
                                    <input
                                        type="number"
                                        className="input input-bordered w-full"
                                        value={data[field.key || field.title] ?? ""}
                                        min={field.min}
                                        max={field.max}
                                        onChange={(e) => handleChange(field.key || field.title, Number(e.target.value))}
                                        disabled={field.disabled} />
                                )}
                                {field.type === "checkbox" && (
                                    <>
                                        {field.fieldsCheckbox ? (
                                            <div className="flex gap-4">
                                                {field.fieldsCheckbox.map((option, j) => (
                                                    <div key={j} className="flex items-center gap-2">
                                                        <input
                                                            type="checkbox"
                                                            className="checkbox"
                                                            checked={((data[field.key || field.title] as string[]) || []).includes(option)}
                                                            onChange={(e) => {
                                                                const key = field.key || field.title;
                                                                const currentValues = (data[key] as string[]) || [];
                                                                if (e.target.checked) {
                                                                    handleChange(key, [...currentValues, option]);
                                                                } else {
                                                                    handleChange(
                                                                        key,
                                                                        currentValues.filter((v) => v !== option)
                                                                    );
                                                                }
                                                            }}
                                                            disabled={field.disabled}
                                                        />
                                                        <span>{option}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    className="checkbox"
                                                    checked={Boolean(data[field.key || field.title])}
                                                    onChange={(e) => handleChange(field.key || field.title, e.target.checked)}
                                                    disabled={field.disabled}
                                                />
                                            </div>
                                        )}
                                    </>
                                )}
                                {field.type === "select" && selectOptions && (
                                    <select
                                        className="select select-bordered w-full"
                                        value={data[field.key || field.title] ?? ""}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            const originalOption = selectOptions.find((opt) => String(opt.value) === val);
                                            handleChange(field.key || field.title, originalOption ? originalOption.value : val);
                                        }}
                                        disabled={field.disabled}
                                    >
                                        <option disabled value="">
                                            اختر {field.title}
                                        </option>
                                        {selectOptions.map((opt, k) => (
                                            <option key={k} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {field.type === "autocomplete" && field.fetchSuggestions && (
                                    <AutocompleteText
                                        fetchSuggestions={field.fetchSuggestions}
                                        onSelect={(val) => handleChange(field.key || field.title, val)}
                                        onChange={(val) => handleChange(field.key || field.title, val)}
                                        value={data[field.key || field.title] ?? ""}
                                        placeholder={`ابحث عن ${field.title}`}
                                        className="w-full"
                                        ghost={false}
                                        expand={true}
                                        disabled={field.disabled}
                                    />
                                )}
                                {field.type === "subform" && field.subformTemplate && (
                                    <div className="w-full rounded-box border border-base-content/10 p-3 space-y-3">
                                        <div className="overflow-x-auto">
                                            <table className="table table-xs">
                                                <thead>
                                                    <tr>
                                                        {field.subformTemplate.map((subfield, subfieldIndex) => (
                                                            <th key={`${field.key || field.title}-header-${subfieldIndex}`}>{subfield.title}</th>
                                                        ))}
                                                        <th className="w-14"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {(Array.isArray(data[field.key || field.title]) ? data[field.key || field.title] : []).map((row: any, rowIndex: number) => (
                                                        <tr key={`${field.key || field.title}-row-${rowIndex}`}>
                                                            {field.subformTemplate!.map((subfield, subfieldIndex) => {
                                                                const subKey = subfield.key || subfield.title;
                                                                const value = row?.[subKey] ?? "";

                                                                if (subfield.type === "number") {
                                                                    return <td key={`${field.key || field.title}-cell-${rowIndex}-${subfieldIndex}`}>
                                                                        <input
                                                                            type="number"
                                                                            className="input input-bordered input-xs w-24"
                                                                            min={subfield.min}
                                                                            max={subfield.max}
                                                                            value={value}
                                                                            onChange={(e) => updateSubformRow(field.key || field.title, rowIndex, subKey, Number(e.target.value))}
                                                                            disabled={field.disabled || subfield.disabled}
                                                                        />
                                                                    </td>;
                                                                }

                                                                if (subfield.type === "select" && subfield.options) {
                                                                    const subfieldOptions = typeof subfield.options === "function"
                                                                        ? subfield.options(data as Record<string, any>)
                                                                        : subfield.options;
                                                                    return <td key={`${field.key || field.title}-cell-${rowIndex}-${subfieldIndex}`}>
                                                                        <select
                                                                            className="select select-bordered select-xs w-28"
                                                                            value={value}
                                                                            onChange={(e) => {
                                                                                const selected = subfieldOptions.find((opt) => String(opt.value) === e.target.value);
                                                                                updateSubformRow(field.key || field.title, rowIndex, subKey, selected ? selected.value : e.target.value);
                                                                            }}
                                                                            disabled={field.disabled || subfield.disabled}
                                                                        >
                                                                            {subfieldOptions.map((opt, k) => (
                                                                                <option key={k} value={opt.value}>{opt.label}</option>
                                                                            ))}
                                                                        </select>
                                                                    </td>;
                                                                }

                                                                return <td key={`${field.key || field.title}-cell-${rowIndex}-${subfieldIndex}`}>
                                                                    <input
                                                                        type="text"
                                                                        className="input input-bordered input-xs w-36"
                                                                        value={value}
                                                                        onChange={(e) => updateSubformRow(field.key || field.title, rowIndex, subKey, e.target.value)}
                                                                        disabled={field.disabled || subfield.disabled}
                                                                    />
                                                                </td>;
                                                            })}
                                                            <td>
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-xs btn-error btn-outline"
                                                                    onClick={() => removeSubformRow(field.key || field.title, rowIndex)}
                                                                    disabled={field.disabled}
                                                                >
                                                                    حذف
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        <button
                                            type="button"
                                            className="btn btn-xs btn-outline"
                                            onClick={() => addSubformRow(field.key || field.title, field.subformTemplate!)}
                                            disabled={field.disabled}
                                        >
                                            {field.addLabel || "إضافة صف"}
                                        </button>
                                    </div>
                                )}
                                {CustomComponent && (
                                    <CustomComponent
                                        value={data[field.key || field.title]}
                                        onChange={(val: any) => handleChange(field.key || field.title, val)}
                                        field={field}
                                        disabled={field.disabled}
                                    />
                                )}
                            </div>
                        </fieldset>
                    );
                })}
            </form>

            {
                is_loading ?
                    <div className="mt-5 flex w-full justify-center">
                        <span className="loading loading-spinner loading-xl text-center text-neutral"></span>
                    </div> :
                    <button type="submit" onClick={handleSubmit} className="btn btn-primary w-full mt-4">
                        {props.submitLabel}
                    </button>
            }

        </div >
    );
}

