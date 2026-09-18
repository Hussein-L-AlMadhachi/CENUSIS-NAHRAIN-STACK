import React from 'react';

interface TabItem {
    label: string;
    content: React.ReactNode;
}

interface TabsProps {
    tabs: TabItem[];
    group: string;
    defaultCheckedIndex?: number;
    className?: string;
}

const Tabs: React.FC<TabsProps> = ({ tabs, group = "34", defaultCheckedIndex = 0, className }) => {
    return (
        <div className={`tabs tabs-border ${className || ""}`}>
            {tabs.map((tab, index) => (
                <React.Fragment key={index}>
                    <input
                        type="radio"
                        name={group}
                        className="tab"
                        aria-label={tab.label}
                        defaultChecked={index === defaultCheckedIndex}
                        readOnly
                    />
                    <div className="tab-content border-base-300 bg-base-100 p-10">
                        {tab.content}
                    </div>
                </React.Fragment>
            ))}
        </div>
    );
};

export default Tabs;