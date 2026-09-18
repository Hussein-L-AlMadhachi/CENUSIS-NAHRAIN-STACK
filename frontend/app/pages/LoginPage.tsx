import { useState } from "react"
import { publicRPC } from "../rpc";
import { useGetRoleFirstPageURL } from "../hooks/useGetRoleFirstPageURL";





export function LoginPage() {
    const [username, setUsername] = useState<string>("");
    const [password, setPassword] = useState<string>("");

    const [is_loading, setIsLoading] = useState<boolean>(false);
    const [error_msg, setErrorMsg] = useState<string>("");

    const navigate = useGetRoleFirstPageURL({
        "admin": "/admin/teachers",
        "teacher": "/teacher/subjects",
        "superadmin": "/superadmin/teachers"
    })

    async function login() {
        setIsLoading(true);
        const data = await publicRPC.login(username, password);

        // Expires in one hour (3600 seconds) just like the JWT token
        document.cookie = `auth-role=${data.role}; max-age=3600;`;

        navigate(data.role);

        try {
        } catch {
            setErrorMsg("اسم المستخدم وكلمة السر غير صحيحين.")
        } finally {
            setIsLoading(false);
        }
    }


    return <div className="w-full flex justify-center select-none pt-20">
        <section className="w-2xs">
            <div className="py-3 px-7">
                <h1 className="text-5xl"> مرحباً بكم </h1>
                <p className="text-lg my-3 px-1 text-gray-500"> أدخل معلوماتك </p>
            </div>
            <fieldset className="fieldset">

                <legend className="fieldset-legend text-gray-500 text-sm">الإسم</legend>
                <input type="text" className="input" disabled={is_loading} value={username} onChange={(e) => { setUsername(e.target.value) }} placeholder="ادخل اسم المستخدم الخاص بك" />

                <legend className="fieldset-legend text-gray-500 text-sm">كلمة المرور</legend>
                <input dir="auto" type="password" className="input" disabled={is_loading} value={password} onChange={(e) => { setPassword(e.target.value) }} placeholder="ادخل كلمة السر الخاصة بك" />
                <p className="text-right mt-2 pr-1.5 text-error">{error_msg ? error_msg : ""}</p>
                {
                    is_loading ?
                        <div className="mt-5 flex w-full justify-center"><span className="loading loading-spinner loading-xl text-center text-neutral"></span></div> :
                        <button className="btn btn-neutral mt-4 rounded-lg text-lg active:scale-98 transition" onClick={login}>إدخل</button>
                }


            </fieldset>

        </section>
    </div>

}
