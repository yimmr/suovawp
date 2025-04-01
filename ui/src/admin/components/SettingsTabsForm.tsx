import { useCallback, useState } from 'react';
import { TabPanel } from '@wordpress/components';
import SettingsForm from './SettingsForm';
import type { SettingsFormProps } from './SettingsForm';

interface SettingsTab {
    id: string;
    title: string;
    fields: SettingsFormProps['fields'];
    icon?: string;
    disabled?: boolean;
    closable?: boolean;
}

export interface SettingsTabsFormProps {
    _wpnonce: string;
    tabs: SettingsTab[];
    activeTab?: string;
    data?: { [k: string]: any };
    customText?: SettingsFormProps['customText'];
}

export default function ({
    tabs,
    data,
    _wpnonce,
    customText,
    activeTab = '',
}: SettingsTabsFormProps) {
    const [tabId, setTabId] = useState<string>(activeTab || tabs[0].id);
    const navItems = tabs.map((tab) => ({
        name: tab.id,
        title: tab.title,
        disabled: tab.disabled,
        closable: tab.closable,
    }));
    const tab = tabs.find((tab) => tab.id === tabId);
    if (!tab) return null;
    const handleSendBefore = useCallback(
        (fd: FormData) => {
            fd.set('active_tab', tabId);
        },
        [tabId]
    );
    return (
        <div>
            <nav className="tw-border-b tw-shadow-b tw-mb-4 md:tw-mb-6 tw-overflow-hidden tw-overflow-x-auto tw-whitespace-nowrap tw-scrollbar-simple">
                <TabPanel
                    className="[&_[role='tab']]:tw-px-8"
                    onSelect={setTabId}
                    tabs={navItems}
                    initialTabName={tabId}
                    children={() => null}
                />
            </nav>
            <SettingsForm
                fields={tab.fields}
                data={data ? data[tab.id] : {}}
                _wpnonce={_wpnonce}
                onSendBefore={handleSendBefore}
                customText={customText}
            />
        </div>
    );
}
