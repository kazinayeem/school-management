/** The maker's credit, shown at the foot of every page and printout. */
export function MadeBy({ className = '' }: { className?: string }) {
    return (
        <p className={`text-center text-xs text-slate-400 print:text-slate-500 ${className}`}>
            Made by{' '}
            <a href="https://bornosoft.bd" target="_blank" rel="noreferrer" className="font-semibold hover:underline">
                bornosoft.bd
            </a>
        </p>
    );
}
