import { Card, Field, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function SmsSend({
    navigation,
    roleLabel,
    templates,
    classes,
    enabled,
}: {
    navigation: Nav;
    roleLabel: string;
    templates: { id: number; name: string; body: string }[];
    classes: string[];
    enabled: boolean;
}) {
    const { t } = useI18n();
    const result = (usePage().props as { flash?: Record<string, any> }).flash?.sms as
        | { queued: number; skipped: number; sent?: number; failed?: number }
        | undefined;
    const form = useForm({ audience: 'guardians', class_name: classes[0] ?? '', section: '', number: '', message: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/sms', { preserveScroll: true, onSuccess: () => form.setData('message', '') });
    };

    return (
        <OfficePage
            navigation={navigation}
            roleLabel={roleLabel}
            title="Send SMS"
            subtitle="Write a message and queue it for guardians, staff or one number."
        >
            {!enabled && (
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {t('No SMS provider is connected. Messages are queued in the Delivery Log until one is set up under Settings.')}
                </p>
            )}
            {result && (
                <p role="status" className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {t('{queued} messages queued, {skipped} skipped (no phone number).', result)}
                    {(result.sent ?? 0) + (result.failed ?? 0) > 0 &&
                        ` ${t('{sent} sent, {failed} failed.', { sent: result.sent ?? 0, failed: result.failed ?? 0 })}`}
                </p>
            )}
            <form onSubmit={submit}>
                <Card className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-4">
                        <Field label="Send to" error={form.errors.audience}>
                            <select className={inputCls} value={form.data.audience} onChange={(e) => form.setData('audience', e.target.value)}>
                                <option value="guardians">{t('Guardians of a class')}</option>
                                <option value="staff">{t('All staff')}</option>
                                <option value="number">{t('One phone number')}</option>
                            </select>
                        </Field>
                        {form.data.audience === 'guardians' && (
                            <>
                                <Field label="Class" error={form.errors.class_name}>
                                    <select
                                        className={inputCls}
                                        value={form.data.class_name}
                                        onChange={(e) => form.setData('class_name', e.target.value)}
                                    >
                                        {classes.map((c) => (
                                            <option key={c} value={c}>
                                                {t(c)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Section" error={form.errors.section}>
                                    <select className={inputCls} value={form.data.section} onChange={(e) => form.setData('section', e.target.value)}>
                                        <option value="">{t('All sections')}</option>
                                        <option>A</option>
                                        <option>B</option>
                                    </select>
                                </Field>
                            </>
                        )}
                        {form.data.audience === 'number' && (
                            <Field label="Phone number" error={form.errors.number}>
                                <input
                                    className={inputCls}
                                    dir="ltr"
                                    value={form.data.number}
                                    onChange={(e) => form.setData('number', e.target.value)}
                                    placeholder="+8801700000000"
                                />
                            </Field>
                        )}
                        <Field label="Template">
                            <select
                                className={inputCls}
                                value=""
                                onChange={(e) =>
                                    form.setData('message', templates.find((x) => String(x.id) === e.target.value)?.body ?? form.data.message)
                                }
                            >
                                <option value="">{t('Use a template...')}</option>
                                {templates.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                    <Field label="Message" error={form.errors.message}>
                        <textarea
                            rows={4}
                            maxLength={500}
                            className={inputCls}
                            value={form.data.message}
                            onChange={(e) => form.setData('message', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-slate-400">{form.data.message.length}/500</p>
                    </Field>
                    {form.errors.audience && <p className="text-sm text-red-600">{t(form.errors.audience)}</p>}
                    <button disabled={form.processing} className={primaryBtn}>
                        {t('Queue messages')}
                    </button>
                </Card>
            </form>
        </OfficePage>
    );
}
