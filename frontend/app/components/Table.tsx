import type { JSX } from "react";

type TableRow = Record<string, string | number>;


interface props {
    data: TableRow[] | null;
    dropdown: React.FC<{ id: string | number }>;
    headers: Record<string, string>;
    className?: string;
}



export function Table(props: props) {

    // skeleton loading
    if (props.data === null) {
        return <div className={`overflow-x-auto rounded-2xl border border-base-content/5 p-4 min-w-4xs ${props.className ? props.className : ""}`} >
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
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                    <tr>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                    <tr>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                    <tr>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                        <td> <div className="skeleton h-4 w-full"></div></td>
                    </tr>
                </tbody>

            </table>
        </div>
            ;
    }

    // indecate no data
    if (props.data === undefined) {
        return <div className={`overflow-x-auto border border-base-content/5 p-4 min-w-4xs ${props.className ? props.className : ""}`} >
            <table className="table flex justify-center items-center">
                <tbody>
                    <tr>
                        <td className="text-3xl text-center"> لا يوجد معلومات لحد الآن </td>
                    </tr>
                </tbody>
            </table>
        </div>
            ;
    }

    const table_body: JSX.Element[] = props.data.map((row_data) => {

        return <tr> {
            Object.keys(props.headers).map((header) => {

                if (header === ":dropdown:") {
                    return <td> <props.dropdown id={row_data["id"]} /> </td>
                }

                return <td> {props.headers[header]} </td>
            })
        } </tr>;

    });

    const table_headers: JSX.Element[] = Object.keys(props.headers).map((header) => <th> {props.headers[header]} </th>)

    return <div className={`overflow-x-auto rounded-box border border-base-content/5 bg-base-100 ${props.className ? props.className : ""}`}>
        <table className="table">
            {/* head */}
            <thead>
                <tr>
                    {table_headers}
                </tr>
            </thead>
            <tbody>
                {table_body}
            </tbody>
        </table>
    </div>
}
