import { Route, Switch } from "wouter";
import type { JSX } from "react";


import { LoginPage } from "@/pages/LoginPage";

import { TeachersPage } from "@/pages/admin/Teachers";
import { StudentsPage } from "@/pages/admin/Students";
import { SubjectsPage } from "@/pages/admin/Subjects";

import { StudyingPage } from "@/pages/admin/Studying";

import { PermissionsPage } from "@/pages/admin/Permissions";
import { GradingSystemsPage } from "@/pages/admin/GradingSystems";
import { AbsenceAlertsPage } from "@/pages/admin/AbsenceAlerts";
import { TeachersSubjectsPage } from "./pages/teacher/Subjects";
import { TeachersAttendancePage } from "./pages/teacher/Attendance";
import { TeachersAbsentedPage } from "./pages/teacher/Absented";
import { TeachersGradesPage } from "./pages/teacher/Grades";
import { TeacherStudyingPage } from "./pages/teacher/Studying";
import { TeachersLabAttendancePage } from "./pages/teacher/LabAttendance"
import { TeachersLabAbsentedPage } from "./pages/teacher/LabAttended";
import { TeachersLabGradesPage } from "./pages/teacher/LabGrades";

import { SuperTeachersPage }  from "@/pages/superadmin/Teachers" ;
import { SuperStudentsPage } from "@/pages/superadmin/Students";
import { SuperSubjectsPage } from "@/pages/superadmin/Subjects";

import { SuperStudyingPage } from "@/pages/superadmin/Studying";
import { SuperAttendancePage } from "@/pages/superadmin/Attendance";
import { SuperAbsentedPage } from "@/pages/superadmin/Absented";

import { SuperPermissionsPage } from "@/pages/superadmin/Permissions";
import { SuperGradesPage } from "./pages/superadmin/Grades";
import { SuperGradingSystemsPage } from "./pages/superadmin/GradingSystems";
import { SuperAbsenceAlertsPage } from "./pages/superadmin/AbsenceAlerts";
import { TeachersLabSubjectsPage } from "./pages/teacher/LabSubjects";
import { SuperLabSubjectsPage } from "./pages/superadmin/LabSubjects";
import { SuperLabAttendancePage } from "./pages/superadmin/LabAttendance";
import { SuperLabAbsentedPage } from "./pages/superadmin/LabAbsented";
import { SuperLabGradesPage } from "./pages/superadmin/LabGrades";




export default function App(): JSX.Element {
    return <>
        <Switch>
            <Route path="/" component={LoginPage} />
            <Route path="/login" component={LoginPage} />
            <Route path="/admin/teachers" component={TeachersPage} />
            <Route path="/admin/students" component={StudentsPage} />
            <Route path="/admin/subjects" component={SubjectsPage} />
            <Route path="/admin/grading-systems" component={GradingSystemsPage} />
            <Route path="/admin/absence-alerts" component={AbsenceAlertsPage} />
            <Route path="/admin/enrolled/:teacher/:id" component={StudyingPage} />
            <Route path="/admin/permissions/:subject_id" component={PermissionsPage} />

            <Route path="/teacher/subjects" component={TeachersSubjectsPage} />
            <Route path="/teacher/attendance/:subject_id" component={TeachersAttendancePage} />
            <Route path="/teacher/absented/:attendance_record" component={TeachersAbsentedPage} />
            <Route path="/teacher/grades/:studying_id" component={TeachersGradesPage} />
            <Route path="/teacher/enrolled/:teacher/:id" component={TeacherStudyingPage} />
            <Route path="/teacher/subjects/:subject_id" component={TeacherStudyingPage} />

            <Route path="/teacher/lab/attendance/:subject_id" component={TeachersLabAttendancePage} />
            <Route path="teacher/lab/absented/:attendance_record" component={TeachersLabAbsentedPage} />
            <Route path="/teacher/lab/subjects" component={TeachersLabSubjectsPage} />
            <Route path="/teacher/lab/grades/:studying_id" component={TeachersLabGradesPage} />

            <Route path="/superadmin/teachers" component={SuperTeachersPage} />
            <Route path="/superadmin/students" component={SuperStudentsPage} />
            <Route path="/superadmin/subjects" component={SuperSubjectsPage} />
            <Route path="/superadmin/grading-systems" component={SuperGradingSystemsPage} />
            <Route path="/superadmin/absence-alerts" component={SuperAbsenceAlertsPage} />
            <Route path="/superadmin/enrolled/:teacher/:id" component={SuperStudyingPage} />
            <Route path="/superadmin/permissions/:subject_id" component={SuperPermissionsPage} />
            <Route path="/superadmin/attendance/:subject_id" component={SuperAttendancePage} />
            <Route path="/superadmin/absented/:attendance_record" component={SuperAbsentedPage} />
            <Route path="/superadmin/grades/:studying_id" component={SuperGradesPage} />
            <Route path="/superadmin/lab/subjects" component={SuperLabSubjectsPage} />
            <Route path="/superadmin/lab/attendance/:subject_id" component={SuperLabAttendancePage} />
            <Route path="/superadmin/lab/absented/:attendance_record" component={SuperLabAbsentedPage} />
            <Route path="/superadmin/lab/grades/:studying_id" component={SuperLabGradesPage} />



            {/* Default route in a switch */}
            <Route><div className="w-full h-screen flex justify-center items-center">404: No such page!</div></Route>
        </Switch>
    </>;
}
