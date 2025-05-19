import type { NumberFieldProps } from './types2';
import InputField from './InputField';

export default ({ type, ...props }: NumberFieldProps) => {
    return <InputField {...props} type="input" variant="number" />;
};
