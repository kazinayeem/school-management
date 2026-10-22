import { Card, inputCls, OfficePage } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';

type Room = { name: string; kind: string; capacity: number; free: boolean; by: string | null };
type Props = {
    navigation: any;
    roleLabel: string;
    days: string[];
    periods: { id: number; label: string; time_range: string; sort: number }[];
    kinds: string[];
    filters: { day: number; period: number; kind: string };
    rooms: Room[];
};

export default function FreeRooms({ navigation, roleLabel, days, periods, kinds, filters, rooms }: Props) {
    const { t } = useI18n();
    const go = (next: Record<string, string | number>) => router.get(window.location.pathname, { ...filters, ...next }, { preserveState: true, replace: true });
    const free = rooms.filter((r) => r.free);

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Free Rooms" subtitle="Pick a day and period to see which rooms are empty." wide>
            <Card className="flex flex-wrap items-end gap-4">
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Day')}</label>
                    <select className={inputCls} value={filters.day} onChange={(e) => go({ day: e.target.value })}>
                        {days.map((d, i) => (
                            <option key={d} value={i}>
                                {t(d)}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Period')}</label>
                    <select className={inputCls} value={filters.period} onChange={(e) => go({ period: e.target.value })}>
                        {periods.map((p) => (
                            <option key={p.id} value={p.sort}>
                                {t(p.label)} · {p.time_range}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-500">{t('Room type')}</label>
                    <select className={inputCls} value={filters.kind} onChange={(e) => go({ kind: e.target.value })}>
                        <option value="">{t('All')}</option>
                        {kinds.map((k) => (
                            <option key={k} value={k}>
                                {k}
                            </option>
                        ))}
                    </select>
                </div>
                <p className="ms-auto text-sm font-medium text-emerald-700">{t('{n} rooms free', { n: free.length })}</p>
            </Card>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {rooms.map((r) => (
                    <div key={r.name} className={`rounded-2xl border p-4 ${r.free ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'}`}>
                        <div className="flex items-center justify-between">
                            <span className="font-semibold">{r.name}</span>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${r.free ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'}`}>{r.free ? t('Free') : t('In use')}</span>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            {r.kind} · {t('Seats')} {r.capacity}
                        </p>
                        {r.by && <p className="mt-2 text-xs text-slate-600">{r.by}</p>}
                    </div>
                ))}
            </div>
        </OfficePage>
    );
}
