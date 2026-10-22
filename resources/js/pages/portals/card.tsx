import { MyCard, type CardData } from '@/components/personal-card';
import { PortalShell } from '@/components/portal-shell';
import { useI18n } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';

export default function Card({ card, userId, notices }: { card: CardData; userId: number; notices: { title: string; body: string; date: string }[] }) {
    const { t } = useI18n();
    const parent = card.kind === 'parent';
    const title = parent ? 'Visiting Pass' : 'My ID Card';
    void usePage();

    return (
        <PortalShell caption={parent ? 'Parent portal' : 'Student portal'} roleLabel={parent ? 'Parent' : 'Student'} title={title} notices={notices}>
            <Head title={t(title)} />
            <MyCard card={card} userId={userId} />
        </PortalShell>
    );
}
