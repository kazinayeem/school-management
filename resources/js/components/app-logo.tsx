import { GraduationCap } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-gradient-to-br from-fuchsia-800 to-fuchsia-500 text-white">
                <GraduationCap className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">ReadySchool</span>
            </div>
        </>
    );
}
