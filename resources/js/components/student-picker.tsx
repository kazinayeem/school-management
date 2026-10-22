import { Avatar } from '@/components/avatar';
import { useI18n } from '@/lib/i18n';
import { Plus, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export type PickedStudent = {
    id: number;
    name: string;
    admission_no: string | null;
    roll_no?: string | null;
    class_name: string | null;
    section: string | null;
    card_no?: string | null;
    photo?: string | null;
    parents?: number;
};

const input = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-fuchsia-500';

const describe = (s: PickedStudent, t: (k: string) => string) =>
    [s.class_name ? t(s.class_name) : '', s.section, s.roll_no ? `${t('Roll')} ${s.roll_no}` : ''].filter(Boolean).join(' · ');

/**
 * Search by name, admission (registration) number, roll or username, narrowed by class and
 * section. It shows a few students straight away, so there is always something to pick.
 */
function StudentSearch({
    isPicked,
    onPick,
    scope,
    actionLabel,
}: {
    isPicked: (s: PickedStudent) => boolean;
    onPick: (s: PickedStudent) => void;
    scope?: 'library';
    actionLabel?: string;
}) {
    const { t } = useI18n();
    const [q, setQ] = useState('');
    const [cls, setCls] = useState('');
    const [section, setSection] = useState('');
    const [results, setResults] = useState<PickedStudent[]>([]);
    const [classes, setClasses] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setBusy(true);
            try {
                const params = new URLSearchParams({ q, class: cls, section, ...(scope ? { scope } : {}) });
                const res = await fetch(`/app/lookup/students?${params}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (res.ok) {
                    const body = await res.json();
                    setResults(body.data);
                    setClasses(body.classes);
                }
            } catch {
                /* a newer search replaced this one */
            } finally {
                setBusy(false);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [q, cls, section, scope]);

    return (
        <div className="rounded-xl border border-slate-200 p-3">
            <div className="grid gap-2 sm:grid-cols-[1fr_150px_120px]">
                <div className="relative">
                    <Search className="absolute start-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        className={`${input} ps-9`}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={scope === 'library' ? t('Search name, card no. or admission no.') : t('Search name, admission no. or roll')}
                        aria-label={t('Search students')}
                    />
                </div>
                <select className={input} value={cls} onChange={(e) => setCls(e.target.value)} aria-label={t('Class')}>
                    <option value="">{t('All classes')}</option>
                    {classes.map((c) => (
                        <option key={c} value={c}>
                            {t(c)}
                        </option>
                    ))}
                </select>
                <select className={input} value={section} onChange={(e) => setSection(e.target.value)} aria-label={t('Section')}>
                    <option value="">{t('All sections')}</option>
                    <option>A</option>
                    <option>B</option>
                </select>
            </div>

            <ul className={`mt-3 divide-y divide-slate-100 ${busy ? 'opacity-60' : ''}`}>
                {results.length === 0 && !busy && <li className="py-4 text-center text-sm text-slate-400">{t('No students match.')}</li>}
                {results.map((s) => (
                    <li key={s.id} className="flex items-center gap-3 py-2">
                        <Avatar name={s.name} src={s.photo} className="h-9 w-9" />
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-sm font-medium">{s.name}</div>
                            <div className="truncate text-xs text-slate-500">
                                {scope === 'library' && s.card_no ? `${s.card_no} · ` : ''}
                                {s.admission_no} · {describe(s, t)}
                                {scope !== 'library' && (s.parents ?? 0) > 0 && ` · ${t('{n} parent account(s) linked', { n: s.parents ?? 0 })}`}
                            </div>
                        </div>
                        <button
                            type="button"
                            disabled={isPicked(s)}
                            onClick={() => onPick(s)}
                            className="flex items-center gap-1 rounded-lg bg-fuchsia-700 px-3 py-1 text-xs font-semibold text-white hover:bg-fuchsia-800 disabled:bg-slate-200 disabled:text-slate-500"
                        >
                            {isPicked(s) ? (
                                t('Added')
                            ) : (
                                <>
                                    <Plus className="h-3.5 w-3.5" /> {t(actionLabel ?? 'Add')}
                                </>
                            )}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Pick as many students as needed (for example a parent's children). */
export function StudentPicker({ selected, onChange }: { selected: PickedStudent[]; onChange: (next: PickedStudent[]) => void; classes?: string[] }) {
    const { t } = useI18n();
    const picked = new Set(selected.map((s) => s.id));

    return (
        <div className="space-y-3 md:col-span-2">
            <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">{t('Children')}</label>
                {selected.length === 0 ? (
                    <p className="text-sm text-slate-400">{t('No student linked yet. Search below and add one or more.')}</p>
                ) : (
                    <ul className="flex flex-wrap gap-2">
                        {selected.map((s) => (
                            <li
                                key={s.id}
                                className="flex items-center gap-2 rounded-full border border-fuchsia-200 bg-fuchsia-50 py-1 ps-1 pe-2 text-sm"
                            >
                                <Avatar name={s.name} src={s.photo} className="h-7 w-7 text-xs" />
                                <span>
                                    {s.name} <span className="text-xs text-slate-500">{describe(s, t)}</span>
                                </span>
                                <button
                                    type="button"
                                    aria-label={`${t('Remove')} ${s.name}`}
                                    onClick={() => onChange(selected.filter((x) => x.id !== s.id))}
                                    className="text-slate-400 hover:text-red-600"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <StudentSearch isPicked={(s) => picked.has(s.id)} onPick={(s) => onChange([...selected, s])} />
        </div>
    );
}

/**
 * Pick exactly one student. `value` is the identifier the page sends to the server (the
 * admission number, or the library card number in library mode) and `onChange` receives it.
 */
export function StudentSelect({
    label,
    value,
    onChange,
    scope,
    error,
}: {
    label: string;
    value: PickedStudent | null;
    onChange: (student: PickedStudent | null) => void;
    scope?: 'library';
    error?: string;
}) {
    const { t } = useI18n();
    const [open, setOpen] = useState(false);

    return (
        <div className="md:col-span-2">
            <label className="mb-1 block text-sm font-medium text-slate-700">{t(label)}</label>
            {value ? (
                <div className="flex items-center gap-3 rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-2">
                    <Avatar name={value.name} src={value.photo} className="h-10 w-10" />
                    <div className="min-w-0 flex-1">
                        <div className="truncate text-sm font-medium">{value.name}</div>
                        <div className="truncate text-xs text-slate-500">
                            {scope === 'library' && value.card_no ? `${value.card_no} · ` : ''}
                            {value.admission_no} · {describe(value, t)}
                        </div>
                    </div>
                    <button type="button" onClick={() => onChange(null)} className="rounded-lg border bg-white px-3 py-1 text-xs hover:bg-slate-50">
                        {t('Change')}
                    </button>
                </div>
            ) : (
                <>
                    <button type="button" onClick={() => setOpen(!open)} className={`${input} flex items-center gap-2 text-start text-slate-500`}>
                        <Search className="h-4 w-4" /> {t('Find a student by name, ID or class...')}
                    </button>
                    {open && (
                        <div className="mt-2">
                            <StudentSearch
                                scope={scope}
                                isPicked={() => false}
                                actionLabel="Select"
                                onPick={(s) => {
                                    onChange(s);
                                    setOpen(false);
                                }}
                            />
                        </div>
                    )}
                </>
            )}
            {error && <p className="mt-1 text-xs text-red-600">{t(error)}</p>}
        </div>
    );
}
