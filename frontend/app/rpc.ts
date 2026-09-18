import { RPC } from "enders-sync-client";



interface Loggedin {
    user_id: string;
    role: string;
}

export interface AccountInfo {
    id: number;
    username: string;
    role: string;
}

interface PublicRPC {
    load(): Promise<void>;
    login(username: string, password: string): Promise<Loggedin>;
    logout(): Promise<void>;
}

export interface teacherData {
    id?: number;
    teacher_name: string;
    password?: string;
}

export interface studentData {
    id?: number;
    name: string;
    degree: string;
    class: number;
    years_retaken: number;
    years_failed: number;
}

export interface StudentUpdateData {
    student_name?: string;
    degree?: string;
    class?: number;
    sex?: "ذكر" | "انثى";
}

export interface SubjectData {
    id?: number;
    name: string;
    subject_name?: string;
    degree: string;
    class: number;
    total_hours: number;
    hours_weekly: number;
    is_attending_required: boolean;
    teacher_name: string;
    teacher?: string;
    grading_system_id?: number;
    grading_system_name?: string;
    has_lab?: boolean;
    lab_teacher?: number | null;
    lab_teacher_name?: string | null;
    max_lab_grade?: number | null;
    lab_grade_field?: string | null;
    lab_weekly_hours?: number | null;
}

export interface GradeField {
    name: string;
    grade: number;
}

export interface EnrollmentData {
    id?: number;
    teacher_id?: number;
    teacher_name?: string;
    student_id?: number;
    student_name?: string;
    subject_id?: number;
    subject_name?: string;
    studying_year?: number;
    hours_missed?: number;
    grade_fields?: GradeField[];
    class?: number;
    degree?: string;
}

export interface AttendanceRecordData {
    id: number;
    date: string;
    created_at: string;
}


export interface LabAttendanceRecordData {
    id: number;
    date: string;
    lab_attendance: boolean;
    created_at: string;
}

export interface AttendanceRecordWithSubject {
    id: number;
    date: string;
    lab_attendance: boolean;
    subject_name: string;
}

export interface AbsentedData {
    id?: number;
    hours_absent?: number;
    student_name?: string;
    student_id?: number;
}

export interface GradesDate {
    id?: number;
    grade_fields?: GradeField[];
    teacher_name?: string;
    student_name?: string;
}

export interface LabGradesData {
    id?: number;
    lab_grade?: number;
    teacher_name?: string;
    student_name?: string;
}

export interface GradingSystemFieldData {
    max_grade: number;
    min_grade: number;
    field_name: string;
}

export interface GradingSystemData {
    id?: number;
    name: string;
    normalized_name?: string;
    fields: GradingSystemFieldData[];
}

export interface SubjectAccessControlData {
    id?: number;
    subject_id?: number;
    loggedin_user?: number;
    subject_name?: string;
    user_name?: string;
}

export interface AbsenceAlertThresholdData {
    id?: number;
    grading_system_id?: number;
    grading_system_name?: string;
    alert_name?: string;
    threshold_percent?: number;
}

export interface AbsenceAlertRowData {
    id?: number;
    studying_id?: number;
    student_name?: string;
    subject_name?: string;
    hours_missed?: number;
    total_hours?: number;
    absence_ratio_percent?: number;
    alert_level?: string;
    grading_system_name?: string;
}

export interface AbsenceAlertsFilterData {
    degree?: string;
    class?: number;
    grading_system_name?: string;
}

interface AdminsRPC {
    load(): Promise<void>;
    getAccountInfo(): Promise<AccountInfo>;

    autocompleteStudentsBySubject(searched_name: string, subject_id: number): Promise<string[]>;

    login(username: string, password: string): Promise<Loggedin>;
    registerTeacher(data: teacherData): Promise<number>;

    // teachers accounts management
    updateUser(id: string, data: teacherData): Promise<number>;
    deleteUser(id: number): Promise<void>;
    changeSelfPassword(password: string): Promise<void>;
    changeTeacherPassword(id: string, password: string): Promise<void>;
    fetchTeachers(): Promise<teacherData[]>;
    autocompleteTeacher(name: string): Promise<string[]>;

    // students registration management
    fetchStudents(): Promise<studentData[]>;
    updateStudent(uid: number, data: StudentUpdateData): Promise<{ id: number }>;
    newStudent(data: studentData): Promise<{ id: number }>;
    deleteStudent(student_id: number): Promise<void>;
    fetchStudentInfo(id: number): Promise<studentData>;
    filterStudentsByClassDegree(degree: string, student_class: number): Promise<studentData[]>;
    filterStudentsByDegree(degree: string): Promise<studentData[]>;
    autocompleteStudent(name: string): Promise<string[]>;
    findStudentByName(name: string): Promise<studentData>;

    // subjects tracking
    newSubject(data: SubjectData): Promise<number>;
    updateSubject(id: number, data: Partial<SubjectData>): Promise<number>;
    deleteSubject(id: number): Promise<void>;
    fetchSingleSubject(id: number): Promise<SubjectData>;
    fetchSubjects(): Promise<SubjectData[]>;
    filterSubjectsByClassDegree(degree: string, student_class: number): Promise<SubjectData[]>;
    filterSubjectsByDegree(degree: string): Promise<SubjectData[]>;
    autocompleteSubject(name: string): Promise<string[]>;
    findSubjectByName(name: string): Promise<SubjectData[]>;

    // grading systems
    newGradingSystem(data: GradingSystemData): Promise<number>;
    updateGradingSystem(id: number, data: Partial<GradingSystemData>): Promise<number>;
    deleteGradingSystem(id: number): Promise<void>;
    fetchSingleGradingSystem(id: number): Promise<GradingSystemData>;
    fetchGradingSystems(): Promise<GradingSystemData[]>;
    autocompleteGradingSystem(name: string): Promise<string[]>;
    findGradingSystemByName(name: string): Promise<GradingSystemData>;

    // students "studying" relationship management
    newEnrollment(data: EnrollmentData): Promise<number>;
    updateEnrollment(id: number, data: Partial<EnrollmentData>): Promise<number>;
    deleteEnrollment(id: number): Promise<void>;
    fetchSingleEnrollment(id: number): Promise<EnrollmentData>;
    fetchEnrollmentsForSubject(subject_id: number): Promise<EnrollmentData[]>;

    // absence alerts
    newAbsenceAlertThreshold(data: AbsenceAlertThresholdData): Promise<number>;
    updateAbsenceAlertThreshold(id: number, data: Partial<AbsenceAlertThresholdData>): Promise<number>;
    deleteAbsenceAlertThreshold(id: number): Promise<void>;
    fetchAbsenceAlertThresholds(filters?: AbsenceAlertsFilterData): Promise<AbsenceAlertThresholdData[]>;
    recomputeAbsenceAlerts(): Promise<{ updated: number }>;
    fetchAbsenceAlerts(filters?: AbsenceAlertsFilterData): Promise<AbsenceAlertRowData[]>;

    // attendance records management
    fetchDailyAttendanceRecordsForTheSubject(subject_id: number): Promise<AttendanceRecordData[]>;

    // attendance management per student
    fetchAbsentStudents(attendance_record_id: number): Promise<AbsentedData[]>;

    // grading
    fetchStudentGradeFieldsPerStudying(studying_id: number): Promise<GradesDate[]>;
    fetchStudentLabGradesPerStudying(studying_id: number): Promise<LabGradesData[]>;

    // TA access control
    fetchSubjectAccessControl(subject_id: number): Promise<SubjectAccessControlData[]>;
    grantAccess(subject_name: string, user_name: string): Promise<void>;
    revokeAccess(subject_id: number, user_id: number): Promise<void>;
}

interface TeachersRPC {
    load(): Promise<void>;
    getAccountInfo(): Promise<AccountInfo>;

    fetchSubjectsByLabTeacher(): Promise<SubjectData[]>;

    autocompleteStudentsBySubject(searched_name: string, subject_id: number): Promise<string[]>;

    logout(): Promise<void>;
    login(username: string, password: string): Promise<Loggedin>;
    registerTeacher(data: teacherData): Promise<number>;

    // teachers accounts management
    updateUser(id: string, data: teacherData): Promise<number>;
    deleteUser(id: number): Promise<void>;
    changeSelfPassword(password: string): Promise<void>;
    changeTeacherPassword(id: string, password: string): Promise<void>;
    fetchTeachers(): Promise<teacherData[]>;
    autocompleteTeacher(name: string): Promise<string[]>;

    // students registration management
    fetchStudents(): Promise<studentData[]>;
    updateStudent(uid: number, data: StudentUpdateData): Promise<{ id: number }>;
    newStudent(data: studentData): Promise<{ id: number }>;
    deleteStudent(student_id: number): Promise<void>;
    fetchStudentInfo(id: number): Promise<studentData>;
    filterStudentsByClassDegree(degree: string, student_class: number): Promise<studentData[]>;
    filterStudentsByDegree(degree: string): Promise<studentData[]>;
    autocompleteStudent(name: string): Promise<string[]>;
    findStudentByName(name: string): Promise<studentData>;

    // subjects tracking
    newSubject(data: SubjectData): Promise<number>;
    updateSubject(id: number, data: Partial<SubjectData>): Promise<number>;
    deleteSubject(id: number): Promise<void>;
    fetchSingleSubject(id: number): Promise<SubjectData>;
    fetchSubjects(): Promise<SubjectData[]>;
    filterSubjectsByClassDegree(degree: string, student_class: number): Promise<SubjectData[]>;
    filterSubjectsByDegree(degree: string): Promise<SubjectData[]>;
    autocompleteSubject(name: string): Promise<string[]>;
    findSubjectByName(name: string): Promise<SubjectData[]>;

    // grading systems
    newGradingSystem(data: GradingSystemData): Promise<number>;
    updateGradingSystem(id: number, data: Partial<GradingSystemData>): Promise<number>;
    deleteGradingSystem(id: number): Promise<void>;
    fetchSingleGradingSystem(id: number): Promise<GradingSystemData>;
    fetchGradingSystems(): Promise<GradingSystemData[]>;
    autocompleteGradingSystem(name: string): Promise<string[]>;
    findGradingSystemByName(name: string): Promise<GradingSystemData>;

    // TA subject tracking
    fetch_ta_subject_list(degree: string, subject_class?: number): Promise<SubjectData[]>;

    // students "studying" relationship management
    newEnrollment(data: EnrollmentData): Promise<number>;
    updateEnrollment(id: number, data: Partial<EnrollmentData>): Promise<number>;
    deleteEnrollment(id: number): Promise<void>;
    fetchSingleEnrollment(id: number): Promise<EnrollmentData>;
    fetchEnrollmentsForSubject(subject_id: number): Promise<EnrollmentData[]>;

    // attendance management per record
    deleteAttendanceRecordForTheDay(attendance_record_id: number): Promise<void>;

    // attendance records management
    createDailyAttendanceRecord(subject_id: number, date: string, lab_attendance?:boolean): Promise<number>;
    fetchDailyAttendanceRecordsForTheSubject(subject_id: number): Promise<AttendanceRecordData[]>;
    fetchDailyLabAttendanceRecordsForTheSubject(subject_id: number): Promise<LabAttendanceRecordData[]>;
    fetchAttendanceRecordWithSubject(attendance_record_id: number): Promise<AttendanceRecordWithSubject>;

    // attendance management per student
    fetchAbsentStudents(attendance_record_id: number): Promise<AbsentedData[]>;
    removeAbsence(absented_id: number): Promise<void>;
    markStudentAbsent(data: { attendance_record_id: number, student_id: number, hours_absent: number }, lab_attendance:boolean): Promise<void>;
    markStudentAbsentBulk(data: { attendance_record_id: number, student_ids: number[], hours_absent: number }, lab_attendance:boolean): Promise<void>;

    // grading
    fetchStudentGradeFieldsPerStudying(studying_id: number): Promise<GradesDate[]>;
    fetchStudentLabGradesPerStudying(studying_id: number): Promise<LabGradesData[]>;

    fetchSubjectsByTeacher(degree: string, subject_class?: number): Promise<SubjectData[]>;
}


interface SuperAdminRPC {
    load(): Promise<void>;
    getAccountInfo(): Promise<AccountInfo>;

    autocompleteStudentsBySubject(searched_name: string, subject_id: number): Promise<string[]>;

    login(username: string, password: string): Promise<Loggedin>;
    registerTeacher(data: teacherData): Promise<number>;

    // teachers accounts management
    updateUser(id: string, data: teacherData): Promise<number>;
    deleteUser(id: number): Promise<void>;
    changeSelfPassword(password: string): Promise<void>;
    changeTeacherPassword(id: string, password: string): Promise<void>;
    fetchTeachers(): Promise<teacherData[]>;
    autocompleteTeacher(name: string): Promise<string[]>;

    // students registration management
    fetchStudents(): Promise<studentData[]>;
    updateStudent(uid: number, data: StudentUpdateData): Promise<{ id: number }>;
    newStudent(data: studentData): Promise<{ id: number }>;
    deleteStudent(student_id: number): Promise<void>;
    fetchStudentInfo(id: number): Promise<studentData>;
    filterStudentsByClassDegree(degree: string, student_class: number): Promise<studentData[]>;
    filterStudentsByDegree(degree: string): Promise<studentData[]>;
    autocompleteStudent(name: string): Promise<string[]>;
    findStudentByName(name: string): Promise<studentData>;

    // subjects tracking
    newSubject(data: SubjectData): Promise<number>;
    updateSubject(id: number, data: Partial<SubjectData>): Promise<number>;
    deleteSubject(id: number): Promise<void>;
    fetchSingleSubject(id: number): Promise<SubjectData>;
    fetchSubjects(): Promise<SubjectData[]>;
    filterSubjectsByClassDegree(degree: string, student_class: number): Promise<SubjectData[]>;
    filterSubjectsByDegree(degree: string): Promise<SubjectData[]>;
    autocompleteSubject(name: string): Promise<string[]>;
    findSubjectByName(name: string): Promise<SubjectData[]>;

    // grading systems
    newGradingSystem(data: GradingSystemData): Promise<number>;
    updateGradingSystem(id: number, data: Partial<GradingSystemData>): Promise<number>;
    deleteGradingSystem(id: number): Promise<void>;
    fetchSingleGradingSystem(id: number): Promise<GradingSystemData>;
    fetchGradingSystems(): Promise<GradingSystemData[]>;
    autocompleteGradingSystem(name: string): Promise<string[]>;
    findGradingSystemByName(name: string): Promise<GradingSystemData>;

    // students "studying" relationship management
    newEnrollment(data: EnrollmentData): Promise<number>;
    updateEnrollment(id: number, data: Partial<EnrollmentData>): Promise<number>;
    deleteEnrollment(id: number): Promise<void>;
    fetchSingleEnrollment(id: number): Promise<EnrollmentData>;
    fetchEnrollmentsForSubject(subject_id: number): Promise<EnrollmentData[]>;

    // absence alerts
    newAbsenceAlertThreshold(data: AbsenceAlertThresholdData): Promise<number>;
    updateAbsenceAlertThreshold(id: number, data: Partial<AbsenceAlertThresholdData>): Promise<number>;
    deleteAbsenceAlertThreshold(id: number): Promise<void>;
    fetchAbsenceAlertThresholds(filters?: AbsenceAlertsFilterData): Promise<AbsenceAlertThresholdData[]>;
    recomputeAbsenceAlerts(): Promise<{ updated: number }>;
    fetchAbsenceAlerts(filters?: AbsenceAlertsFilterData): Promise<AbsenceAlertRowData[]>;

    // attendance management per record
    markStudentAbsent(data: { attendance_record_id: number, hours_absent: number }): Promise<void>;
    deleteAttendanceRecordForTheDay(attendance_record_id: number): Promise<void>;

    // attendance records management
    createDailyAttendanceRecord(subject_id: number, date: string): Promise<number>;
    fetchDailyAttendanceRecordsForTheSubject(subject_id: number): Promise<AttendanceRecordData[]>;
    fetchDailyLabAttendanceRecordsForTheSubject(subject_id: number): Promise<LabAttendanceRecordData[]>;
    fetchAttendanceRecordWithSubject(attendance_record_id: number): Promise<AttendanceRecordWithSubject>;

    // attendance management per student
    markStudentAbsent(data: { attendance_record_id: number, student_name: string, hours_absent: number }): Promise<void>;
    fetchAbsentStudents(attendance_record_id: number): Promise<AbsentedData[]>;
    removeAbsence(absented_id: number): Promise<void>;
    updateAbsence(data: { absented_id: number, hours_absent: number }): Promise<void>;

    // grading
    fetchStudentGradeFieldsPerStudying(studying_id: number): Promise<GradesDate[]>;
    fetchStudentLabGradesPerStudying(studying_id: number): Promise<LabGradesData[]>;

    // TA access control
    fetchSubjectAccessControl(subject_id: number): Promise<SubjectAccessControlData[]>;
    grantAccess(subject_name: string, user_name: string): Promise<void>;
    revokeAccess(subject_id: number, user_id: number): Promise<void>;

    // teacher
    logout(): Promise<void>;

    // TA subject tracking
    fetch_ta_subject_list(degree: string, subject_class?: number): Promise<SubjectData[]>;


    fetchSubjectsByTeacher(degree: string, subject_class?: number): Promise<SubjectData[]>;
};


export const publicRPC = new RPC('/api/public') as unknown as PublicRPC;
export const adminRPC = new RPC('/api/admin') as unknown as AdminsRPC;


export const superAdminRPC = new RPC('/api/superadmin') as unknown as SuperAdminRPC;
export const teacherRPC = new RPC('/api/teacher') as unknown as TeachersRPC;

type GradeFieldsFetcher = (studying_id: number) => Promise<GradesDate[]>;
type GradeFieldsFallbackClient = {
    fetchStudentGradeFieldsPerStudying?: GradeFieldsFetcher;
};
type AccountInfoFetcher = () => Promise<AccountInfo>;
type AccountInfoFallbackClient = {
    getAccountInfo?: AccountInfoFetcher;
    getProfile?: () => Promise<{ username?: string; teacher_name?: string; role?: string; id?: number }>;
};

function isAuthError(error: unknown): boolean {
    const message = error instanceof Error ? error.message.toLowerCase() : String(error).toLowerCase();
    return message.includes("unauthorized") || message.includes("forbidden") || message.includes("401") || message.includes("403");
}

function attachGradeFieldsFetcherFallback(client: GradeFieldsFallbackClient) {
    if (typeof client.fetchStudentGradeFieldsPerStudying === "function") {
        return;
    }

    client.fetchStudentGradeFieldsPerStudying = async (studying_id: number): Promise<GradesDate[]> => {
        const candidates: GradeFieldsFallbackClient[] = [adminRPC, teacherRPC, superAdminRPC];
        let lastAuthError: unknown = null;

        for (const candidate of candidates) {
            const fn = candidate.fetchStudentGradeFieldsPerStudying as GradeFieldsFetcher | undefined;
            if (typeof fn !== "function") {
                continue;
            }

            try {
                return await fn.call(candidate, studying_id);
            } catch (error) {
                if (!isAuthError(error)) {
                    throw error;
                }

                lastAuthError = error;
            }
        }

        if (lastAuthError) {
            throw lastAuthError;
        }

        throw new Error("fetchStudentGradeFieldsPerStudying is not available on loaded RPC endpoints");
    };
}

function attachAccountInfoFallback(client: AccountInfoFallbackClient) {
    if (typeof client.getAccountInfo === "function") {
        return;
    }

    client.getAccountInfo = async (): Promise<AccountInfo> => {
        if (typeof client.getProfile === "function") {
            const profile = await client.getProfile();
            return {
                id: typeof profile.id === "number" ? profile.id : 0,
                username: profile.username ?? profile.teacher_name ?? "",
                role: profile.role ?? ""
            };
        }

        throw new Error("getAccountInfo is not available on loaded RPC endpoints");
    };
}

export async function initializeRPC() {
  await publicRPC.load();
  await adminRPC.load();
  await superAdminRPC.load();
  await teacherRPC.load();

    attachGradeFieldsFetcherFallback(adminRPC);
    attachGradeFieldsFetcherFallback(teacherRPC);
    attachGradeFieldsFetcherFallback(superAdminRPC);

    attachAccountInfoFallback(adminRPC);
    attachAccountInfoFallback(teacherRPC);
    attachAccountInfoFallback(superAdminRPC);
}

await initializeRPC()
