import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    Circle,
    ClipboardList,
    FileText,
    GraduationCap,
    LayoutGrid,
    type LucideIcon,
    Megaphone,
    School,
    Settings,
    User,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const icons: Record<string, LucideIcon> = {
    BookOpen,
    Bus,
    CalendarCheck,
    CalendarDays,
    CalendarOff,
    ClipboardList,
    FileText,
    GraduationCap,
    LayoutGrid,
    Megaphone,
    School,
    Settings,
    User,
    Users,
    Wallet,
};

export function AppSidebar() {
    const { menu, auth } = usePage<SharedData>().props;
    const roleLabel = auth.user.role.charAt(0).toUpperCase() + auth.user.role.slice(1);

    const items: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        ...menu.map((m) => ({
            title: m.title,
            url: m.url,
            icon: icons[m.icon] ?? Circle,
        })),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} label={`${roleLabel} Menu`} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
