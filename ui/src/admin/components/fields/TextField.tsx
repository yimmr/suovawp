import type { TextFieldProps } from './types2';
import InputField from './InputField';

export default ({ type, ...props }: TextFieldProps) => {
    return <InputField {...props} type="input" variant="text" />;
};
