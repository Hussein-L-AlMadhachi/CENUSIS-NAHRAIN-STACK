import { type JSX, useState, useEffect } from "react";
import { ArrowRightFromLine } from "lucide-react";

// layouts
import { MainLayout } from "@/layout/MainLayout";

// Components
import { EditableTable } from "@/components/EditableTable";
import { Section, Subsection } from "@/components/Section";
import { buildGradeTable } from "@/helpers/grade_table";

// Hooks
import { useValidRoute } from "@/hooks/useValidRoute";

// Globals
import { type GradesDate, superAdminRPC } from "@/rpc";
import { Link, useParams } from "wouter";
import { sidebar_pages } from "./sidebar_pages";









function Options() {
    return <div id="options" className="menu lg:menu-horizontal menu-vertical w-full justify-between">
        <Link href="/superadmin/subjects" className="btn btn-md btn-ghost btn-circle">
            <ArrowRightFromLine />
        </Link>
    </div>;
}







function MainContent(): JSX.Element {
    const params = useParams<{ studying_id: string }>();
    if (!params.studying_id) {
        throw "invalid studying id"
    }

    const [data, setData] = useState<GradesDate[]>([]);

    const fetchData = () => {
        superAdminRPC.fetchStudentGradeFieldsPerStudying(parseInt(params.studying_id)).then((data) => setData(data));
    };

    useEffect(() => {
        fetchData();
    }, []);

    const { headers, rows } = buildGradeTable(data || []);

    return <>
        <Section>
            <Subsection>
                <Options />
            </Subsection>
            <Subsection>

                <EditableTable
                    data={rows}
                    headers={headers}
                />

            </Subsection>
        </Section>

    </>;
}







export function SuperGradesPage(): JSX.Element {
    useValidRoute(["superadmin"], "/login");

    return <>
        <MainLayout
            main={MainContent}
            title={"درجات السعي"}
            sidebar={sidebar_pages}
        />
    </>
}


