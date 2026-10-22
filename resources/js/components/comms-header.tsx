import { useI18n } from '@/lib/i18n';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Mail } from 'lucide-react';
import { useEffect } from 'react';

type Comms = { messages: number; notifications: number; base: string } | null;

/** The inbox and notification icons with unread counts. The counts refresh every 30 seconds while the tab is open. */
export function CommsButtons() {
    const { t } = useI18n();
    const comms = (usePage().props as { comms?: Comms }).comms;

    useEffect(() => {
        if (!comms) return;
        const id = setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['comms'] });
        }, 30000);

        return () => clearInterval(id);
    }, [!!comms]);

    if (!comms) return null;
    const badge = (n: number) =>
        n > 0 && (
            <span className="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white" aria-hidden>
                {n > 99 ? '99+' : n}
            </span>
        );

    return (
        <>
            <Link href={`${comms.base}/messages`} aria-label={`${t('Messages')}${comms.messages ? `, ${t('{n} unread', { n: comms.messages })}` : ''}`} className="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                <Mail className="h-4 w-4" />
                {badge(comms.messages)}
            </Link>
            <Link href={`${comms.base}/notifications`} aria-label={`${t('Notifications')}${comms.notifications ? `, ${t('{n} unread', { n: comms.notifications })}` : ''}`} className="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                <Bell className="h-4 w-4" />
                {badge(comms.notifications)}
            </Link>
        </>
    );
}
