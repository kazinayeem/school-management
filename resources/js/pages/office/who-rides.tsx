import { Card, Field, inputCls, money, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Row = {
    id: number;
    name: string;
    admission_no: string | null;
    class_name: string | null;
    section: string | null;
    route: string;
    stop: string;
    vehicle: string | null;
    fare: number;
};

export default function WhoRides({
    navigation,
    roleLabel,
    rows,
    routes,
    stops,
    vehicles,
    filters,
    totals,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    routes: { id: number; name: string; fare: number }[];
    stops: { id: number; route_id: number; name: string }[];
    vehicles: { id: number; name: string; route_id: number | null; capacity: number; riders: number }[];
    filters: { route: number; search: string };
    totals: { riders: number; monthly: number };
}) {
    const { t } = useI18n();
    const first = routes[0]?.id ?? '';
    const form = useForm({ student: '', route_id: String(first), stop_id: '', vehicle_id: '' });
    const [q, setQ] = useState(filters.search);
    const routeStops = stops.filter((s) => String(s.route_id) === form.data.route_id);
    const go = (next: Record<string, string | number>) =>
        router.get('/app/transport', { ...filters, ...next }, { preserveState: true, replace: true });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/transport/riders', { preserveScroll: true, onSuccess: () => form.reset('student', 'stop_id') });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Who Rides"
            subtitle="Which students use the school bus, and where they get on."
            wide
        >
            <div className="grid gap-3 sm:grid-cols-3">
                <Card>
                    <div className="text-2xl font-bold">{totals.riders}</div>
                    <div className="text-xs text-slate-500">{t('Students on the bus')}</div>
                </Card>
                <Card>
                    <div className="text-2xl font-bold">{money(totals.monthly)}</div>
                    <div className="text-xs text-slate-500">{t('Monthly transport fares')}</div>
                </Card>
            </div>

            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <h2 className="font-semibold">{t('Add or move a student')}</h2>
                    <div className="grid gap-4 md:grid-cols-4">
                        <Field label="Admission no. or username" error={form.errors.student}>
                            <input className={inputCls} value={form.data.student} onChange={(e) => form.setData('student', e.target.value)} />
                        </Field>
                        <Field label="Route" error={form.errors.route_id}>
                            <select
                                className={inputCls}
                                value={form.data.route_id}
                                onChange={(e) => form.setData({ ...form.data, route_id: e.target.value, stop_id: '', vehicle_id: '' })}
                            >
                                {routes.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name} — {money(r.fare)}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Stop" error={form.errors.stop_id}>
                            <select className={inputCls} value={form.data.stop_id} onChange={(e) => form.setData('stop_id', e.target.value)}>
                                <option value="">{t('Choose...')}</option>
                                {routeStops.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Vehicle" error={form.errors.vehicle_id}>
                            <select className={inputCls} value={form.data.vehicle_id} onChange={(e) => form.setData('vehicle_id', e.target.value)}>
                                <option value="">{t('No vehicle yet')}</option>
                                {vehicles
                                    .filter((v) => v.route_id === null || String(v.route_id) === form.data.route_id)
                                    .map((v) => (
                                        <option key={v.id} value={v.id}>
                                            {v.name} ({v.riders}/{v.capacity})
                                        </option>
                                    ))}
                            </select>
                        </Field>
                    </div>
                    <button disabled={form.processing || routes.length === 0} className={primaryBtn}>
                        {t('Save')}
                    </button>
                </Card>
            </form>

            <div className="flex flex-wrap items-end gap-4">
                <select className={`${inputCls} w-56`} value={filters.route} onChange={(e) => go({ route: e.target.value })}>
                    <option value={0}>{t('All routes')}</option>
                    {routes.map((r) => (
                        <option key={r.id} value={r.id}>
                            {r.name}
                        </option>
                    ))}
                </select>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ search: q });
                    }}
                >
                    <input
                        className={`${inputCls} w-72`}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('Search name or admission no.')}
                    />
                </form>
            </div>

            <Table
                headers={['Student', 'Class', 'Route', 'Stop', 'Vehicle', 'Fare', '']}
                empty={rows.length === 0 ? 'No students ride the bus yet.' : null}
            >
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">
                            {r.name} <span className="text-xs text-slate-400">{r.admission_no}</span>
                        </td>
                        <td className="px-4 py-3">{[r.class_name ? t(r.class_name) : '', r.section].filter(Boolean).join(' ')}</td>
                        <td className="px-4 py-3">{r.route}</td>
                        <td className="px-4 py-3">{r.stop}</td>
                        <td className="px-4 py-3">{r.vehicle ?? <span className="text-slate-400">—</span>}</td>
                        <td className="px-4 py-3">{money(r.fare)}</td>
                        <td className="px-4 py-3 text-end">
                            <button
                                aria-label={t('Delete')}
                                className="text-slate-500 hover:text-red-600"
                                onClick={() =>
                                    confirm(t('Take this student off the bus?')) &&
                                    router.delete(`/app/transport/riders/${r.id}`, { preserveScroll: true })
                                }
                            >
                                <Trash2 className="inline h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
