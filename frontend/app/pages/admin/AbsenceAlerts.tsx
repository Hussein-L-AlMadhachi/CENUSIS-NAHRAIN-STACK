import { type JSX, useCallback, useEffect, useMemo, useState } from "react";
import { BellPlus, Download } from "lucide-react";

import { MainLayout } from "@/layout/MainLayout";
import { EditableTable } from "@/components/EditableTable";
import { Modal } from "@/components/Modal";
import { DynamicForm, type DynamicFormTemplate } from "@/components/DynamicForm";
import { Section, Subsection } from "@/components/Section";
import { useValidRoute } from "@/hooks/useValidRoute";
import {
    adminRPC,
    type AbsenceAlertRowData,
    type AbsenceAlertThresholdData,
    type GradingSystemData
} from "@/rpc";
import Tabs from "@/components/Tabs";
import { sidebar_pages } from "./sidebar_pages";

interface OptionsProps {
    onAddClick: () => void;
    onRefresh: () => void;
    isRefreshing: boolean;
    gradingSystems: string[];
    selectedGradingSystem: string;
    onGradingSystemChange: (gradingSystem: string) => void;
}

function Options({
    onAddClick,
    gradingSystems,
    selectedGradingSystem,
    onGradingSystemChange
}: OptionsProps) {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <div className="text-4xl text-center max-sm:py-10 max-md:w-full"> تنبيهات الغياب </div>
        <ul className="menu bg-base-200 lg:menu-horizontal rounded-box gap-1 menu-vertical max-md:w-full">
            <li>
                <button className="btn btn-lg" onClick={onAddClick}>
                    <BellPlus size={18} /> إضافة تنبيه
                </button>
            </li>
            <li>
                <select
                    className="select select-bordered select-lg"
                    value={selectedGradingSystem}
                    onChange={(event) => onGradingSystemChange(event.target.value)}
                    disabled={gradingSystems.length === 0}
                >
                    <option value="" disabled>
                        اختر نظام الدرجات
                    </option>
                    {gradingSystems.map((gradingSystemName) => (
                        <option key={gradingSystemName} value={gradingSystemName}>
                            {gradingSystemName}
                        </option>
                    ))}
                </select>
            </li>
        </ul>
    </div>;
}

interface AddThresholdModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    selectedGradingSystem: string;
}

const thresholdFormTemplate: DynamicFormTemplate[] = [
    { title: "اسم التنبيه", key: "alert_name", type: "text" },
    { title: "نسبة الغياب (%)", key: "threshold_percent", type: "number", min: 0, max: 100 }
];

function AddThresholdModal({ isOpen, onClose, onSuccess, selectedGradingSystem }: AddThresholdModalProps) {
    const handleSubmit = async (data: AbsenceAlertThresholdData) => {
        const alertName = data.alert_name?.trim();
        const thresholdPercent = Number(data.threshold_percent);

        if (!selectedGradingSystem || !alertName || Number.isNaN(thresholdPercent)) {
            throw "يجب ملء جميع الحقول";
        }

        await adminRPC.newAbsenceAlertThreshold({
            grading_system_name: selectedGradingSystem,
            alert_name: alertName,
            threshold_percent: thresholdPercent
        });

        onSuccess();
        onClose();
    };

    return <Modal isOpen={isOpen} className="w-full flex flex-col justify-center max-w-lg">
        <h3 className="font-bold text-lg mb-4 text-center">إضافة تنبيه غياب</h3>
        <DynamicForm
            key={isOpen ? "open" : "closed"}
            template={thresholdFormTemplate}
            onSubmit={handleSubmit}
            submitLabel="حفظ"
        />
        <button className="btn btn-ghost w-2xs mt-4" onClick={onClose}>إلغاء</button>
    </Modal>;
}

function MainContent(): JSX.Element {
    const [thresholds, setThresholds] = useState<AbsenceAlertThresholdData[]>([]);
    const [alerts1st, setAlerts1st] = useState<AbsenceAlertRowData[]>([]);
    const [alerts2nd, setAlerts2nd] = useState<AbsenceAlertRowData[]>([]);
    const [alerts3rd, setAlerts3rd] = useState<AbsenceAlertRowData[]>([]);
    const [alerts4th, setAlerts4th] = useState<AbsenceAlertRowData[]>([]);
    const [alertsMaster, setAlertsMaster] = useState<AbsenceAlertRowData[]>([]);
    const [alertsPhd, setAlertsPhd] = useState<AbsenceAlertRowData[]>([]);
    const [gradingSystems, setGradingSystems] = useState<GradingSystemData[]>([]);
    const [selectedGradingSystem, setSelectedGradingSystem] = useState("");
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    const dedupeAlerts = useCallback((rows: AbsenceAlertRowData[]) => {
        const byStudentSubject = new Map<string, AbsenceAlertRowData>();

        for (const row of rows) {
            const studentName = row.student_name ?? "";
            const subjectName = row.subject_name ?? "";
            const key = `${studentName}::${subjectName}`;

            if (!byStudentSubject.has(key)) {
                byStudentSubject.set(key, row);
                continue;
            }

            const existing = byStudentSubject.get(key)!;
            const existingRatio = Number(existing.absence_ratio_percent ?? 0);
            const nextRatio = Number(row.absence_ratio_percent ?? 0);

            if (nextRatio > existingRatio) {
                byStudentSubject.set(key, row);
            }
        }

        return Array.from(byStudentSubject.values()).map((row) => ({
            ...row,
            id: row.studying_id
        }));
    }, []);

    const refreshAlerts = useCallback(async () => {
        if (!selectedGradingSystem) {
            return;
        }

        setIsRefreshing(true);
        try {
            await adminRPC.recomputeAbsenceAlerts();
            const [
                thresholdRows,
                firstClassRows,
                secondClassRows,
                thirdClassRows,
                fourthClassRows,
                masterRows,
                phdRows
            ] = await Promise.all([
                adminRPC.fetchAbsenceAlertThresholds({ grading_system_name: selectedGradingSystem }),
                adminRPC.fetchAbsenceAlerts({ degree: "بكلوريوس", class: 1, grading_system_name: selectedGradingSystem }),
                adminRPC.fetchAbsenceAlerts({ degree: "بكلوريوس", class: 2, grading_system_name: selectedGradingSystem }),
                adminRPC.fetchAbsenceAlerts({ degree: "بكلوريوس", class: 3, grading_system_name: selectedGradingSystem }),
                adminRPC.fetchAbsenceAlerts({ degree: "بكلوريوس", class: 4, grading_system_name: selectedGradingSystem }),
                adminRPC.fetchAbsenceAlerts({ degree: "ماجستير", grading_system_name: selectedGradingSystem }),
                adminRPC.fetchAbsenceAlerts({ degree: "دكتوراه", grading_system_name: selectedGradingSystem })
            ]);

            setThresholds(thresholdRows);
            setAlerts1st(dedupeAlerts(firstClassRows));
            setAlerts2nd(dedupeAlerts(secondClassRows));
            setAlerts3rd(dedupeAlerts(thirdClassRows));
            setAlerts4th(dedupeAlerts(fourthClassRows));
            setAlertsMaster(dedupeAlerts(masterRows));
            setAlertsPhd(dedupeAlerts(phdRows));
        } finally {
            setIsRefreshing(false);
        }
    }, [dedupeAlerts, selectedGradingSystem]);

    useEffect(() => {
        adminRPC.fetchGradingSystems().then((systems) => {
            setGradingSystems(systems);
            if (systems.length > 0) {
                setSelectedGradingSystem((current) => current || systems[0].name);
            }
        });
    }, []);

    useEffect(() => {
        if (!selectedGradingSystem) {
            return;
        }

        refreshAlerts();
    }, [refreshAlerts, selectedGradingSystem]);

    const gradingSystemOptions = useMemo(
        () => gradingSystems.map((system) => system.name),
        [gradingSystems]
    );

    const activeAlertsHeaders = useMemo(() => ({
        "student_name": "الطالب",
        "subject_name": "المادة",
        "hours_missed": "عدد ساعات الغياب",
        "total_hours": "عدد الساعات الكلية",
        "@ratio": "نسبة الغياب",
        "alert_level": "مستوى التنبيه"
    }), []);

    const activeAlertsRenderers = useMemo(() => ({
        "@ratio": (row: AbsenceAlertRowData) => {
            const ratio = Number(row.absence_ratio_percent ?? 0);
            return <span>{ratio.toFixed(2)}%</span>;
        }
    }), []);

    const handleExportAbsenceAlerts = useCallback((degree: string, classNumber?: number) => {
        if (!selectedGradingSystem) {
            return;
        }

        const query = new URLSearchParams({
            degree,
            grading_system: selectedGradingSystem
        });

        if (typeof classNumber === "number") {
            query.set("class", String(classNumber));
        }

        window.open(`/api/absence-alerts/export?${query.toString()}`, "_blank");
    }, [selectedGradingSystem]);

    const ExportBar = ({ degree, classNumber }: { degree: string; classNumber?: number }) => (
        <div className="flex justify-end items-center">
            <button
                className="btn btn-sm"
                onClick={() => handleExportAbsenceAlerts(degree, classNumber)}
                disabled={!selectedGradingSystem}
            >
                <Download size={16} /> تصدير التنبيهات
            </button>
        </div>
    );

    return <>
        <Section>
            <Subsection>
                <Options
                    onAddClick={() => setIsAddModalOpen(true)}
                    onRefresh={refreshAlerts}
                    isRefreshing={isRefreshing}
                    gradingSystems={gradingSystemOptions}
                    selectedGradingSystem={selectedGradingSystem}
                    onGradingSystemChange={setSelectedGradingSystem}
                />
            </Subsection>

            <Subsection>
                <h3 className="text-2xl mb-4">إعدادات التنبيهات</h3>
                <EditableTable
                    data={thresholds}
                    headers={{
                        "alert_name": "اسم التنبيه",
                        "threshold_percent": "نسبة الغياب (%)",
                        ":edit:": ""
                    }}
                    onSave={async (id, row) => {
                        await adminRPC.updateAbsenceAlertThreshold(id, {
                            grading_system_name: selectedGradingSystem,
                            alert_name: row.alert_name?.trim(),
                            threshold_percent: Number(row.threshold_percent)
                        });
                        await refreshAlerts();
                    }}
                    onDelete={async (id) => {
                        await adminRPC.deleteAbsenceAlertThreshold(id);
                        await refreshAlerts();
                    }}
                    formTemplate={thresholdFormTemplate}
                />
            </Subsection>

            <Subsection>
                <h3 className="text-2xl my-4 mt-8">قائمة التنبيهات الفعالة</h3>
                <Tabs
                    group="absence_alerts_by_degree"
                    tabs={[
                        {
                            label: "المرحلة الأولى",
                            content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={1} />
                                <EditableTable
                                    data={alerts1st}
                                    headers={activeAlertsHeaders}
                                    customRenderers={activeAlertsRenderers}
                                />
                            </div>
                        },
                        {
                            label: "المرحلة الثانية",
                            content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={2} />
                                <EditableTable
                                    data={alerts2nd}
                                    headers={activeAlertsHeaders}
                                    customRenderers={activeAlertsRenderers}
                                />
                            </div>
                        },
                        {
                            label: "المرحلة الثالثة",
                            content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={3} />
                                <EditableTable
                                    data={alerts3rd}
                                    headers={activeAlertsHeaders}
                                    customRenderers={activeAlertsRenderers}
                                />
                            </div>
                        },
                        {
                            label: "المرحلة الرابعة",
                            content: <div className="flex flex-col gap-4">
                                <ExportBar degree="بكلوريوس" classNumber={4} />
                                <EditableTable
                                    data={alerts4th}
                                    headers={activeAlertsHeaders}
                                    customRenderers={activeAlertsRenderers}
                                />
                            </div>
                        },
                        {
                            label: "الماجستير",
                            content: <div className="flex flex-col gap-4">
                                <ExportBar degree="ماجستير" />
                                <EditableTable
                                    data={alertsMaster}
                                    headers={activeAlertsHeaders}
                                    customRenderers={activeAlertsRenderers}
                                />
                            </div>
                        },
                        {
                            label: "الدكتوراه",
                            content: <div className="flex flex-col gap-4">
                                <ExportBar degree="دكتوراه" />
                                <EditableTable
                                    data={alertsPhd}
                                    headers={activeAlertsHeaders}
                                    customRenderers={activeAlertsRenderers}
                                />
                            </div>
                        }
                    ]}
                />
            </Subsection>
        </Section>

        <AddThresholdModal
            isOpen={isAddModalOpen}
            onClose={() => setIsAddModalOpen(false)}
            onSuccess={refreshAlerts}
            selectedGradingSystem={selectedGradingSystem}
        />
    </>;
}

export function AbsenceAlertsPage(): JSX.Element {
    useValidRoute(["admin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"تنبيهات الغياب"}
            sidebar={sidebar_pages}
        />
    </>;
}
