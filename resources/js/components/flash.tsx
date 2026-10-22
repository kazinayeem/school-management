import { useI18n } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2 } from 'lucide-react';

export function FlashStatus() {
    const { t } = useI18n();
    const flash = (usePage().props as { flash?: { status?: string | null; error?: string | null } }).flash;
    const status = flash?.status;
    const error = flash?.error;

    if (error) {
        return (
            <div role="alert" className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <AlertCircle className="h-4 w-4" /> {t(error)}
            </div>
        );
    }

    if (!status) return null;

    return (
        <div role="status" className="flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <CheckCircle2 className="h-4 w-4" /> {t(status)}
        </div>
    );
}
