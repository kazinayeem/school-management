import { Card, ghostBtn, inputCls, Nav, OfficePage, primaryBtn, Table } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Download, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Backup = { name: string; size: number; created: string };

export default function Backup({
    navigation,
    roleLabel,
    supported,
    backups,
}: {
    navigation: Nav;
    roleLabel: string;
    supported: boolean;
    backups: Backup[];
}) {
    const { t } = useI18n();
    const [restoring, setRestoring] = useState<string | null>(null);
    const [confirmText, setConfirmText] = useState('');

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Backup & Restore"
            subtitle="Save a copy of all school data, and bring one back if something goes wrong."
        >
            {!supported && (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {t('Backups need a SQLite database file.')}
                </p>
            )}

            <Card className="flex items-center justify-between gap-4">
                <p className="text-sm text-slate-600">{t('A backup is a full copy of the database. Keep copies somewhere safe too.')}</p>
                <button
                    disabled={!supported}
                    onClick={() => router.post('/app/settings/backup', {}, { preserveScroll: true })}
                    className={primaryBtn}
                >
                    {t('Create backup')}
                </button>
            </Card>

            {restoring && (
                <Card className="space-y-3 border-red-200">
                    <p className="text-sm font-medium text-red-700">
                        {t('Restoring replaces every record with the ones in {name}. A safety backup is made first and everyone is signed out.', {
                            name: restoring,
                        })}
                    </p>
                    <input
                        className={`${inputCls} max-w-xs`}
                        value={confirmText}
                        onChange={(e) => setConfirmText(e.target.value)}
                        placeholder={t('Type RESTORE to confirm.')}
                    />
                    <div className="flex gap-3">
                        <button
                            disabled={confirmText !== 'RESTORE'}
                            onClick={() => router.post(`/app/settings/backup/${restoring}/restore`, { confirm: confirmText })}
                            className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {t('Restore')}
                        </button>
                        <button
                            onClick={() => {
                                setRestoring(null);
                                setConfirmText('');
                            }}
                            className={ghostBtn}
                        >
                            {t('Cancel')}
                        </button>
                    </div>
                </Card>
            )}

            <Table headers={['Backup', 'Created', 'Size', '']} empty={backups.length === 0 ? 'No backups yet.' : null}>
                {backups.map((b) => (
                    <tr key={b.name} className="hover:bg-slate-50">
                        <td className="px-4 py-3 font-mono text-xs">{b.name}</td>
                        <td className="px-4 py-3">{b.created}</td>
                        <td className="px-4 py-3">{(b.size / 1024).toFixed(0)} KB</td>
                        <td className="space-x-3 px-4 py-3 text-end whitespace-nowrap">
                            <a aria-label={t('Download')} href={`/app/settings/backup/${b.name}`} className="text-slate-500 hover:text-fuchsia-700">
                                <Download className="inline h-4 w-4" />
                            </a>
                            <button aria-label={t('Restore')} onClick={() => setRestoring(b.name)} className="text-slate-500 hover:text-amber-600">
                                <RotateCcw className="inline h-4 w-4" />
                            </button>
                            <button
                                aria-label={t('Delete')}
                                onClick={() =>
                                    confirm(t('Delete this entry?')) && router.delete(`/app/settings/backup/${b.name}`, { preserveScroll: true })
                                }
                                className="text-slate-500 hover:text-red-600"
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
