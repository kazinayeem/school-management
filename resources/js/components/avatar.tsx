import { useI18n } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useRef } from 'react';

const tones = [
    'from-fuchsia-600 to-purple-600',
    'from-sky-500 to-indigo-600',
    'from-emerald-500 to-teal-600',
    'from-amber-500 to-orange-600',
    'from-rose-500 to-pink-600',
];

export function Avatar({ name, src, className = 'h-9 w-9 text-sm' }: { name: string; src?: string | null; className?: string }) {
    if (src) {
        return <img src={src} alt={name} loading="lazy" className={`${className} shrink-0 rounded-full object-cover`} />;
    }
    const tone = tones[[...name].reduce((n, c) => n + c.charCodeAt(0), 0) % tones.length];

    return (
        <span
            aria-hidden
            className={`${className} flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br font-semibold text-white ${tone}`}
        >
            {name.trim().charAt(0).toUpperCase()}
        </span>
    );
}

/** Shows a person's picture with a button to replace it and one to remove it. */
export function PhotoUpload({
    userId,
    name,
    src,
    className = 'h-24 w-24 text-3xl',
}: {
    userId: number;
    name: string;
    src?: string | null;
    className?: string;
}) {
    const { t } = useI18n();
    const input = useRef<HTMLInputElement>(null);
    const error = (usePage().props as { errors?: Record<string, string> }).errors?.photo;

    return (
        <div className="flex items-center gap-4">
            <Avatar name={name} src={src} className={className} />
            <div className="space-y-1">
                <input
                    ref={input}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    className="hidden"
                    aria-label={t('Choose a photo')}
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) router.post(`/photos/${userId}`, { photo: file }, { forceFormData: true, preserveScroll: true });
                        e.target.value = '';
                    }}
                />
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => input.current?.click()}
                        className="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                    >
                        <Camera className="h-3.5 w-3.5" /> {src ? t('Change photo') : t('Upload photo')}
                    </button>
                    {src && (
                        <button
                            type="button"
                            onClick={() => confirm(t('Remove this photo?')) && router.delete(`/photos/${userId}`, { preserveScroll: true })}
                            className="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs text-red-600 hover:bg-slate-50"
                        >
                            <Trash2 className="h-3.5 w-3.5" /> {t('Remove')}
                        </button>
                    )}
                </div>
                <p className="text-xs text-slate-400">{t('JPG, PNG, WebP or GIF, up to 3 MB.')}</p>
                {error && <p className="text-xs text-red-600">{t(error)}</p>}
            </div>
        </div>
    );
}
