import type React from "react";
import { type JSX, useEffect, useState } from "react";
import { ArrowLeftFromLine, LockKeyhole } from "lucide-react";
import { Link } from "wouter"

import UniLogo from "../../public/Assets/logo.jpg"
import { adminRPC, publicRPC, superAdminRPC, teacherRPC } from "@/rpc";
import { navigate } from "wouter/use-browser-location";
import { ThemeController } from "@/components/ThemeController";



interface MainLayoutProps {
    main: React.FC;
    sidebar?: { label: string, link: string, icon: React.FC<{ size: number, strokeWidth: number }> }[];
    title: string;
}

async function logoutHandler() {
    await publicRPC.logout();
    navigate('/login');
    document.cookie.split(";").forEach(function(c) { document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); });
    window.location.reload(); // cookies start to act weird without a full page reload
}

export function MainLayout(props: MainLayoutProps): JSX.Element {
    const [isDrawerOpen, setIsDrawerOpen] = useState(() =>
        typeof window !== "undefined" && window.matchMedia("(min-width: 1024px)").matches
    );
    const [username, setUsername] = useState<string>("");

    useEffect(() => {
        const desktopQuery = window.matchMedia("(min-width: 1024px)");
        const updateDrawerDefault = () => setIsDrawerOpen(desktopQuery.matches);

        updateDrawerDefault();
        desktopQuery.addEventListener("change", updateDrawerDefault);

        return () => desktopQuery.removeEventListener("change", updateDrawerDefault);
    }, []);

    useEffect(() => {
        const pathname = window.location.pathname;
        const rpcClient = pathname.startsWith("/admin")
            ? adminRPC
            : pathname.startsWith("/superadmin")
                ? superAdminRPC
                : pathname.startsWith("/teacher")
                    ? teacherRPC
                    : null;

        if (!rpcClient) {
            return;
        }

        rpcClient.getAccountInfo()
            .then((accountInfo) => setUsername(accountInfo.username))
            .catch(() => setUsername(""));
    }, []);

    return <div className="drawer sm:drawer-open w-full">

        <input
            id="my-drawer-4"
            type="checkbox"
            className="drawer-toggle"
            checked={isDrawerOpen}
            onChange={(event) => setIsDrawerOpen(event.target.checked)}
        />
        <div className="drawer-content overflow-x-hidden max-w-full">

            {/* Navbar */}
            <nav className="navbar w-[calc(100%-16px)] mr-4 bg-[#3e4659] text-white rounded-full rounded-l-none mt-2 justify-between items-center">
                <label htmlFor="my-drawer-4" aria-label="open sidebar" className="btn btn-circle btn-ghost sm:hidden">
                    {/* Sidebar toggle icon */}
                    <ArrowLeftFromLine size={20} strokeWidth={1.5} />
                </label>
                <ThemeController></ThemeController>

                <div className="px-6 text-lg">{props.title}</div>

                <button className="sm:pl-20 btn-sm btn-outline btn-circle" onClick={logoutHandler}>
                    <LockKeyhole size={20} strokeWidth={1.5} />
                </button>
            </nav>

            {/* Page content */}
            <div className="py-8 sm:px-16 px-4 max-w-full overflow-hidden"> <props.main /> </div>
        </div>

        <div className="drawer-side is-drawer-close:overflow-visible">
            <label htmlFor="my-drawer-4" aria-label="close sidebar" className="drawer-overlay"></label>
            <div className="flex min-h-[calc(100vh-8px)] flex-col sm:mt-2 items-start bg-[#1e2537] text-white
                is-drawer-close:w-14 is-drawer-open:w-[200px] sm:rounded-full sm:rounded-b-none rounded-2xl md:rounded-b-none">

                {/* Sidebar content */}
                <ul className="menu w-full grow text-base">
                    <li className="is-drawer-open:mb-2 m-1 is-drawer-close:m-0">
                        <label htmlFor="my-drawer-4" aria-label="افتح الشريط الجانبي" className="is-drawer-close:tooltip is-drawer-close:tooltip-left" data-tip="إفتح">
                            {/* Sidebar toggle icon */}
                            <img className="is-drawer-close:h-5 w-full my-2 rounded-full" src={UniLogo} alt="logo" />
                        </label>
                    </li>
                    {username ? (
                        <li className="h-8 px-3 mb-20 w-full is-drawer-close:px-0 is-drawer-close:flex is-drawer-close:justify-center">
                            <span
                                className="text-sm text-gray-300 mx-auto text-center opacity-85 truncate max-w-full is-drawer-close:tooltip is-drawer-close:tooltip-left"
                                data-tip={username}
                            >
                                حساب {username}
                            </span>
                        </li>
                    ) : <li className="h-8 px-3 mb-20 w-full is-drawer-close:px-0 is-drawer-close:flex is-drawer-close:justify-center">
                    <span
                        className="text-sm text-gray-300 mx-auto text-center opacity-85 truncate max-w-full is-drawer-close:tooltip is-drawer-close:tooltip-left"
                        data-tip={username}
                    >
                        -
                    </span>
                </li>}

                    {props.sidebar?.map((item, index) => (
                        <li key={index}>
                            <Link href={item.link} className="is-drawer-close:tooltip is-drawer-close:tooltip-left" data-tip={item.label}>
                                <item.icon size={20} strokeWidth={1.5} />
                                <span className="is-drawer-close:hidden"> {item.label} </span>
                            </Link>
                        </li>
                    ))}
                </ul>

            </div>
        </div>

    </div>;
}


