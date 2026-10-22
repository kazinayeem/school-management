import {
    AdmissionView,
    AttendanceView,
    FeesView,
    HomeworkView,
    LeaveView,
    NoticesView,
    ProfileView,
    StudentsView,
} from '@/components/office-features';
import { OfficeShell } from '@/components/office-shell';
import { Head } from '@inertiajs/react';

const views: Record<string, (props: { module: Record<string, any> }) => React.JSX.Element> = {
    students: StudentsView,
    profile: ProfileView,
    admission: AdmissionView,
    attendance: AttendanceView,
    notices: NoticesView,
    homework: HomeworkView,
    leave: LeaveView,
    fees: FeesView,
};

export default function OfficeModule({
    navigation,
    roleLabel,
    module,
}: {
    navigation: Parameters<typeof OfficeShell>[0]['navigation'];
    roleLabel: string;
    module: { kind?: string; title: string; subtitle: string; headers?: string[]; rows?: string[][] };
}) {
    const Feature = module.kind ? views[module.kind] : undefined;

    return (
        <OfficeShell navigation={navigation} roleLabel={roleLabel}>
            <Head title={module.title} />
            {Feature ? (
                <Feature module={module} />
            ) : (
                <div className="mx-auto max-w-[1600px] space-y-6">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">{module.title}</h1>
                        <p className="mt-0.5 text-sm text-slate-600">{module.subtitle}</p>
                    </div>
                    <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                        {(module.rows ?? []).length === 0 ? (
                            <p className="px-6 py-16 text-center text-sm text-slate-400">No records yet.</p>
                        ) : (
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        {(module.headers ?? []).map((header) => (
                                            <th key={header} className="px-4 py-3 text-left font-semibold">
                                                {header}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {(module.rows ?? []).map((row, index) => (
                                        <tr key={index} className="hover:bg-slate-50">
                                            {row.map((cell, cellIndex) => (
                                                <td key={cellIndex} className="px-4 py-3 text-slate-700">
                                                    {cell}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            )}
        </OfficeShell>
    );
}
