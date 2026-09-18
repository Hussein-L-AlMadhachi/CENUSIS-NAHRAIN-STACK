import type { GradesDate } from "@/rpc";


export type GradeTableRow = {
    id?: number;
    student_name: string;
} & Record<string, string | number | undefined>;


export function buildGradeTable(data: GradesDate[]): {
    headers: Record<string, string>;
    rows: GradeTableRow[];
} {
    const fieldNames: string[] = [];
    const seen = new Set<string>();

    for (const row of data) {
        for (const field of row.grade_fields ?? []) {
            const fieldName = field.name.trim();

            if (!fieldName || seen.has(fieldName)) {
                continue;
            }

            seen.add(fieldName);
            fieldNames.push(fieldName);
        }
    }

    const headers: Record<string, string> = { student_name: "اسم الطالب" };
    for (const fieldName of fieldNames) {
        headers[fieldName] = fieldName;
    }

    const rows: GradeTableRow[] = data.map((row) => {
        const flattenedRow: GradeTableRow = {
            id: row.id,
            student_name: row.student_name ?? "",
        };

        for (const fieldName of fieldNames) {
            flattenedRow[fieldName] = "";
        }

        for (const field of row.grade_fields ?? []) {
            const fieldName = field.name.trim();
            if (!fieldName) {
                continue;
            }

            flattenedRow[fieldName] = field.grade;
        }

        return flattenedRow;
    });

    return { headers, rows };
}