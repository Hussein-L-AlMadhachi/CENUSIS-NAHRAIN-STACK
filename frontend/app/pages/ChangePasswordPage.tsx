import { useState } from "react";
import { useLocation } from "wouter";
import { changePasswordRPC } from "../rpc";
import { useGetRoleFirstPageURL } from "../hooks/useGetRoleFirstPageURL";



function readCookie(name: string): string | null {
    const raw = document.cookie;
    if (!raw) return null;

    for (const entry of raw.split(";")) {
        const [key, ...rest] = entry.split("=");
        if (key.trim() === name) {
            return rest.join("=").trim();
        }
    }

    return null;
}

export function ChangePasswordPage() {
    const [newPassword, setNewPassword] = useState<string>("");
    const [confirmPassword, setConfirmPassword] = useState<string>("");

    const [is_loading, setIsLoading] = useState<boolean>(false);
    const [error_msg, setErrorMsg] = useState<string>("");

    const [, navigate] = useLocation();
    const navigateToRoleHome = useGetRoleFirstPageURL({
        "admin": "/admin/teachers",
        "teacher": "/teacher/subjects",
        "superadmin": "/superadmin/teachers"
    });

    async function changePassword() {
        setErrorMsg("");

        if (newPassword.length < 8) {
            setErrorMsg("كلمة المرور يجب أن تكون 8 أحرف على الأقل");
            return;
        }

        if (newPassword !== confirmPassword) {
            setErrorMsg("كلمتا المرور غير متطابقتين");
            return;
        }

        setIsLoading(true);
        try {
            await changePasswordRPC.changeSelfPassword(newPassword);

            // clear the force-change flag so the user can use the app
            document.cookie = "must-change-password=; max-age=0; path=/;";

            const role = readCookie("auth-role");
            if (role) {
                navigateToRoleHome(role);
            } else {
                navigate("/login");
            }
        } catch {
            setErrorMsg("تعذر تغيير كلمة المرور، حاول مرة أخرى");
        } finally {
            setIsLoading(false);
        }
    }

    return <div className="w-full flex justify-center select-none pt-20">
        <section className="w-2xs">
            <div className="py-3 px-7">
                <h1 className="text-4xl">تغيير كلمة المرور</h1>
                <p className="text-lg my-3 px-1 text-gray-500">يجب عليك تغيير كلمة المرور قبل المتابعة</p>
            </div>
            <fieldset className="fieldset">

                <legend className="fieldset-legend text-gray-500 text-sm">كلمة المرور الجديدة</legend>
                <input dir="auto" type="password" className="input" disabled={is_loading} value={newPassword} onChange={(e) => { setNewPassword(e.target.value) }} placeholder="ادخل كلمة المرور الجديدة" />

                <legend className="fieldset-legend text-gray-500 text-sm">تأكيد كلمة المرور</legend>
                <input dir="auto" type="password" className="input" disabled={is_loading} value={confirmPassword} onChange={(e) => { setConfirmPassword(e.target.value) }} placeholder="أعد كتابة كلمة المرور" />

                <p className="text-right mt-2 pr-1.5 text-error">{error_msg ? error_msg : ""}</p>
                {
                    is_loading ?
                        <div className="mt-5 flex w-full justify-center"><span className="loading loading-spinner loading-xl text-center text-neutral"></span></div> :
                        <button className="btn btn-neutral mt-4 rounded-lg text-lg active:scale-98 transition" onClick={changePassword}>تغيير كلمة المرور</button>
                }

            </fieldset>
        </section>
    </div>;
}
