import type { NumberFieldProps } from './types';
import InputField from './InputField';

export default ({ type, ...props }: NumberFieldProps) => {
    return <InputField {...props} type="input" variant="number" />;
};
