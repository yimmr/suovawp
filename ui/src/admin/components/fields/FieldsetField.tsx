import BaseField from './BaseField';
import type { FieldsetFieldProps } from './types';
import FormContent from '../FormContent';
import { PanelBody } from '@wordpress/components';
import HelpText from './HelpText';

export default ({
    errors,
    label,
    help,
    fields,
    className,
    initOpen,
    opened,
    icon,
    errorId,
    onDeleteError,
    parentName,
}: FieldsetFieldProps) => {
    return (
        <BaseField errors={errors?._errors}>
            <PanelBody
                title={label}
                className={`${className} tw-border tw-border-solid ${
                    errors ? 'tw-border-red-500' : 'tw-border-gray-300/50'
                } tw-bg-white !tw-p-0 [&.is-opened_.components-panel\\_\\_body-title]:tw-border-b [&>h2]:!tw-m-0 [&>h2_.components-button]:!tw-font-bold`}
                scrollAfterOpen={false}
                initialOpen={initOpen}
                opened={opened}
                icon={icon}
                children={({ opened }) => (
                    <div
                        className={`suovawp-fieldset-body tw-p-4 ${
                            opened ? 'tw-block' : 'tw-hidden'
                        }`}
                    >
                        {help && <HelpText className="tw-mb-6" help={help} />}
                        <FormContent
                            isChild={true}
                            fields={fields}
                            errors={errors as any}
                            parentId={errorId}
                            onDeleteError={onDeleteError}
                            parentName={parentName}
                            defaultWidth="full"
                        />
                    </div>
                )}
            ></PanelBody>
        </BaseField>
    );
};
