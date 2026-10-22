import { PublicHome } from '@/components/public-home';
import { PublicPages } from '@/components/public-pages';
import { PublicShell } from '@/components/public-shell';
import { Head } from '@inertiajs/react';

export default function PublicSite({ page }: { page: Record<string, any> }) {
    return (
        <PublicShell ticker={page.ticker}>
            <Head title={page.kind === 'home' ? page.title : page.title} />
            {page.kind === 'home' ? <PublicHome page={page} /> : <PublicPages page={page} />}
        </PublicShell>
    );
}
