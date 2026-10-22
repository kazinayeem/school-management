import { Card, Field, inputCls, Nav, OfficePage, primaryBtn } from '@/components/office-ui';
import { useI18n } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Img = { key: 'brand_logo' | 'brand_logo_dark' | 'pdf_banner'; label: string; url: string | null; custom: boolean };

const hints: Record<string, string> = {
    brand_logo: 'Shown on the website, login page, ID cards and certificates. PNG, JPG or WebP, up to 2 MB.',
    brand_logo_dark: 'Used on the dark sidebar. A white or light version works best. If empty, the main logo is used.',
    pdf_banner: 'A wide letterhead for the top of every PDF (about 1600 × 250). If empty, the logo, name and address are used.',
};

const PRESETS: { name: string; primary: string; secondary: string }[] = [
    { name: 'Magenta', primary: '#b31299', secondary: '#7c3aed' },
    { name: 'Ocean', primary: '#0369a1', secondary: '#0d9488' },
    { name: 'Forest', primary: '#15803d', secondary: '#ca8a04' },
    { name: 'Royal', primary: '#4338ca', secondary: '#db2777' },
    { name: 'Crimson', primary: '#be123c', secondary: '#1d4ed8' },
    { name: 'Sunset', primary: '#c2410c', secondary: '#7c3aed' },
    { name: 'Slate', primary: '#334155', secondary: '#0891b2' },
    { name: 'Teal', primary: '#0f766e', secondary: '#e11d48' },
];

const hex = /^#[0-9a-f]{6}$/i;
const mix = (h: string, t: number) => {
    const n = [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
    const to = t >= 0 ? 255 : 0;

    return '#' + n.map((c) => Math.round(c + (to - c) * Math.abs(t)).toString(16).padStart(2, '0')).join('');
};
const steps: Record<number, number> = { 50: 0.94, 100: 0.87, 200: 0.74, 300: 0.56, 400: 0.36, 500: 0.18, 600: 0.08, 800: -0.18, 900: -0.34, 950: -0.5 };

/** The same colour steps the server computes, applied to the page at once so the choice can be seen before saving. */
function previewColors(primary: string, secondary: string) {
    const root = document.documentElement.style;
    const set = (names: string[], base: string) =>
        names.forEach((n) => {
            root.setProperty(`--color-${n}-700`, base);
            Object.entries(steps).forEach(([k, t]) => root.setProperty(`--color-${n}-${k}`, mix(base, t)));
        });
    set(['fuchsia', 'indigo', 'pink'], primary);
    set(['violet', 'purple'], secondary);
    root.setProperty('--brand-primary', primary);
    root.setProperty('--brand-primary-dark', mix(primary, -0.18));
    root.setProperty('--brand-primary-light', mix(primary, 0.18));
    root.setProperty('--brand-primary-soft', mix(primary, 0.94));
    root.setProperty('--brand-secondary', secondary);
    root.setProperty('--brand-secondary-dark', mix(secondary, -0.18));
    root.setProperty('--brand-secondary-soft', mix(secondary, 0.94));
}

function ColorField({ label, value, onChange, error }: { label: string; value: string; onChange: (v: string) => void; error?: string }) {
    return (
        <div>
            <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
            <div className="flex items-center gap-3">
                <input type="color" aria-label={label} value={hex.test(value) ? value : '#000000'} onChange={(e) => onChange(e.target.value)} className="h-11 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1" />
                <input className={`${inputCls} w-32 font-mono`} value={value} maxLength={7} onChange={(e) => onChange(e.target.value.startsWith('#') ? e.target.value : `#${e.target.value}`)} />
                <span className="h-9 flex-1 rounded-lg" style={{ background: hex.test(value) ? value : '#e2e8f0' }} />
            </div>
            {error && <p role="alert" className="mt-1 text-xs text-rose-600">{error}</p>}
        </div>
    );
}

export default function Branding({ navigation, roleLabel, name, tagline, images, defaults, colors, defaultColors }: { navigation: Nav; roleLabel: string; name: string; tagline: string; images: Img[]; defaults: { logo: string }; colors: { primary: string; secondary: string }; defaultColors: { primary: string; secondary: string } }) {
    const { t } = useI18n();
    const form = useForm<{ school_name: string; tagline: string; brand_logo: File | null; brand_logo_dark: File | null; pdf_banner: File | null; remove: string[]; brand_primary: string; brand_secondary: string }>({ school_name: name, tagline, brand_primary: colors.primary, brand_secondary: colors.secondary, brand_logo: null, brand_logo_dark: null, pdf_banner: null, remove: [] });
    const [preview, setPreview] = useState<Record<string, string>>({});
    const errors = form.errors as Record<string, string>;

    const pick = (key: Img['key'], file: File | null) => {
        form.setData(key, file);
        setPreview((p) => ({ ...p, [key]: file ? URL.createObjectURL(file) : '' }));
    };
    const setColors = (primary: string, secondary: string) => {
        form.setData({ ...form.data, brand_primary: primary, brand_secondary: secondary });
        if (hex.test(primary) && hex.test(secondary)) previewColors(primary, secondary);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/settings/branding', { forceFormData: true, preserveScroll: true, onSuccess: () => { form.setData({ ...form.data, brand_logo: null, brand_logo_dark: null, pdf_banner: null, remove: [] }); setPreview({}); } });
    };

    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="Branding & Logo" subtitle="Change the school name, logo and PDF letterhead in one place. They update the website, login page, ID cards, certificates and every PDF." wide>
            <form onSubmit={submit} className="space-y-5">
                <Card className="grid gap-4 md:grid-cols-2">
                    <Field label="School name" error={errors.school_name}>
                        <input className={inputCls} value={form.data.school_name} onChange={(e) => form.setData('school_name', e.target.value)} maxLength={150} />
                    </Field>
                    <Field label="Tagline" error={errors.tagline}>
                        <input className={inputCls} value={form.data.tagline} onChange={(e) => form.setData('tagline', e.target.value)} maxLength={255} />
                    </Field>
                </Card>
                <Card className="space-y-4">
                    <div>
                        <h3 className="font-semibold">{t('Website colours')}</h3>
                        <p className="text-xs text-slate-500">{t('Pick a primary and a secondary colour, or start from a preset. You see the change on this page straight away; press Save to keep it for everyone.')}</p>
                    </div>
                    <div className="flex flex-wrap gap-2" role="group" aria-label={t('Colour presets')}>
                        {PRESETS.map((p) => {
                            const on = form.data.brand_primary.toLowerCase() === p.primary && form.data.brand_secondary.toLowerCase() === p.secondary;

                            return (
                                <button key={p.name} type="button" onClick={() => setColors(p.primary, p.secondary)} aria-pressed={on} className={`flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition ${on ? 'border-brand bg-brand-soft' : 'border-slate-200 hover:border-slate-300'}`}>
                                    <span className="flex -space-x-1.5">
                                        <span className="h-5 w-5 rounded-full border-2 border-white" style={{ background: p.primary }} />
                                        <span className="h-5 w-5 rounded-full border-2 border-white" style={{ background: p.secondary }} />
                                    </span>
                                    {t(p.name)}
                                </button>
                            );
                        })}
                        <button type="button" onClick={() => setColors(defaultColors.primary, defaultColors.secondary)} className="rounded-xl border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                            {t('Reset to default')}
                        </button>
                    </div>
                    <div className="grid gap-5 md:grid-cols-2">
                        <ColorField label={t('Primary colour')} value={form.data.brand_primary} onChange={(v) => setColors(v, form.data.brand_secondary)} error={errors.brand_primary} />
                        <ColorField label={t('Secondary colour')} value={form.data.brand_secondary} onChange={(v) => setColors(form.data.brand_primary, v)} error={errors.brand_secondary} />
                    </div>
                    <div className="flex flex-wrap items-center gap-3 rounded-xl bg-slate-50 p-4">
                        <span className="text-xs font-medium text-slate-500">{t('Preview')}</span>
                        <span className="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white">{t('Primary button')}</span>
                        <span className="rounded-lg bg-brand-2 px-4 py-2 text-sm font-semibold text-white">{t('Secondary button')}</span>
                        <span className="rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-brand">{t('Badge')}</span>
                        <a className="text-sm font-semibold text-brand underline" href="#preview" onClick={(e) => e.preventDefault()}>{t('A link')}</a>
                    </div>
                </Card>

                <div className="grid gap-5 lg:grid-cols-3">
                    {images.map((img) => {
                        const removing = form.data.remove.includes(img.key);
                        const shown = preview[img.key] || (removing ? null : img.url) || (img.key === 'brand_logo' ? defaults.logo : null);

                        return (
                            <Card key={img.key} className="space-y-3">
                                <h3 className="font-semibold">{t(img.label)}</h3>
                                <div className={`flex min-h-28 items-center justify-center rounded-xl border border-dashed border-slate-300 p-3 ${img.key === 'brand_logo_dark' ? 'bg-slate-900' : 'bg-slate-50'}`}>
                                    {shown ? <img src={shown} alt={t(img.label)} className="max-h-24 max-w-full object-contain" /> : <span className="text-xs text-slate-400">{t('Nothing uploaded')}</span>}
                                </div>
                                <p className="text-xs text-slate-500">{t(hints[img.key])}</p>
                                <input type="file" accept=".png,.jpg,.jpeg,.webp" className="block w-full text-xs" onChange={(e) => pick(img.key, e.target.files?.[0] ?? null)} />
                                {errors[img.key] && <p role="alert" className="text-xs text-rose-600">{errors[img.key]}</p>}
                                {img.custom && (
                                    <label className="flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" checked={removing} onChange={(e) => form.setData('remove', e.target.checked ? [...form.data.remove, img.key] : form.data.remove.filter((k) => k !== img.key))} />
                                        {t('Remove it and use the default')}
                                    </label>
                                )}
                            </Card>
                        );
                    })}
                </div>
                <button disabled={form.processing} className={primaryBtn}>
                    {form.processing ? t('Saving…') : t('Save branding')}
                </button>
            </form>
        </OfficePage>
    );
}
