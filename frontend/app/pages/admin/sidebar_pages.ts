import { BookMarked, GraduationCap, UserStar, Scale, TriangleAlert } from "lucide-react";


export const sidebar_pages = [
    { label: "المواد الدراسية", link: "/admin/subjects", icon: BookMarked },
    { label: "الطلاب", link: "/admin/students", icon: GraduationCap },
    { label: "التدريسيين", link: "/admin/teachers", icon: UserStar },
    { label: "تنبيهات الغياب", link: "/admin/absence-alerts", icon: TriangleAlert },
    { label: "أنظمة الدرجات", link: "/admin/grading-systems", icon: Scale },
];
