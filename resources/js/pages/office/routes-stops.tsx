import { Badge, Card, Field, ghostBtn, inputCls, money, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Stop = {
    id: number;
    route_id: number;
    name: string;
    landmark: string | null;
    pickup_time: string;
    drop_time: string;
    sort_order: number;
    riders: number;
};
type Route = { id: number; name: string; fare: number; active: number; riders: number; stops: Stop[] };

function RouteForm({ route, onDone }: { route: Route | null; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({ name: route?.name ?? '', fare: String(route?.fare ?? 0), active: route ? Boolean(route.active) : true });

    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                const done = { preserveScroll: true, onSuccess: onDone };
                if (route) form.put(`/app/transport/routes/${route.id}`, done);
                else form.post('/app/transport/routes', done);
            }}
        >
            <Card className="space-y-4 border-fuchsia-200">
                <h2 className="font-semibold">{route ? t('Edit route') : t('New route')}</h2>
                <div className="grid gap-4 md:grid-cols-3">
                    <Field label="Route name" error={form.errors.name}>
                        <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Monthly fare" error={form.errors.fare}>
                        <input
                            type="number"
                            min={0}
                            className={inputCls}
                            value={form.data.fare}
                            onChange={(e) => form.setData('fare', e.target.value)}
                        />
                    </Field>
                    <label className="flex items-center gap-2 pt-6 text-sm font-medium">
                        <input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} />
                        {t('Active')}
                    </label>
                </div>
                <div className="flex gap-3">
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Save')}
                    </button>
                    <button type="button" onClick={onDone} className={ghostBtn}>
                        {t('Cancel')}
                    </button>
                </div>
            </Card>
        </form>
    );
}

function StopForm({ routeId, stop, onDone }: { routeId: number; stop: Stop | null; onDone: () => void }) {
    const { t } = useI18n();
    const form = useForm({
        route_id: routeId,
        name: stop?.name ?? '',
        landmark: stop?.landmark ?? '',
        pickup_time: stop?.pickup_time ?? '',
        drop_time: stop?.drop_time ?? '',
        sort_order: String(stop?.sort_order ?? 0),
    });

    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                const done = { preserveScroll: true, onSuccess: onDone };
                if (stop) form.put(`/app/transport/stops/${stop.id}`, done);
                else form.post('/app/transport/stops', done);
            }}
        >
            <Card className="space-y-4 border-fuchsia-200">
                <h2 className="font-semibold">{stop ? t('Edit stop') : t('New stop')}</h2>
                <div className="grid gap-4 md:grid-cols-5">
                    <Field label="Stop name" error={form.errors.name}>
                        <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Landmark" error={form.errors.landmark}>
                        <input className={inputCls} value={form.data.landmark} onChange={(e) => form.setData('landmark', e.target.value)} />
                    </Field>
                    <Field label="Morning pickup" error={form.errors.pickup_time}>
                        <input
                            type="time"
                            className={inputCls}
                            value={form.data.pickup_time}
                            onChange={(e) => form.setData('pickup_time', e.target.value)}
                        />
                    </Field>
                    <Field label="Afternoon drop" error={form.errors.drop_time}>
                        <input
                            type="time"
                            className={inputCls}
                            value={form.data.drop_time}
                            onChange={(e) => form.setData('drop_time', e.target.value)}
                        />
                    </Field>
                    <Field label="Order" error={form.errors.sort_order}>
                        <input
                            type="number"
                            min={0}
                            className={inputCls}
                            value={form.data.sort_order}
                            onChange={(e) => form.setData('sort_order', e.target.value)}
                        />
                    </Field>
                </div>
                {form.errors.route_id && <p className="text-sm text-red-600">{t(form.errors.route_id)}</p>}
                <div className="flex gap-3">
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Save')}
                    </button>
                    <button type="button" onClick={onDone} className={ghostBtn}>
                        {t('Cancel')}
                    </button>
                </div>
            </Card>
        </form>
    );
}

export default function RoutesStops({ navigation, roleLabel, routes, open }: { navigation: Nav; roleLabel: string; routes: Route[]; open: number }) {
    const { t } = useI18n();
    const [routeForm, setRouteForm] = useState<Route | 'new' | null>(null);
    const [stopForm, setStopForm] = useState<Stop | 'new' | null>(null);
    const current = routes.find((r) => r.id === open) ?? routes[0];

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Routes & Stops"
            subtitle="The bus routes, their fares, and the stops along each one."
            wide
        >
            <div className="flex justify-end">
                <button onClick={() => setRouteForm('new')} className={`${primaryBtn} flex items-center gap-1`}>
                    <Plus className="h-4 w-4" /> {t('New route')}
                </button>
            </div>
            {routeForm && (
                <RouteForm
                    key={routeForm === 'new' ? 'new' : routeForm.id}
                    route={routeForm === 'new' ? null : routeForm}
                    onDone={() => setRouteForm(null)}
                />
            )}

            {routes.length === 0 ? (
                <p className="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center text-sm text-slate-400">{t('No routes yet.')}</p>
            ) : (
                <div className="grid gap-4 lg:grid-cols-[300px_1fr]">
                    <ul className="space-y-2">
                        {routes.map((r) => (
                            <li key={r.id}>
                                <button
                                    onClick={() => router.get('/app/transport/routes', { route: r.id }, { preserveState: true, replace: true })}
                                    className={`w-full rounded-xl border bg-white p-3 text-start shadow-sm ${current?.id === r.id ? 'border-fuchsia-500' : 'border-slate-200'}`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-medium">{r.name}</span>
                                        {!r.active && <Badge tone="slate">{t('Inactive')}</Badge>}
                                    </div>
                                    <div className="mt-1 text-xs text-slate-500">
                                        {money(r.fare)} · {t('{n} stops', { n: r.stops.length })} · {t('{n} riders', { n: r.riders })}
                                    </div>
                                </button>
                            </li>
                        ))}
                    </ul>

                    {current && (
                        <section className="min-w-0 space-y-4">
                            <div className="flex flex-wrap items-center gap-3">
                                <h2 className="text-lg font-semibold">{current.name}</h2>
                                <button
                                    aria-label={t('Edit route')}
                                    onClick={() => setRouteForm(current)}
                                    className="text-slate-500 hover:text-fuchsia-700"
                                >
                                    <Pencil className="h-4 w-4" />
                                </button>
                                <button
                                    aria-label={t('Delete')}
                                    onClick={() =>
                                        confirm(t('Delete this entry?')) &&
                                        router.delete(`/app/transport/routes/${current.id}`, { preserveScroll: true })
                                    }
                                    className="text-slate-500 hover:text-red-600"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                                <button onClick={() => setStopForm('new')} className={`${ghostBtn} ms-auto flex items-center gap-1`}>
                                    <Plus className="h-4 w-4" /> {t('New stop')}
                                </button>
                            </div>
                            {stopForm && (
                                <StopForm
                                    key={stopForm === 'new' ? `new-${current.id}` : stopForm.id}
                                    routeId={current.id}
                                    stop={stopForm === 'new' ? null : stopForm}
                                    onDone={() => setStopForm(null)}
                                />
                            )}

                            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                                {current.stops.length === 0 ? (
                                    <p className="px-6 py-12 text-center text-sm text-slate-400">{t('No stops on this route yet.')}</p>
                                ) : (
                                    <table className="w-full min-w-[640px] text-sm">
                                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                            <tr>
                                                {['Order', 'Stop', 'Landmark', 'Morning pickup', 'Afternoon drop', 'Riders', ''].map((h) => (
                                                    <th key={h} className="px-4 py-3 text-start font-semibold">
                                                        {t(h)}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {current.stops.map((s) => (
                                                <tr key={s.id} className="hover:bg-slate-50">
                                                    <td className="px-4 py-3">{s.sort_order}</td>
                                                    <td className="px-4 py-3 font-medium">{s.name}</td>
                                                    <td className="px-4 py-3">{s.landmark}</td>
                                                    <td className="px-4 py-3" dir="ltr">
                                                        {s.pickup_time}
                                                    </td>
                                                    <td className="px-4 py-3" dir="ltr">
                                                        {s.drop_time}
                                                    </td>
                                                    <td className="px-4 py-3">{s.riders}</td>
                                                    <td className="space-x-3 px-4 py-3 text-end whitespace-nowrap">
                                                        <button
                                                            aria-label={t('Edit stop')}
                                                            onClick={() => setStopForm(s)}
                                                            className="text-slate-500 hover:text-fuchsia-700"
                                                        >
                                                            <Pencil className="inline h-4 w-4" />
                                                        </button>
                                                        <button
                                                            aria-label={t('Delete')}
                                                            onClick={() =>
                                                                confirm(t('Delete this entry?')) &&
                                                                router.delete(`/app/transport/stops/${s.id}`, { preserveScroll: true })
                                                            }
                                                            className="text-slate-500 hover:text-red-600"
                                                        >
                                                            <Trash2 className="inline h-4 w-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </section>
                    )}
                </div>
            )}
        </OfficePage>
    );
}
