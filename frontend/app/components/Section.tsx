interface SectionProps {
    children: React.ReactNode;
    className?: string;
}



export function Section({ children }: SectionProps) {
    return (
        <section id="section" className={`pb-12 md:pb-16 lg:pb-20 ${""}`}>
        {children}
        </section>
    );
}



export function Subsection({ children }: SectionProps) {
    return (
        <div id="subsection" className={`pb-6 md:pb-8 lg:pb-0 ${""}`}>
        {children}
        </div>
    );
}
