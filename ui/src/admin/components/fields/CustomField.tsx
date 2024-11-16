import BaseField from './BaseField';
import type { CustomFieldProps } from './types';

export default (props: CustomFieldProps) => {
    const { children: Child, html } = props;
    if (typeof Child === 'string') {
        return (
            <BaseField>
                {html ? <div dangerouslySetInnerHTML={{ __html: Child }} /> : <div>{Child}</div>}
            </BaseField>
        );
    }
    return (
        <BaseField>
            <Child {...props} />
        </BaseField>
    );
};
