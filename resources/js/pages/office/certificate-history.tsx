import { inputCls, Nav, OfficePage, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { useState } from 'react';

type Row = { id: number; number: string; title: string; issued_on: string; name: string };

export default function CertificateHistory({
    navigation,
    roleLabel,
    rows,
    search,
}: {
    navigation: Nav;
    roleLabel: string;
    rows: Row[];
    search: string;
}) {
    const { t } = useI18n();
    const [q, setQ] = useState(search);

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Issued Register" subtitle="Every certificate the school has issued.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/app/certificates/history', { search: q }, { preserveState: true, replace: true });
                }}
            >
                <input
                    className={`${inputCls} max-w-sm`}
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={t('Search name, number or certificate')}
                />
            </form>
            <Table headers={['Number', 'Student', 'Certificate', 'Issued on', '']} empty={rows.length === 0 ? 'No certificates found.' : null}>
                {rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 font-mono text-xs">{r.number}</td>
                        <td className="px-4 py-3">{r.name}</td>
                        <td className="px-4 py-3">{r.title}</td>
                        <td className="px-4 py-3">{r.issued_on}</td>
                        <td className="px-4 py-3 text-end">
                            <a href={`/app/certificates/${r.id}/print`} target="_blank" rel="noreferrer" className="text-fuchsia-700">
                                {t('Print')}
                            </a>
                        </td>
                    </tr>
                ))}
            </Table>
        </OfficePage>
    );
}
