import { useMemo } from 'react';
import SettingsForm from './SettingsForm';
import type { SettingsFormProps } from './SettingsForm';
import SettingsTabsForm from './SettingsTabsForm';
import type { SettingsTabsFormProps } from './SettingsTabsForm';
import { formatApiData, setLocaleText } from './utils';

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