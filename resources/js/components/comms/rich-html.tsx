export function Rich({ html, className = '' }: { html: string; className?: string }) {
    return <div className={`rich ${className}`} dangerouslySetInnerHTML={{ __html: html }} />;
}
