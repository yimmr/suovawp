import { useMemo } from 'react';
import SettingsForm from './Settings/SettingsForm';
import type { SettingsFormProps } from './Settings/SettingsForm';
import SettingsTabsForm from './Settings/SettingsTabsForm';
import type { SettingsTabsFormProps } from './Settings/SettingsTabsForm';

import './index.css';
import { setLocaleText } from './Settings/utils';

interface OptionPageProps {
    _wpnonce: string;
    title?: string;
    customText?: SettingsFormProps['customText'];
    form: SettingsFormProps['fields'] | SettingsTabsFormProps;
    locale?: Record<string, string>;
}

export default ({ customText, title, form, _wpnonce, locale }: OptionPageProps) => {
    if (!form) return null;
    const _form = useMemo(() => formatApiData(form), [form]);
    if (locale) setLocaleText(locale);
    return (
        <>
            <header className="tw-flex p-0 tw-mb-4 md:tw-mb-5">
                {title && <h1 className="!tw-text-base !tw-p-0 tw-font-thin">{title}</h1>}
            </header>
            {'tabs' in _form ? (
                <SettingsTabsForm {..._form} _wpnonce={_wpnonce} customText={customText} />
            ) : (
                <SettingsForm fields={_form} _wpnonce={_wpnonce} customText={customText} />
            )}
        </>
    );
};

const snakeToCamel = (str: string) => str.replace(/_([a-z])/g, (g) => g[1].toUpperCase());

const convertObjectKey = (key: string) => {
    switch (key) {
        case 'default':
            return 'defaultValue';
        case 'active_tab':
            return 'activeTab';
        default:
            return snakeToCamel(key);
    }
};

const formatApiData = (data: any): any => {
    if (!data) {
        return data;
    }
    if (Array.isArray(data)) {
        return data.map((item) => formatApiData(item));
    } else if (typeof data === 'object' && data !== null) {
        return Object.fromEntries(
            Object.entries(data).map(([key, val]) => [convertObjectKey(key), formatApiData(val)])
        );
    }
    return data;
};
