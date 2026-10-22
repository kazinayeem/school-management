import { animate, motion, useInView, useReducedMotion } from 'motion/react';
import { type PropsWithChildren, useEffect, useRef, useState } from 'react';

const ease = [0.22, 1, 0.36, 1] as const;

/** Fades and lifts page content in. Skipped for people who ask for reduced motion. */
export function AnimatedPage({ children, className }: PropsWithChildren<{ className?: string }>) {
    const reduce = useReducedMotion();

    return (
        <motion.div className={className} initial={reduce ? false : { opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.22, ease }}>
            {children}
        </motion.div>
    );
}

/** One block that fades up when it first scrolls into view. Pass a small `delay` to stagger neighbours. */
export function Reveal({ children, delay = 0, className }: PropsWithChildren<{ delay?: number; className?: string }>) {
    const reduce = useReducedMotion();
    const ref = useRef<HTMLDivElement>(null);
    const seen = useInView(ref, { once: true, margin: '0px 0px -40px 0px' });

    return (
        <motion.div ref={ref} className={className} initial={reduce ? false : { opacity: 0, y: 10 }} animate={seen || reduce ? { opacity: 1, y: 0 } : undefined} transition={{ duration: 0.35, delay, ease }}>
            {children}
        </motion.div>
    );
}

/** Content that swaps when `id` changes (a tab, a child): a quick fade without keeping the old one around. */
export function Swap({ id, children, className }: PropsWithChildren<{ id: string | number; className?: string }>) {
    const reduce = useReducedMotion();

    return (
        <motion.div key={id} className={className} initial={reduce ? false : { opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.18, ease }}>
            {children}
        </motion.div>
    );
}

/** A number that counts to its new value. Text such as "83%" or "Tk 4,200" keeps its prefix and suffix. */
export function AnimatedNumber({ value, className }: { value: string | number; className?: string }) {
    const reduce = useReducedMotion();
    const text = String(value);
    const match = text.match(/^(\D*)([\d,]+(?:\.\d+)?)(.*)$/);
    const target = match ? Number(match[2].replace(/,/g, '')) : NaN;
    const [shown, setShown] = useState(reduce || Number.isNaN(target) ? target : 0);
    const first = useRef(true);

    useEffect(() => {
        if (reduce || Number.isNaN(target)) {
            setShown(target);

            return;
        }
        const controls = animate(first.current ? 0 : shown, target, { duration: 0.7, ease, onUpdate: (v) => setShown(v) });
        first.current = false;

        return () => controls.stop();
    }, [target]);

    if (!match || Number.isNaN(target)) return <span className={className}>{text}</span>;
    const decimals = match[2].includes('.') ? match[2].split('.')[1].length : 0;
    const formatted = shown.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

    return (
        <span className={`tabular-nums ${className ?? ''}`} aria-label={text}>
            {match[1]}
            {match[2].includes(',') || target >= 1000 ? formatted : shown.toFixed(decimals)}
            {match[3]}
        </span>
    );
}
