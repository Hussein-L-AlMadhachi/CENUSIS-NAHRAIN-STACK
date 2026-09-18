import * as React from "react";
import { Plus, Minus } from "lucide-react";
import { DatePicker } from "./DatePciker";

type CellRenderer<T> = (row: T) => React.JSX.Element;
type ColumnFieldType = "text" | "number" | "select" | "date";

interface SelectOption {
    label: string;
    value: string | number;
}

interface EditableCell<T> {
    rowId: string | number;
    columnKey: string;
    row: T;
}

interface Props<T extends { id?: string | number }> {
    data: T[] | null | undefined;
    headers: Record<string, string>;
    className?: string;
    onSave?: (id: string | number, data: T) => Promise<void> | void;
    editableColumns?: string[];
    nonEditableColumns?: string[];
    customRenderers?: Record<string, CellRenderer<T>>;
    columnFieldTypes?: Record<string, ColumnFieldType>;
    selectOptions?: Record<string, SelectOption[]>;
}

export function InPlaceEditableTable<T extends { id?: string | number }>(props: Props<T>) {
    const [editingCell, setEditingCell] = React.useState<EditableCell<T> | null>(null);
    const [draftValue, setDraftValue] = React.useState("");
    const [savingCellKey, setSavingCellKey] = React.useState<string | null>(null);
    const savingCellKeyRef = React.useRef<string | null>(null);
    const draftValueRef = React.useRef("");

    const getColumnFieldType = (column: string): ColumnFieldType => {
        return props.columnFieldTypes?.[column] ?? "text";
    };

    const formatDateForValue = (date: Date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };

    const parseDraftDate = (value: string) => {
        if (!value) {
            return null;
        }
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const isColumnEditable = (column: string) => {
        if (column === "id" || column.startsWith("@") || column.startsWith(":")) {
            return false;
        }

        if (props.editableColumns && !props.editableColumns.includes(column)) {
            return false;
        }

        if (props.nonEditableColumns?.includes(column)) {
            return false;
        }

        return true;
    };

    const getCellValue = (row: T, column: string) => {
        const value = (row as Record<string, unknown>)[column];
        if (value === null || value === undefined) {
            return "";
        }
        return String(value);
    };

    const startEditing = (row: T, columnKey: string) => {
        if (!isColumnEditable(columnKey) || row.id === undefined) {
            return;
        }

        if (editingCell?.rowId === row.id && editingCell.columnKey === columnKey) {
            return;
        }

        setEditingCell({
            rowId: row.id,
            columnKey,
            row,
        });
        const cellValue = getCellValue(row, columnKey);
        draftValueRef.current = cellValue;
        setDraftValue(cellValue);
    };

    const cancelEditing = () => {
        setEditingCell(null);
        draftValueRef.current = "";
        setDraftValue("");
    };

    const getTypedCellValue = (column: string, value: string): string | number => {
        const columnType = getColumnFieldType(column);
        if (columnType === "number" && value.trim() !== "") {
            const parsed = Number(value);
            if (!Number.isNaN(parsed)) {
                return parsed;
            }
        }
        return value;
    };

    const saveCell = async (valueOverride?: string) => {
        if (!editingCell) {
            return;
        }

        const cellKey = `${editingCell.rowId}:${editingCell.columnKey}`;
        if (savingCellKeyRef.current === cellKey) {
            return;
        }

        const valueToSave = valueOverride ?? draftValueRef.current;
        const nextRow = {
            ...editingCell.row,
            [editingCell.columnKey]: getTypedCellValue(editingCell.columnKey, valueToSave),
        } as T;

        if (props.onSave) {
            savingCellKeyRef.current = cellKey;
            setSavingCellKey(cellKey);
            try {
                await props.onSave(editingCell.rowId, nextRow);
            } finally {
                savingCellKeyRef.current = null;
                setSavingCellKey(null);
            }
        }

        cancelEditing();
    };

    if (props.data === null) {
        return (
            <div className={`overflow-x-auto h-[200px] rounded-2xl border border-base-content/5 p-4 min-w-4xs ${props.className ? props.className : ""}`}>
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
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                        </tr>
                        <tr>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                        </tr>
                        <tr>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                        </tr>
                        <tr>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                            <td><div className="skeleton h-4 w-full"></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        );
    }

    if (props.data === undefined) {
        return (
            <div className={`overflow-x-auto border border-base-content/5 p-4 min-w-4xs ${props.className ? props.className : ""}`}>
                <table className="table flex justify-center items-center">
                    <tbody>
                        <tr>
                            <td className="text-3xl text-center"> لا يوجد معلومات لحد الآن </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        );
    }

    return (
        <div className={`overflow-x-auto rounded-box border border-base-content/5 bg-base-100 ${props.className ? props.className : ""}`}>
            <table className="table">
                <thead>
                    <tr>
                        {Object.keys(props.headers).map((header) => (
                            <th key={header} className="w-43">{props.headers[header]}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {props.data.map((rowData, rowIndex) => (
                        <tr key={rowData.id ?? rowIndex}>
                            {Object.keys(props.headers).map((column) => {
                                if (column.startsWith("@") && props.customRenderers?.[column]) {
                                    return (
                                        <td className="w-fit" key={column}>
                                            {props.customRenderers[column](rowData)}
                                        </td>
                                    );
                                }

                                const isActiveCell =
                                    editingCell?.rowId === rowData.id && editingCell?.columnKey === column;

                                const currentCellKey = rowData.id === undefined ? null : `${rowData.id}:${column}`;
                                const isSavingThisCell = currentCellKey !== null && savingCellKey === currentCellKey;
                                const editable = isColumnEditable(column);
                                const columnType = getColumnFieldType(column);

                                return (
                                    <td
                                        key={column}
                                        className={`w-fit ${editable ? "cursor-text" : ""}`}
                                        onClick={() => {
                                            if (!isActiveCell) {
                                                startEditing(rowData, column);
                                            }
                                        }}
                                    >
                                        {isActiveCell ? (
                                            columnType === "select" ? (
                                                <select
                                                    autoFocus
                                                    className="select select-bordered select-sm h-[25px] min-h-[25px] w-full min-w-24"
                                                    value={draftValue}
                                                    disabled={isSavingThisCell}
                                                    onClick={(e) => e.stopPropagation()}
                                                    onChange={(e) => {
                                                        draftValueRef.current = e.target.value;
                                                        setDraftValue(e.target.value);
                                                    }}
                                                    onBlur={() => { void saveCell(); }}
                                                    onKeyDown={(e) => {
                                                        if (e.key === "Enter") {
                                                            e.preventDefault();
                                                            void saveCell();
                                                        }
                                                        if (e.key === "Escape") {
                                                            e.preventDefault();
                                                            cancelEditing();
                                                        }
                                                    }}
                                                >
                                                    {(props.selectOptions?.[column] ?? []).map((option) => (
                                                        <option key={option.value} value={String(option.value)}>
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : columnType === "date" ? (
                                                <div className="w-full min-w-24" onClick={(e) => e.stopPropagation()}>
                                                    <DatePicker
                                                        className="input-sm h-[25px] w-full"
                                                        date={parseDraftDate(draftValue)}
                                                        setDate={(date) => {
                                                            const value = date ? formatDateForValue(date) : "";
                                                            draftValueRef.current = value;
                                                            setDraftValue(value);
                                                            void saveCell(value);
                                                        }}
                                                    />
                                                </div>
                                            ) : columnType === "number" ? (
                                                <div
                                                    tabIndex={0}
                                                    className="flex items-center w-[100px] outline-none"
                                                    onClick={(e) => e.stopPropagation()}
                                                    onBlur={(e) => {
                                                        if (!e.currentTarget.contains(e.relatedTarget)) {
                                                            void saveCell();
                                                        }
                                                    }}
                                                    ref={(el) => el?.focus()}
                                                >
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-square h-[25px] min-h-[25px] w-[25px]"
                                                        disabled={isSavingThisCell}
                                                        onClick={() => {
                                                            const current = Number(draftValueRef.current) || 0;
                                                            const newValue = String(Math.max(0, current - 1));
                                                            draftValueRef.current = newValue;
                                                            setDraftValue(newValue);
                                                        }}
                                                    >
                                                        <Minus size={14} />
                                                    </button>
                                                    <div className="h-[25px] w-[50px] flex items-center justify-center">
                                                        {draftValue}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-square h-[25px] min-h-[25px] w-[25px]"
                                                        disabled={isSavingThisCell}
                                                        onClick={() => {
                                                            const current = Number(draftValueRef.current) || 0;
                                                            const newValue = String(current + 1);
                                                            draftValueRef.current = newValue;
                                                            setDraftValue(newValue);
                                                        }}
                                                    >
                                                        <Plus size={14} />
                                                    </button>
                                                </div>
                                            ) : (
                                                <input
                                                    type="text"
                                                    autoFocus
                                                    className="input input-outline input-sm h-[25px] w-full min-w-24"
                                                    value={draftValue}
                                                    disabled={isSavingThisCell}
                                                    onClick={(e) => e.stopPropagation()}
                                                    onChange={(e) => {
                                                        draftValueRef.current = e.target.value;
                                                        setDraftValue(e.target.value);
                                                    }}
                                                    onBlur={() => { void saveCell(); }}
                                                    onKeyDown={(e) => {
                                                        if (e.key === "Enter") {
                                                            e.preventDefault();
                                                            void saveCell();
                                                        }
                                                        if (e.key === "Escape") {
                                                            e.preventDefault();
                                                            cancelEditing();
                                                        }
                                                    }}
                                                />
                                            )
                                        ) : (
                                            getCellValue(rowData, column)
                                        )}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

